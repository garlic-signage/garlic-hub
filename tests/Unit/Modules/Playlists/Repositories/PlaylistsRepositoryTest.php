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

namespace Tests\Unit\Modules\Playlists\Repositories;

use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Only what the integration tests (tests/Integration) can not reach with SQLite: MariaDB needs
 * no pragma for the foreign keys. Everything else of PlaylistsRepository is covered there.
 */
class PlaylistsRepositoryTest extends TestCase
{
	private Connection&MockObject $connectionMock;
	private PlaylistsRepository $repository;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock = $this->createMock(Connection::class);
		$this->repository     = new PlaylistsRepository($this->connectionMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteDoesNotSetThePragmaOutsideOfSqlite(): void
	{
		$this->connectionMock->expects($this->once())->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());
		$this->connectionMock->expects($this->never())->method('executeQuery');
		$this->connectionMock->expects($this->once())->method('delete')
			->with('playlists', ['playlist_id' => 1])
			->willReturn(1);

		static::assertSame(1, $this->repository->delete(1));
	}
}
