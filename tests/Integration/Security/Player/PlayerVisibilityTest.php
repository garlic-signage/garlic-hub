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

namespace Tests\Integration\Security\Player;

use App\Modules\Player\Services\PlayerDatatableService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\ContainerTestCase;

/**
 * Who sees which players in the player list. Fixtures: players 1 and 2 belong to anna,
 * 3 to bernd, 4 to the admin.
 */
#[Group('security')]
class PlayerVisibilityTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('security/users.sql');
		$this->loadFixture('security/players.sql');
	}

	public function testAdminSeesAllPlayers(): void
	{
		static::assertSame(
			['Annas Player 1', 'Annas Player 2', 'Bernds Player', 'Admin Player'],
			$this->playerNamesVisibleFor(1)
		);
	}

	public function testUserSeesOnlyOwnPlayers(): void
	{
		static::assertSame(['Annas Player 1', 'Annas Player 2'], $this->playerNamesVisibleFor(2));
		static::assertSame(['Bernds Player'], $this->playerNamesVisibleFor(3));
	}

	public function testUserWithoutPlayersSeesNothing(): void
	{
		$this->connection->executeStatement("INSERT INTO user_main (UID, status, locale, username, email) VALUES (4, 3, 'en_US', 'carla', 'carla@example.com')");

		static::assertSame([], $this->playerNamesVisibleFor(4));
	}

	public function testAdminRightOnAnotherModuleDoesNotShowForeignPlayers(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 8, 'playlists')");

		static::assertSame(['Annas Player 1', 'Annas Player 2'], $this->playerNamesVisibleFor(2));
	}

	public function testTotalCountMatchesWhatTheUserSees(): void
	{
		$this->actingAs(2);
		$service = $this->container->get(PlayerDatatableService::class);
		$service->loadDatatable();

		static::assertSame(2, $service->getCurrentTotalResult());
	}

	/**
	 * @return list<string>
	 */
	private function playerNamesVisibleFor(int $UID): array
	{
		$this->actingAs($UID);
		$service = $this->container->get(PlayerDatatableService::class);
		$service->loadDatatable();

		return array_column($service->getCurrentFilterResults(), 'player_name');
	}
}
