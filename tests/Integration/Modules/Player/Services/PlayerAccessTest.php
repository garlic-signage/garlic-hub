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

namespace Tests\Integration\Modules\Player\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Player\Services\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may change or delete which player. Fixtures: players 1 and 2 belong to anna,
 * 3 to bernd, 4 to the admin.
 *
 * Currently only the module admin may change a player in the edge edition. Even the owner
 * is denied. These tests describe that and protect the "deny by default".
 */
#[Group('acl')]
class PlayerAccessTest extends ContainerTestCase
{
	private PlayerService $playerService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->playerService = $this->container->get(PlayerService::class);
	}

	public function testAdminMayDeleteEveryPlayer(): void
	{
		$this->actingAs(1);

		static::assertSame(1, $this->playerService->delete(1));
		static::assertSame(1, $this->playerService->delete(3));
		static::assertSame([2, 4], $this->remainingPlayerIds());
	}

	public function testUserMayNotDeleteAForeignPlayer(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->playerService->delete(3);
		}
		finally
		{
			static::assertSame([1, 2, 3, 4], $this->remainingPlayerIds());
		}
	}

	public function testUserMayNotDeleteAnOwnPlayer(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->playerService->delete(1);
		}
		finally
		{
			static::assertSame([1, 2, 3, 4], $this->remainingPlayerIds());
		}
	}

	public function testUserMayNotChangeThePlaylistOfAForeignPlayer(): void
	{
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (7, 3, 'master', 'Bernds Liste')");
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->playerService->replaceMasterPlaylist(3, 7);
		}
		finally
		{
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT playlist_id FROM player WHERE player_id = 3'));
		}
	}

	public function testAdminMayChangeThePlaylistOfAnyPlayer(): void
	{
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (7, 3, 'master', 'Bernds Liste')");
		$this->actingAs(1);

		$result = $this->playerService->replaceMasterPlaylist(3, 7);

		static::assertSame(1, $result['affected']);
		static::assertSame('Bernds Liste', $result['playlist_name']);
		static::assertSame(7, (int) $this->connection->fetchOne('SELECT playlist_id FROM player WHERE player_id = 3'));
	}

	public function testFetchAclCheckedPlayerDataForAnUnknownPlayer(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->playerService->fetchAclCheckedPlayerData(999);
	}

	public function testUserGetsNoPlayerDataOfAnUnknownPlayerEither(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->playerService->fetchAclCheckedPlayerData(999);
	}

	/**
	 * @return list<int>
	 */
	private function remainingPlayerIds(): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT player_id FROM player ORDER BY player_id'));
	}
}
