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
 * Which playlists of the list are in use: nested in another playlist or assigned to a player.
 * A playlist in use may not be deleted. Fixtures: playlist 2 is nested in 1, 3 is nested in 2.
 */
#[Group('acl')]
class PlaylistUsageTest extends ContainerTestCase
{
	private PlaylistsDatatableService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->service = $this->container->get(PlaylistsDatatableService::class);
	}

	public function testNestedPlaylistsAreInUse(): void
	{
		static::assertSame([2 => true, 3 => true], $this->service->getPlaylistsInUse([1, 2, 3]));
	}

	public function testAPlaylistOfAPlayerIsInUse(): void
	{
		$this->connection->executeStatement("INSERT INTO player (player_id, playlist_id, UID, uuid) VALUES (1, 1, 2, '11111111-1111-4111-8111-111111111111')");

		static::assertSame([1 => true, 2 => true, 3 => true], $this->sorted($this->service->getPlaylistsInUse([1, 2, 3])));
	}

	public function testPlaylistsWhichAreNotInUseAreNotListed(): void
	{
		static::assertSame([], $this->service->getPlaylistsInUse([1, 99]));
	}

	public function testWithoutPlaylistsNothingIsInUse(): void
	{
		static::assertSame([], $this->service->getPlaylistsInUse([]));
	}

	/**
	 * @param array<int,bool> $inUse
	 * @return array<int,bool>
	 */
	private function sorted(array $inUse): array
	{
		ksort($inUse);

		return $inUse;
	}
}
