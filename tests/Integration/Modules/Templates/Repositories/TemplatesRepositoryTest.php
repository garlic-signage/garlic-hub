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

namespace Tests\Integration\Modules\Templates\Repositories;

use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class TemplatesRepositoryTest extends RepositoryTestCase
{
	private TemplatesRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new TemplatesRepository($this->connection);
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstWithUserNameAddsTheOwner(): void
	{
		$template = $this->repository->findFirstWithUserName(2);

		static::assertSame('Annas Vorlage', $template['name']);
		static::assertSame('anna', $template['username']);
		static::assertSame(1, (int) $template['company_id']);
		static::assertSame(2, (int) $template['UID']);
		static::assertSame('private', $template['visibility']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstWithUserNameKeepsTheContent(): void
	{
		$template = $this->repository->findFirstWithUserName(1);

		static::assertSame('{"objects":[{"fileName":"abc123.jpg","src":"x"}]}', $template['content']);
		static::assertSame('von Admin', $template['description']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstWithUserNameOfAnUnknownTemplate(): void
	{
		static::assertSame([], $this->repository->findFirstWithUserName(999));
	}

	/**
	 * @throws Exception
	 */
	public function testInsertUsesTheDefaultsOfTheSchema(): void
	{
		$id = (int) $this->repository->insert(['UID' => 3, 'name' => 'Neu']);

		static::assertSame(4, $id);
		$template = $this->repository->findFirstWithUserName($id);
		static::assertSame('private', $template['visibility']);
		static::assertSame('fabric.js', $template['type']);
		static::assertNull($template['content']);
		static::assertNotSame('', $template['create_date']);
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateChangesOnlyTheGivenTemplate(): void
	{
		static::assertSame(1, $this->repository->update(2, ['name' => 'Umbenannt', 'visibility' => 'public']));

		$changed = $this->repository->findFirstWithUserName(2);
		static::assertSame('Umbenannt', $changed['name']);
		static::assertSame('public', $changed['visibility']);
		static::assertSame('Bernds Vorlage', $this->repository->findFirstWithUserName(3)['name']);
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteRemovesOnlyTheGivenTemplate(): void
	{
		static::assertSame(1, $this->repository->delete(3));

		static::assertSame([], $this->repository->findFirstWithUserName(3));
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates'));
		static::assertSame(0, $this->repository->delete(3));
	}
}
