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

use App\Framework\Config\Config;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Playlists\Services\PlaylistFiles;
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

	public function testAChangeOfTheSettingsStartsTheReleaseAgain(): void
	{
		$this->actingAs(2);
		$this->connection->update('playlists', ['release_status' => 'released'], ['playlist_id' => 1]);

		$this->playlistsService->updateSecure(['playlist_id' => 1, 'playlist_name' => 'Umbenannt']);

		static::assertSame('in_progress', $this->playlist(1)['release_status']);
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

	/**
	 * A zone without playlist is skipped, the zones after it are checked all the same.
	 */
	public function testEveryZoneAfterAnEmptyOneIsChecked(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->saveZones(1, ['resolution' => '1920x1080', 'zones' => [['zone_playlist_id' => 0], ['zone_playlist_id' => 2]]]);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertNull($this->playlist(1)['layout']);
		}
	}

	/**
	 * The ids of the form are text.
	 */
	public function testAPlaylistCanNotBeAZoneOfItselfAlsoWithTheIdAsText(): void
	{
		$this->actingAs(2);

		try
		{
			$this->playlistsService->saveZones(1, ['resolution' => '1920x1080', 'zones' => [['zone_playlist_id' => '3'], ['zone_playlist_id' => '1']]]);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('invalid_zone_data', $e->getTranslationKey());
			static::assertNull($this->playlist(1)['layout']);
		}
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
	 * Item 5 is the template item of the playlist 1, item 9 one which only a version still has.
	 * The files of the item 55 are not the ones of the item 5.
	 */
	public function testDeleteRemovesTheImagesOfTheTemplateItemsOfThePlaylistAndItsVersions(): void
	{
		$this->actingAs(2);
		$this->connection->insert('unit_versions', [
			'module' => 'playlists', 'unit_id' => '1', 'version' => 1, 'UID' => 2, 'created_at' => '2026-11-05 10:00:00',
			'snapshot' => (string) json_encode(['playlist' => [], 'items' => [['item_id' => 9, 'item_type' => 'template'], ['item_id' => 1, 'item_type' => 'mediapool']]])
		]);
		$originals = $this->storeImages('originals', ['5.png', '5-0123456789abcdef0123456789abcdef.png', '9-fedcba9876543210fedcba9876543210.jpg', '55-0123456789abcdef0123456789abcdef.png', '1-0123456789abcdef0123456789abcdef.png']);
		$thumbnails = $this->storeImages('thumbnails', ['5-0123456789abcdef0123456789abcdef.jpg']);

		static::assertSame(1, $this->playlistsService->delete(1));

		static::assertEqualsCanonicalizing(['55-0123456789abcdef0123456789abcdef.png', '1-0123456789abcdef0123456789abcdef.png'], $this->files($originals));
		static::assertSame([], $this->files($thumbnails));
	}

	public function testDeleteRemovesTheSmilFilesOfThePlaylistOnly(): void
	{
		$this->actingAs(2);
		$smil = $this->storeSmil([1, 3]);

		static::assertSame(1, $this->playlistsService->delete(1));

		static::assertFalse($this->connection->isTransactionActive());

		static::assertDirectoryDoesNotExist($smil . '/1');
		static::assertFileExists($smil . '/3/items.smil');
	}

	public function testTheFilesStayWhenThePlaylistMayNotBeDeleted(): void
	{
		$this->actingAs(3);
		$originals = $this->storeImages('originals', ['5-0123456789abcdef0123456789abcdef.png']);
		$smil      = $this->storeSmil([1]);

		try
		{
			$this->playlistsService->delete(1);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(['5-0123456789abcdef0123456789abcdef.png'], $this->files($originals));
			static::assertFileExists($smil . '/1/items.smil');
		}
	}

	public function testTheFilesWhichCanNotBeRemovedAreNamed(): void
	{
		$originals = $this->storeImages('originals', ['5-0123456789abcdef0123456789abcdef.png']);
		$this->storeImages('thumbnails', []);
		// a directory can not be unlinked
		mkdir($originals . '/5-a.png');
		mkdir($originals . '/5-b.png');

		try
		{
			$failed = $this->container->get(PlaylistFiles::class)->remove(3, [5]);
			$left   = $this->files($originals);
		}
		finally
		{
			rmdir($originals . '/5-a.png');
			rmdir($originals . '/5-b.png');
		}

		static::assertSame([$originals . '/5-a.png', $originals . '/5-b.png'], $failed);
		static::assertSame(['5-a.png', '5-b.png'], $left);
	}

	/**
	 * @param list<int> $playlistIds
	 */
	private function storeSmil(array $playlistIds): string
	{
		$directory = self::runtimeDir() . '/system/' . $this->container->get(Config::class)->getConfigValue('path_playlists', 'playlists');
		foreach ($playlistIds as $playlistId)
		{
			if (!is_dir($directory . '/' . $playlistId))
				mkdir($directory . '/' . $playlistId, 0775, true);
			file_put_contents($directory . '/' . $playlistId . '/items.smil', '<seq/>');
		}

		return $directory;
	}

	/**
	 * In the system directory of the PlaylistFiles of the tests, see TestContainerFactory.
	 *
	 * @param list<string> $names
	 */
	private function storeImages(string $key, array $names): string
	{
		$directory = self::runtimeDir() . '/system/' . $this->container->get(Config::class)->getConfigValue($key, 'playlists', 'directories');
		if (!is_dir($directory))
			mkdir($directory, 0775, true);
		foreach (glob($directory . '/*') ?: [] as $file)
			unlink($file);
		foreach ($names as $name)
			file_put_contents($directory . '/' . $name, 'image');

		return $directory;
	}

	/**
	 * @return list<string>
	 */
	private function files(string $directory): array
	{
		return array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
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
