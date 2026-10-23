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

use App\Modules\Playlists\Services\PlaylistsDatatableService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who sees which playlists in the playlist list. Fixtures: playlists 1 and 3 belong to anna, 2 to bernd.
 */
#[Group('acl')]
class PlaylistVisibilityTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
	}

	public function testAdminSeesAllPlaylists(): void
	{
		static::assertSame(
			['Annas Lobby', 'Bernds Kantine', 'Annas leere Liste'],
			$this->playlistNamesVisibleFor(1)
		);
	}

	public function testUserSeesOnlyOwnPlaylists(): void
	{
		static::assertSame(['Annas Lobby', 'Annas leere Liste'], $this->playlistNamesVisibleFor(2));
		static::assertSame(['Bernds Kantine'], $this->playlistNamesVisibleFor(3));
	}

	public function testAdminRightOnAnotherModuleDoesNotShowForeignPlaylists(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 8, 'player')");

		static::assertSame(['Annas Lobby', 'Annas leere Liste'], $this->playlistNamesVisibleFor(2));
	}

	public function testTotalCountMatchesWhatTheUserSees(): void
	{
		$this->actingAs(3);
		$service = $this->container->get(PlaylistsDatatableService::class);
		$service->loadDatatable();

		static::assertSame(1, $service->getCurrentTotalResult());
	}

	/**
	 * @return list<string>
	 */
	private function playlistNamesVisibleFor(int $UID): array
	{
		$this->actingAs($UID);
		$service = $this->container->get(PlaylistsDatatableService::class);
		$service->loadDatatable();

		return array_column($service->getCurrentFilterResults(), 'playlist_name');
	}
}
