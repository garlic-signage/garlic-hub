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
 * What PlaylistsService does with the data of a playlist. Who may do it is in PlaylistAccessTest.
 * Fixtures: the playlist 1 of anna has the items 1 to 5 (5 items, 4 of anna, 68 seconds,
 * 63 of them anna's, 1170 bytes), the playlist 2 belongs to bernd.
 */
#[Group('acl')]
class PlaylistsServiceTest extends ContainerTestCase
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

	public function testCreateNewBelongsToTheLoggedInUser(): void
	{
		$this->actingAs(2);

		$id = $this->playlistsService->createNew(['playlist_mode' => 'master', 'playlist_name' => 'Neu', 'time_limit' => 60]);

		$row = $this->playlist($id);
		static::assertSame(2, (int) $row['UID']);
		static::assertSame('master', $row['playlist_mode']);
		static::assertSame('Neu', $row['playlist_name']);
		static::assertSame(60, (int) $row['time_limit']);
	}

	public function testCreateNewTakesTheGivenOwner(): void
	{
		$this->actingAs(1);

		$id = $this->playlistsService->createNew(['UID' => 3, 'playlist_mode' => 'internal', 'playlist_name' => 'Für Bernd']);

		static::assertSame(3, (int) $this->playlist($id)['UID']);
	}

	public function testCreateNewStoresOnlyKnownFields(): void
	{
		$this->actingAs(2);

		$id = $this->playlistsService->createNew(['playlist_mode' => 'master', 'playlist_name' => 'Neu', 'duration' => 999, 'filesize' => 999]);

		static::assertSame(0, (int) $this->playlist($id)['duration']);
		static::assertSame(0, (int) $this->playlist($id)['filesize']);
	}

	public function testToggleShuffleTurnsShuffleOnAndOff(): void
	{
		$this->actingAs(2);

		$result = $this->playlistsService->toggleShuffle(1);

		static::assertSame(1, $result['affected']);
		static::assertSame(1, (int) $this->playlist(1)['shuffle']);
		// picking everything: the shuffle does not change the sums
		static::assertSame(
			['count_items' => 5, 'count_owner_items' => 4, 'filesize' => 1170, 'duration' => 68, 'owner_duration' => 63],
			$result['playlist_metrics']
		);

		$this->playlistsService->toggleShuffle(1);

		static::assertSame(0, (int) $this->playlist(1)['shuffle']);
	}

	public function testShufflePickingReturnsTheAverageOfThePickedItems(): void
	{
		$this->actingAs(2);
		$this->playlistsService->toggleShuffle(1);

		$result = $this->playlistsService->shufflePicking(1, 2);

		static::assertSame(1, $result['affected']);
		static::assertSame(2, (int) $this->playlist(1)['shuffle_picking']);
		// 2 of 5 items: 68 / 5 * 2 = 27, anna owns 4 of 5 items: 15.75 * 1.6 = 25
		static::assertSame(27, $result['playlist_metrics']['duration']);
		static::assertSame(25, $result['playlist_metrics']['owner_duration']);
	}

	public function testShufflePickingOfAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->playlistsService->shufflePicking(999, 2);
	}

	public function testUpdateSecureChangesTheGivenFieldsOnly(): void
	{
		$this->actingAs(2);

		$affected = $this->playlistsService->updateSecure(['playlist_id' => 1, 'playlist_name' => 'Umbenannt', 'time_limit' => 30, 'duration' => 999]);

		static::assertSame(1, $affected);
		$row = $this->playlist(1);
		static::assertSame('Umbenannt', $row['playlist_name']);
		static::assertSame(30, (int) $row['time_limit']);
		static::assertSame(0, (int) $row['duration']);
		static::assertSame(2, (int) $row['UID']);
		static::assertSame('master', $row['playlist_mode']);
	}

	public function testUpdateSecureOfAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->playlistsService->updateSecure(['playlist_id' => 999, 'playlist_name' => 'x']);
	}

	public function testUpdateExportStoresTheMetricsAndTheExportTime(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->playlistsService->updateExport(1, ['duration' => 68, 'filesize' => 1170]));

		$row = $this->playlist(1);
		static::assertSame(68, (int) $row['duration']);
		static::assertSame(1170, (int) $row['filesize']);
		static::assertNotSame('0000-00-00 00:00:00', $row['export_time']);
	}

	public function testSaveZonesStoresTheLayoutForTheMultizoneAndTheEdit(): void
	{
		$this->actingAs(2);
		$zones = ['resolution' => '1920x1080', 'zones' => [['name' => 'main']]];

		static::assertSame(1, $this->playlistsService->saveZones(1, $zones));

		static::assertSame($zones, $this->playlistsService->loadPlaylistForMultizone(1));
		/** @var array<string,mixed> $playlist */
		$playlist = $this->playlistsService->loadPlaylistForEdit(1);
		static::assertSame($zones, $playlist['layout']);
		static::assertSame(1920, $playlist['screen_width']);
		static::assertSame(1080, $playlist['screen_height']);
		static::assertSame('anna', $playlist['username']);
	}

	public function testSaveZonesWithoutZonesKeepsTheLayout(): void
	{
		$this->actingAs(2);
		$zones = ['resolution' => '1920x1080'];
		$this->playlistsService->saveZones(1, $zones);

		static::assertSame(0, $this->playlistsService->saveZones(1, []));

		static::assertSame($zones, $this->playlistsService->loadPlaylistForMultizone(1));
	}

	public function testANewPlaylistHasNoLayout(): void
	{
		$this->actingAs(2);

		static::assertSame([], $this->playlistsService->loadPlaylistForMultizone(1));
		/** @var array<string,mixed> $playlist */
		$playlist = $this->playlistsService->loadPlaylistForEdit(1);
		static::assertSame([], $playlist['layout']);
		static::assertArrayNotHasKey('screen_width', $playlist);
	}

	public function testUserMayNotSaveZonesOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->saveZones(2, ['resolution' => '1x1']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertNull($this->playlist(2)['layout']);
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function playlist(int $playlistId): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM playlists WHERE playlist_id = ?', [$playlistId]);

		return $row !== false ? $row : static::fail('No row for ' . $playlistId);
	}
}
