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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaylistMetricsCalculatorTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Config&MockObject $configMock;
	private PlaylistMetricsCalculator $calculator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock = $this->createMock(ItemsRepository::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->configMock = $this->createMock(Config::class);

		$this->calculator = new PlaylistMetricsCalculator($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCalculateFromItemsProcessesMetrics(): void
	{
		$playlist = ['UID' => 1, 'shuffle' => 0, 'time_limit' => 0];
		$items = [
			['item_filesize' => 100, 'item_duration' => 30, 'UID' => 1],
			['item_filesize' => 200, 'item_duration' => 40, 'UID' => 2]
		];

		$this->calculator->calculateFromItems($playlist, $items);

		$this->assertSame(2, $this->calculator->getCountEntries());
		$this->assertSame(300, $this->calculator->getFileSize());
		$this->assertSame(70, $this->calculator->getDuration());
		$this->assertSame(30, $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 2,
			'count_owner_items' => 1,
			'filesize'          => 300,
			'duration'          => 70,
			'owner_duration'    => 30
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();

		$this->assertSame($expectedMetrics, $frontendMetrics);

	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle(): void
	{
		$playlist = ['UID' => 1, 'time_limit' => 0,  'shuffle' => 1, 'shuffle_picking' => 1];
		$items = [
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
		];

		$this->calculator->calculateFromItems($playlist, $items);

		$this->assertSame(3, $this->calculator->getCountEntries());
		$this->assertLessThan(150, $this->calculator->getDuration());
		$this->assertLessThanOrEqual($this->calculator->getDuration(), $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 3,
			'count_owner_items' => 3,
			'filesize'          => 450,
			'duration'          => 50,
			'owner_duration'    => 50
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		$this->assertSame($expectedMetrics, $frontendMetrics);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle2(): void
	{
		$playlist = ['UID' => 1, 'time_limit' => 0,  'shuffle' => 1, 'shuffle_picking' => 6];
		$items = [
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
		];

		$this->calculator->calculateFromItems($playlist, $items);


		$expectedMetrics = [
			'count_items'       => 3,
			'count_owner_items' => 3,
			'filesize'          => 450,
			'duration'          => 150,
			'owner_duration'    => 150
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		$this->assertSame($expectedMetrics, $frontendMetrics);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle3(): void
	{
		$playlist = ['UID' => 1, 'time_limit' => 0,  'shuffle' => 1, 'shuffle_picking' => 1];
		$items = [
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
		];

		$this->calculator->calculateFromItems($playlist, $items);

		$this->assertSame(3, $this->calculator->getCountEntries());
		$this->assertLessThan(150, $this->calculator->getDuration());
		$this->assertLessThanOrEqual($this->calculator->getDuration(), $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 3,
			'count_owner_items' => 0,
			'filesize'          => 450,
			'duration'          => 50,
			'owner_duration'    => 0
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		$this->assertSame($expectedMetrics, $frontendMetrics);
	}


	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsThrowsExceptionWhenTimeLimitExceeded(): void
	{
		$playlist = ['UID' => 1, 'name' => 'Test Playlist', 'time_limit' => 60, 'shuffle' => 0];
		$items = [
			['item_filesize' => 100, 'item_duration' => 70, 'UID' => 1]
		];
		$this->calculator->setUID(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Exceeds time limit 60s of playlist: Test Playlist');
		$this->calculator->calculateFromItems($playlist, $items);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCalculateFromPlaylistData(): void
	{
		$playlist = ['playlist_id' => 5, 'UID' => 1, 'shuffle' => 0, 'time_limit' => 0];
		$expectedMetrics = [
			'count_items' => 3,
			'count_owner_items' => 2,
			'filesize' => 450,
			'duration' => 120,
			'owner_duration' => 80
		];

		$this->itemsRepositoryMock->method('sumAndCountMetricsByPlaylistIdAndOwner')
			->with(5, 1)
			->willReturn($expectedMetrics);

		$this->calculator->calculateFromPlaylistData($playlist);
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		$this->assertSame($expectedMetrics, $frontendMetrics);

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCalculateFromPlaylistDataHandlesEmptyPlaylist(): void
	{
		$playlist = [];

		// Ensure that calculateFromPlaylistData will reset metrics for an empty playlist
		$this->calculator->calculateFromPlaylistData($playlist);

		$expectedMetrics = [
			'count_items' => 0,
			'count_owner_items' => 0,
			'filesize' => 0,
			'duration' => 0,
			'owner_duration' => 0
		];

		$this->assertSame($expectedMetrics, $this->calculator->getMetricsForFrontend());

		$expectedMetrics2 = [
			'filesize' => 0,
			'duration' => 0,
			'owner_duration' => 0
		];
		$this->assertSame($expectedMetrics2, $this->calculator->getMetricsForPlaylistTable());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationWithoutTimeLimit(): void
	{
		$playlist = ['UID' => 1, 'time_limit' => 0];
		$media = ['metadata' => ['duration' => 50]];

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);

		$this->assertSame(50, $result);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationExceedsTimeLimitPartially(): void
	{
		$playlist = ['playlist_id' => 5, 'UID' => 1, 'shuffle' => 0, 'time_limit' => 100];
		$media    = ['metadata' => ['duration' => 60]];
		$expectedMetrics = [
			'count_items' => 2,
			'count_owner_items' => 2,
			'filesize' => 450,
			'duration' => 100,
			'owner_duration' => 80
		];
		$this->itemsRepositoryMock->method('sumAndCountMetricsByPlaylistIdAndOwner')
			->with(5, 1)
			->willReturn($expectedMetrics);

		$this->calculator->setUID(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);

		$this->assertSame(20, $result); // Expected remaining time
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationExceedsTimeLimitFull(): void
	{
		$playlist = ['playlist_id' => 5, 'UID' => 1, 'shuffle' => 0, 'time_limit' => 100];
		$media    = ['metadata' => ['duration' => 60]];
		$expectedMetrics = [
			'count_items' => 2,
			'count_owner_items' => 2,
			'filesize' => 450,
			'duration' => 100,
			'owner_duration' => 100
		];
		$this->itemsRepositoryMock->method('sumAndCountMetricsByPlaylistIdAndOwner')
			->with(5, 1)
			->willReturn($expectedMetrics);

		$this->calculator->setUID(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);

		$this->assertSame(0, $result); // Expected remaining time
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationHandlesDefaultDuration(): void
	{
		$playlist = ['UID' => 1, 'time_limit' => 0];
		$media = ['metadata' => []]; // No duration metadata


		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('duration', 'playlists', 'Defaults')
			->willReturn(15);

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);
		$this->assertSame(15, $result);
	}
}
