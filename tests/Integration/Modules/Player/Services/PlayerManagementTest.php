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
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Player\Services\PlayerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the player service changes besides the rights (see PlayerAccessTest). Players: 1 and 2 belong to anna,
 * 3 to bernd, 4 to the admin. Playlists: 1 and 3 are master lists of anna, 2 is an internal list of bernd.
 */
#[Group('acl')]
class PlayerManagementTest extends ContainerTestCase
{
	private PlayerService $playerService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->playerService = $this->container->get(PlayerService::class);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function playerRow(int $playerId): array
	{
		/** @var array<string,mixed> $row */
		$row = $this->connection->fetchAssociative('SELECT * FROM player WHERE player_id = ?', [$playerId]);

		return $row;
	}

	public function testTheAdminChangesTheNetworkSettingsOfAPlayer(): void
	{
		$this->actingAs(1);

		$affected = $this->playerService->updatePlayer(3, ['api_endpoint' => 'http://10.0.0.7:8080/api', 'is_intranet' => 1]);

		static::assertSame(1, $affected);
		static::assertSame('http://10.0.0.7:8080/api', $this->playerRow(3)['api_endpoint']);
		static::assertSame(1, (int) $this->playerRow(3)['is_intranet']);
	}

	/**
	 * Only the two settings are taken, whatever else is in the data.
	 */
	public function testNothingButTheNetworkSettingsIsChanged(): void
	{
		$this->actingAs(1);
		$before = $this->playerRow(3);

		$this->playerService->updatePlayer(3, ['api_endpoint' => 'http://x', 'player_name' => 'gehackt', 'UID' => 1, 'status' => 9, 'playlist_id' => 3, 'uuid' => 'x']);

		$after = $this->playerRow(3);
		foreach (['player_name', 'UID', 'status', 'playlist_id', 'uuid'] as $field)
			static::assertSame($before[$field], $after[$field], $field);
		static::assertSame('http://x', $after['api_endpoint']);
	}

	public function testOnlyTheGivenSettingIsChanged(): void
	{
		$this->actingAs(1);
		$this->playerService->updatePlayer(3, ['api_endpoint' => 'http://x', 'is_intranet' => 1]);

		$this->playerService->updatePlayer(3, ['is_intranet' => 0]);

		static::assertSame('http://x', $this->playerRow(3)['api_endpoint']);
		static::assertSame(0, (int) $this->playerRow(3)['is_intranet']);
	}

	/**
	 * The method itself checks the rights: it does not rely on the caller.
	 */
	public function testAUserMayNotChangeAForeignPlayer(): void
	{
		$this->actingAs(2);
		$before = $this->playerRow(3);

		try
		{
			$this->playerService->updatePlayer(3, ['api_endpoint' => 'http://evil.example', 'is_intranet' => 1]);
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame($before, $this->playerRow(3));
		}
	}

	public function testAUnknownPlayerIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->playerService->updatePlayer(999, ['api_endpoint' => 'http://x']);
	}

	public function testAMasterPlaylistIsSetAtAPlayer(): void
	{
		$this->actingAs(1);

		$result = $this->playerService->replaceMasterPlaylist(3, 1);

		static::assertSame(['affected' => 1, 'playlist_name' => 'Annas Lobby', 'is_intranet' => 0], $result);
		static::assertSame(1, (int) $this->playerRow(3)['playlist_id']);
	}

	public function testAMultizonePlaylistIsAllowedToo(): void
	{
		$this->connection->executeStatement("UPDATE playlists SET playlist_mode = 'multizone' WHERE playlist_id = 3");
		$this->actingAs(1);

		$result = $this->playerService->replaceMasterPlaylist(3, 3);

		static::assertSame('Annas leere Liste', $result['playlist_name']);
		static::assertSame(3, (int) $this->playerRow(3)['playlist_id']);
	}

	public function testAnInternalPlaylistIsNoMasterPlaylist(): void
	{
		$this->actingAs(1);

		try
		{
			$this->playerService->replaceMasterPlaylist(3, 2);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('playlist_not_master', $e->getTranslationKey());
			static::assertSame(['playlist_name' => 'Bernds Kantine'], $e->getReplacements());
		}

		static::assertSame(0, (int) $this->playerRow(3)['playlist_id']);
	}

	public function testThePlaylistIsTakenOffWithTheId0(): void
	{
		$this->actingAs(1);
		$this->playerService->replaceMasterPlaylist(3, 1);

		$result = $this->playerService->replaceMasterPlaylist(3, 0);

		static::assertSame(['affected' => 1, 'playlist_name' => '', 'is_intranet' => 0], $result);
		static::assertSame(0, (int) $this->playerRow(3)['playlist_id']);
	}

	public function testAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->playerService->replaceMasterPlaylist(3, 999);
	}

	/**
	 * @return array<string,array{int,int}> the user, the player
	 */
	public static function foreignPlayers(): array
	{
		return ['anna and a player of bernd' => [2, 3], 'bernd and a player of anna' => [3, 1], 'anna and her own player' => [2, 1]];
	}

	#[DataProvider('foreignPlayers')]
	public function testAUserWithoutTheRightMayNotSetAPlaylist(int $user, int $player): void
	{
		$this->actingAs($user);
		$before = $this->playerRow($player);

		try
		{
			$this->playerService->replaceMasterPlaylist($player, 1);
			static::fail('Expected an AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame($before, $this->playerRow($player));
		}
	}

	public function testThePlaylistOfAnotherUserNeedsTheRightOnThePlaylist(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('DELETE FROM user_acl WHERE UID = 1 AND module = ?', ['playlists']);
		$this->container->get(\App\Framework\Http\Session::class)->delete('user');
		$this->container->get(\App\Shared\User\UsersService::class)->invalidateCache(1);
		$this->actingAs(1);

		$this->expectException(AccessDeniedException::class);
		$this->playerService->replaceMasterPlaylist(3, 1);
	}

	public function testTheDashboardCountsThePlayersByTheirLastAccess(): void
	{
		$this->connection->executeStatement("UPDATE player SET refresh = 300, last_access = CURRENT_TIMESTAMP WHERE player_id = 1");
		$this->connection->executeStatement("UPDATE player SET refresh = 300, last_access = DATETIME('now', '-700 seconds') WHERE player_id = 2");
		$this->connection->executeStatement("UPDATE player SET refresh = 300, last_access = DATETIME('now', '-5000 seconds') WHERE player_id IN (3, 4)");

		static::assertEqualsCanonicalizing(['active' => 1, 'pending' => 1, 'inactive' => 2], array_map('intval', $this->playerService->findAllForDashboard()));
	}

	public function testTheDashboardOfNoPlayersIsZero(): void
	{
		$this->connection->executeStatement('DELETE FROM player');

		static::assertEqualsCanonicalizing(['active' => 0, 'inactive' => 0, 'pending' => 0], array_map('intval', $this->playerService->findAllForDashboard()));
	}
}
