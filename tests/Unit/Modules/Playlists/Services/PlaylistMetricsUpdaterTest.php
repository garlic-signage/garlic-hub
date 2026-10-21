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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistMetricsUpdater;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaylistMetricsUpdaterTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private LoggerInterface&MockObject $loggerMock;
	private PlaylistMetricsUpdater $updater;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock           = $this->createMock(ItemsRepository::class);
		$this->playlistsServiceMock          = $this->createMock(PlaylistsService::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);
		$this->loggerMock                    = $this->createMock(LoggerInterface::class);

		$this->updater = new PlaylistMetricsUpdater(
			$this->itemsRepositoryMock,
			$this->playlistsServiceMock,
			$this->playlistMetricsCalculatorMock,
			$this->loggerMock
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateItemsMetricsSuccessfully(): void
	{
		$this->expectUntouched($this->playlistsServiceMock, $this->loggerMock, $this->playlistMetricsCalculatorMock);
		$playlistId = 123;

		$saveItem = [
			'item_duration' => 300,
			'item_filesize' => 1024
		];

		$this->itemsRepositoryMock->expects($this->once())
			->method('updateWithWhere')
			->with($saveItem, ['file_resource' => $playlistId]);

		$this->updater->updateItemsMetrics($playlistId, new PlaylistMetrics(fileSize: 1024, duration: 300));
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateMetricsRecursivelySuccessfully(): void
	{
		$this->expectUntouched($this->loggerMock);
		$playlistId = 2;
		$playlistsContaining = [['playlist_id' => 3]];

		$this->itemsRepositoryMock->expects($this->exactly(2))
			->method('findAllPlaylistsContainingPlaylist')
			->willReturnMap([
				[2, $playlistsContaining],
				[3, []]
			]);

		$this->playlistMetricsCalculatorMock->expects($this->once())
			->method('calculateFromPlaylistData')
			->willReturn(new PlaylistMetrics());

		$this->itemsRepositoryMock->expects($this->once())
			->method('updateWithWhere');

		$this->playlistsServiceMock->expects($this->once())
			->method('update');

		$this->updater->updateMetricsRecursively($playlistId);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateMetricsRecursivelyWithNoContainingPlaylists(): void
	{
		$this->expectUntouched($this->loggerMock);
		$playlistId = 1;

		$this->itemsRepositoryMock->expects($this->once())
			->method('findAllPlaylistsContainingPlaylist')
			->with($playlistId)
			->willReturn([]);

		$this->playlistMetricsCalculatorMock->expects($this->never())->method('calculateFromPlaylistData');
		$this->itemsRepositoryMock->expects($this->never())->method('updateWithWhere');
		$this->playlistsServiceMock->expects($this->never())->method('update');

		$this->updater->updateMetricsRecursively($playlistId);
	}

	/**
	 * 1 is nested in 2 and 3, both are nested in 4.
	 * 4 must be calculated once and only after 2 and 3.
	 *
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateMetricsRecursivelyCalculatesSharedParentOnceAfterAllChildren(): void
	{
		$this->expectUntouched($this->loggerMock);

		$this->itemsRepositoryMock->expects($this->exactly(4))
			->method('findAllPlaylistsContainingPlaylist')
			->willReturnMap([
				[1, [['playlist_id' => 2], ['playlist_id' => 3]]],
				[2, [['playlist_id' => 4]]],
				[3, [['playlist_id' => 4]]],
				[4, []]
			]);

		$calculated = [];
		$this->playlistMetricsCalculatorMock->expects($this->exactly(3))
			->method('calculateFromPlaylistData')
			->willReturnCallback(function (array $playlist) use (&$calculated)
			{
				$calculated[] = $playlist['playlist_id'];
				return new PlaylistMetrics();
			});

		$this->itemsRepositoryMock->expects($this->exactly(3))->method('updateWithWhere');
		$this->playlistsServiceMock->expects($this->exactly(3))->method('update');

		$this->updater->updateMetricsRecursively(1);

		$this->assertSame([2, 3, 4], $calculated);
	}

	/**
	 * 1 is nested in 2, and 2 is nested in 1.
	 *
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateMetricsRecursivelyStopsAtCycleBackToExportedPlaylist(): void
	{
		
		$this->itemsRepositoryMock->expects($this->exactly(2))
			->method('findAllPlaylistsContainingPlaylist')
			->willReturnMap([
				[1, [['playlist_id' => 2]]],
				[2, [['playlist_id' => 1]]]
			]);

		$this->playlistMetricsCalculatorMock->expects($this->once())
			->method('calculateFromPlaylistData')
			->with(['playlist_id' => 2])
			->willReturn(new PlaylistMetrics());
		$this->playlistsServiceMock->expects($this->once())->method('update');
		$this->loggerMock->expects($this->once())->method('warning')
			->with('Metrics not updated for playlists in a nesting cycle: 1');

		$this->updater->updateMetricsRecursively(1);
	}

	/**
	 * 1 is nested in 2, 2 and 3 are nested in each other.
	 *
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateMetricsRecursivelySkipsCycleAboveExportedPlaylist(): void
	{
		
		$this->itemsRepositoryMock->expects($this->exactly(3))
			->method('findAllPlaylistsContainingPlaylist')
			->willReturnMap([
				[1, [['playlist_id' => 2]]],
				[2, [['playlist_id' => 3]]],
				[3, [['playlist_id' => 2]]]
			]);

		$this->playlistMetricsCalculatorMock->expects($this->never())->method('calculateFromPlaylistData');
		$this->playlistsServiceMock->expects($this->never())->method('update');
		$this->loggerMock->expects($this->once())->method('warning')
			->with('Metrics not updated for playlists in a nesting cycle: 2, 3');

		$this->updater->updateMetricsRecursively(1);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
