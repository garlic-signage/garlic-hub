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

use App\Modules\Mediapool\Repositories\FilesRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class FilesRepositoryTest extends RepositoryTestCase
{
	private FilesRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new FilesRepository($this->connection);
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllWithOwnerByIdAddsTheOwner(): void
	{
		$media = $this->repository->findAllWithOwnerById('33333333-3333-4333-8333-333333333333');

		static::assertSame('bernd', $media['username']);
		static::assertSame(11, (int) $media['node_id']);
		static::assertSame('geheim.png', $media['filename']);
		static::assertSame('image/png', $media['mimetype']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllWithOwnerByIdIgnoresDeletedAndUnknownMedia(): void
	{
		static::assertSame([], $this->repository->findAllWithOwnerById('66666666-6666-4666-8666-666666666666'));
		static::assertSame([], $this->repository->findAllWithOwnerById('00000000-0000-4000-8000-000000000000'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllWithOwnerByCheckSum(): void
	{
		$media = $this->repository->findAllWithOwnerByCheckSum('dddddddddddddddddddddddddddddddd');

		static::assertSame('teilen.png', $media['filename']);
		static::assertSame('bernd', $media['username']);
		static::assertSame([], $this->repository->findAllWithOwnerByCheckSum('0000'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByNodeIdIsSortedByUploadTimeAndSkipsDeletedMedia(): void
	{
		$media = $this->repository->findAllByNodeId(8);

		static::assertSame(['film.mp4', 'urlaub.jpg'], array_column($media, 'filename'));
		static::assertSame([], $this->repository->findAllByNodeId(9));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByNodeIdFiltersByMimetype(): void
	{
		static::assertSame(['urlaub.jpg'], array_column($this->repository->findAllByNodeId(8, 'image'), 'filename'));
		static::assertSame(['film.mp4'], array_column($this->repository->findAllByNodeId(8, 'video'), 'filename'));
		static::assertSame([], $this->repository->findAllByNodeId(8, 'audio'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByNodeIdOnlyReturnsTheNode(): void
	{
		static::assertSame(['teilen.png'], array_column($this->repository->findAllByNodeId(12), 'filename'));
		static::assertSame(['logo.jpg'], array_column($this->repository->findAllByNodeId(2), 'filename'));
	}
}
