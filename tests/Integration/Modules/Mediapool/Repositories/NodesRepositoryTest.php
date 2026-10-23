<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace Tests\Integration\Modules\Mediapool\Repositories;

use App\Framework\Exceptions\DatabaseException;
use App\Modules\Mediapool\Repositories\NodesRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class NodesRepositoryTest extends RepositoryTestCase
{
	private NodesRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new NodesRepository($this->connection);
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testGetNodeAddsOwnerAndCountsTheChildren(): void
	{
		$root = $this->repository->getNode(7);

		static::assertSame('anna', $root['name']);
		static::assertSame('anna', $root['username']);
		static::assertSame(1, (int) $root['company_id']);
		static::assertSame(2, (int) $root['children']);
		static::assertSame(1, (int) $root['is_user_folder']);
		static::assertSame(0, (int) $root['parent_id']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetNodeOfALeafHasNoChildren(): void
	{
		static::assertSame(0, (int) $this->repository->getNode(8)['children']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetNodeOfAnUnknownNode(): void
	{
		static::assertSame([], $this->repository->getNode(999));
	}

	/**
	 * @throws Exception
	 */
	public function testGetNodeOfThePublicTreeFromTheMigration(): void
	{
		$public = $this->repository->getNode(1);

		static::assertSame('public', $public['name']);
		static::assertSame(2, (int) $public['visibility']);
		static::assertSame(5, (int) $public['children']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetUserRootNodeFindsOnlyTheUserFolder(): void
	{
		static::assertSame(7, (int) $this->repository->getUserRootNode(2)['node_id']);
		static::assertSame(10, (int) $this->repository->getUserRootNode(3)['node_id']);
		// the admin owns the public tree, but no user folder
		static::assertSame([], $this->repository->getUserRootNode(1));
		static::assertSame([], $this->repository->getUserRootNode(999));
	}

	/**
	 * @throws Exception
	 * @throws DatabaseException
	 */
	public function testFindNodeOwner(): void
	{
		$node = $this->repository->findNodeOwner(11);

		static::assertSame(3, (int) $node['UID']);
		static::assertSame('Privat', $node['name']);
		static::assertSame(10, (int) $node['parent_id']);
		static::assertSame(0, (int) $node['visibility']);
		static::assertSame(1, (int) $node['company_id']);
	}

	/**
	 * @throws Exception
	 * @throws DatabaseException
	 */
	public function testFindNodeOwnerOfAnUnknownNodeThrows(): void
	{
		$this->expectException(DatabaseException::class);
		$this->repository->findNodeOwner(999);
	}
}
