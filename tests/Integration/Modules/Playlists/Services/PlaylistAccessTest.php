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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may read, change and delete which playlist. The owner may edit and delete a playlist
 * (different from players), the admin may do everything, everybody else nothing.
 * Fixtures: playlists 1 and 3 belong to anna, 2 to bernd.
 */
#[Group('acl')]
class PlaylistAccessTest extends ContainerTestCase
{
	private PlaylistsService $playlistsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->playlistsService = $this->container->get(PlaylistsService::class);
	}

	public function testOwnerMayLoadHisPlaylist(): void
	{
		$this->actingAs(2);

		static::assertSame(['playlist_id' => 1, 'name' => 'Annas Lobby'], $this->playlistsService->loadNameById(1));
	}

	public function testAdminMayLoadEveryPlaylist(): void
	{
		$this->actingAs(1);

		static::assertSame('Bernds Kantine', $this->playlistsService->loadNameById(2)['name']);
	}

	public function testUserMayNotLoadAForeignPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->playlistsService->loadNameById(2);
	}

	public function testUserGetsNotFoundForAnUnknownPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->playlistsService->loadNameById(999);
	}

	public function testOwnerMayDeleteHisPlaylistWithItsItems(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->playlistsService->delete(1));
		static::assertSame([2, 3], $this->remainingPlaylistIds());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 1'));
	}

	public function testAdminMayDeleteEveryPlaylist(): void
	{
		$this->actingAs(1);

		static::assertSame(1, $this->playlistsService->delete(2));
		static::assertSame([1, 3], $this->remainingPlaylistIds());
	}

	public function testUserMayNotDeleteAForeignPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->delete(2);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame([1, 2, 3], $this->remainingPlaylistIds());
			static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 2'));
		}
	}

	public function testOwnerMayToggleShuffle(): void
	{
		$this->actingAs(2);

		$this->playlistsService->toggleShuffle(1);

		static::assertSame(1, (int) $this->connection->fetchOne('SELECT shuffle FROM playlists WHERE playlist_id = 1'));
	}

	public function testUserMayNotToggleShuffleOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->toggleShuffle(2);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT shuffle FROM playlists WHERE playlist_id = 2'));
		}
	}

	public function testUserMayNotRenameAForeignPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->updateSecure(['playlist_id' => 2, 'playlist_name' => 'gehackt']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('Bernds Kantine', $this->connection->fetchOne('SELECT playlist_name FROM playlists WHERE playlist_id = 2'));
		}
	}

	/**
	 * @return list<int>
	 */
	private function remainingPlaylistIds(): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT playlist_id FROM playlists ORDER BY playlist_id'));
	}
}
