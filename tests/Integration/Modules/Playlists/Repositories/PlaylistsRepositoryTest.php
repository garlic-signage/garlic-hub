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

namespace Tests\Integration\Modules\Playlists\Repositories;

use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class PlaylistsRepositoryTest extends RepositoryTestCase
{
	private PlaylistsRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new PlaylistsRepository($this->connection);
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/items.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteRemovesThePlaylistWithItsItems(): void
	{
		static::assertSame(1, $this->repository->delete(1));

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists WHERE playlist_id = 1'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 1'));
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteKeepsOtherPlaylists(): void
	{
		$this->repository->delete(1);

		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists'));
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items'));
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteOfAnUnknownPlaylistChangesNothing(): void
	{
		static::assertSame(0, $this->repository->delete(999));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstWithUserNameAddsOwnerData(): void
	{
		$playlist = $this->repository->findFirstWithUserName(2);

		static::assertSame('Bernds Kantine', $playlist['playlist_name']);
		static::assertSame('bernd', $playlist['username']);
		static::assertSame(1, (int) $playlist['company_id']);
		static::assertSame(600, (int) $playlist['time_limit']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstWithUserNameForAnUnknownPlaylist(): void
	{
		static::assertSame([], $this->repository->findFirstWithUserName(999));
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateExportWritesMetricsAndExportTime(): void
	{
		$affected = $this->repository->updateExport(1, ['duration' => 68, 'filesize' => 1170]);

		static::assertSame(1, $affected);
		$playlist = $this->connection->fetchAssociative('SELECT duration, filesize, export_time FROM playlists WHERE playlist_id = 1');
		static::assertIsArray($playlist);
		static::assertSame(68, (int) $playlist['duration']);
		static::assertSame(1170, (int) $playlist['filesize']);
		static::assertNotSame('0000-00-00 00:00:00', $playlist['export_time']);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT duration FROM playlists WHERE playlist_id = 2'));
	}
}
