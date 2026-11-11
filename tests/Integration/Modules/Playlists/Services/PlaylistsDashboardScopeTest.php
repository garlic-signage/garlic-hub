<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The numbers of the dashboard in the scope of the list of the playlists.
 * Playlist 1 and 2 belong to anna (2), 3 to bernd (3), 4 to the admin (1).
 */
#[Group('acl')]
class PlaylistsDashboardScopeTest extends ContainerTestCase
{
	private PlaylistsService $playlistsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/playlists.sql');
		$this->playlistsService = $this->container->get(PlaylistsService::class);
	}

	public function testTheModuleAdminCountsAllPlaylists(): void
	{
		$this->connection->executeStatement("UPDATE playlists SET release_status = 'ready' WHERE playlist_id IN (1, 3)");
		$this->actingAs(1);

		static::assertSame(['total' => 4, 'waiting' => 2], $this->playlistsService->countForDashboard());
	}

	public function testAUserCountsHisOwnPlaylists(): void
	{
		$this->connection->executeStatement("UPDATE playlists SET release_status = 'ready' WHERE playlist_id IN (1, 3)");
		$this->actingAs(2);

		static::assertSame(['total' => 2, 'waiting' => 1], $this->playlistsService->countForDashboard());
	}

	/**
	 * Like the list: a playlist shared to edit counts, one shared only to view does not.
	 */
	public function testAUserCountsThePlaylistsSharedToEdit(): void
	{
		$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (3, 'playlists_editor', '1'), (3, 'playlists_viewer', '2')");
		$this->connection->executeStatement("UPDATE playlists SET release_status = 'ready' WHERE playlist_id IN (1, 2)");
		$this->actingAs(3);

		static::assertSame(['total' => 2, 'waiting' => 1], $this->playlistsService->countForDashboard());
	}

	/**
	 * The list of a sub admin is empty until his scope comes with the Enterprise edition.
	 */
	public function testASubAdminCountsNothing(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (3, 4, 'playlists')");
		$this->actingAs(3);

		static::assertSame(['total' => 0, 'waiting' => 0], $this->playlistsService->countForDashboard());
	}

	public function testWithoutPlaylistsEverythingIsZero(): void
	{
		$this->connection->executeStatement('DELETE FROM playlists');
		$this->actingAs(1);

		static::assertSame(['total' => 0, 'waiting' => 0], $this->playlistsService->countForDashboard());
	}
}
