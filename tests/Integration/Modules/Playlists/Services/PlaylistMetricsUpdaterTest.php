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

use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistMetricsUpdater;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\RecordingLogger;

/**
 * After an export the playlists which contain the exported one need new metrics, on every level.
 * Fixtures: the playlist 3 (empty) is nested in 2 (items 6 and 7: 11 seconds, 20 bytes),
 * 2 is nested in 1 (items 1 to 5 where item 3 stands for 2: 68 seconds, 1170 bytes).
 */
#[Group('acl')]
class PlaylistMetricsUpdaterTest extends ContainerTestCase
{
	private RecordingLogger $logger;
	private PlaylistMetricsUpdater $updater;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->actingAs(1);

		$this->logger = new RecordingLogger();

		$this->updater = new PlaylistMetricsUpdater(
			$this->container->get(ItemsRepository::class),
			$this->container->get(PlaylistsService::class),
			$this->container->get(PlaylistMetricsCalculator::class),
			$this->logger
		);
	}

	public function testTheItemsWhichStandForAPlaylistGetItsMetrics(): void
	{
		$this->updater->updateItemsMetrics(2, new PlaylistMetrics(fileSize: 20, duration: 11));

		static::assertSame(11, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 3'));
		static::assertSame(20, (int) $this->connection->fetchOne('SELECT item_filesize FROM playlists_items WHERE item_id = 3'));
		// the items of other playlists keep theirs
		static::assertSame(10, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 1'));
	}

	public function testEveryLevelAboveGetsNewMetrics(): void
	{
		$this->updater->updateMetricsRecursively(3);

		// playlist 2 knows 3 as an empty item of 3 seconds, which is not changed by the export of 3
		static::assertSame([11, 20, 11], $this->playlistMetrics(2));
		// item 3 stands for playlist 2 and has its 11 seconds now instead of 5: 10 + 30 + 11 + 15 + 8
		static::assertSame([74, 1190, 63], $this->playlistMetrics(1));
		static::assertSame(11, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 3'));
		static::assertSame([], $this->logger->messages);
	}

	public function testAPlaylistWithoutContainerChangesNothing(): void
	{
		$this->updater->updateMetricsRecursively(1);

		static::assertSame([0, 0, 0], $this->playlistMetrics(1));
		// the fixture playlist 2 has an owner duration of 120
		static::assertSame([0, 0, 120], $this->playlistMetrics(2));
		static::assertSame([], $this->logger->messages);
	}

	/**
	 * Playlist 4 contains 1 and 2. 1 contains 2 as well, so 4 needs the new metrics of 1 and of 2.
	 * It is calculated once, after both of them.
	 */
	public function testAPlaylistOnSeveralPathsIsCalculatedAfterAllOfThem(): void
	{
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (4, 2, 'master', 'Beides')");
		$this->connection->executeStatement(
			"INSERT INTO playlists_items (item_id, playlist_id, UID, item_order, item_type, file_resource, item_name) VALUES
			(8, 4, 2, 1, 'playlist', '1', 'Lobby'), (9, 4, 2, 2, 'playlist', '2', 'Kantine')"
		);

		$this->updater->updateMetricsRecursively(3);

		// item 8 has the 74 seconds of playlist 1, item 9 the 11 of playlist 2
		static::assertSame([85, 1210, 85], $this->playlistMetrics(4));
		static::assertSame([], $this->logger->messages);
	}

	/**
	 * Playlist 3 is nested in 2 and 2 is nested in 3: the export of 3 must end and not run endlessly.
	 */
	public function testANestingCycleIsLoggedAndEnds(): void
	{
		$this->connection->executeStatement("INSERT INTO playlists_items (item_id, playlist_id, UID, item_order, item_type, file_resource, item_name) VALUES (8, 3, 2, 1, 'playlist', '2', 'Kantine')");

		$this->updater->updateMetricsRecursively(3);

		static::assertCount(1, $this->logger->messages['warning']);
		static::assertStringStartsWith('Metrics not updated for playlists in a nesting cycle: ', $this->logger->messages['warning'][0]);
	}

	/**
	 * @return array{0:int, 1:int, 2:int} duration, filesize and owner duration of the playlist
	 */
	private function playlistMetrics(int $playlistId): array
	{
		$row = $this->connection->fetchNumeric('SELECT duration, filesize, owner_duration FROM playlists WHERE playlist_id = ?', [$playlistId]);
		static::assertIsArray($row);

		return [(int) $row[0], (int) $row[1], (int) $row[2]];
	}
}
