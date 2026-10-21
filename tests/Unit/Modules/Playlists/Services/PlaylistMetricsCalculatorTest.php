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
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PlaylistMetricsCalculatorTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Config&MockObject $configMock;
	private UserSession&Stub $userSessionStub;
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
		$this->userSessionStub = $this->createStub(UserSession::class);

		$this->calculator = new PlaylistMetricsCalculator($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock, $this->userSessionStub);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateFromItemsProcessesMetrics(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
		$playlist = ['UID' => 1, 'shuffle' => 0, 'time_limit' => 0];
		$items = [
			['item_filesize' => 100, 'item_duration' => 30, 'UID' => 1],
			['item_filesize' => 200, 'item_duration' => 40, 'UID' => 2]
		];

		$this->calculator->calculateFromItems($playlist, $items);

		static::assertSame(2, $this->calculator->getCountEntries());
		static::assertSame(300, $this->calculator->getFileSize());
		static::assertSame(70, $this->calculator->getDuration());
		static::assertSame(30, $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 2,
			'count_owner_items' => 1,
			'filesize'          => 300,
			'duration'          => 70,
			'owner_duration'    => 30
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();

		static::assertSame($expectedMetrics, $frontendMetrics);

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
		$playlist = ['UID' => 1, 'time_limit' => 0,  'shuffle' => 1, 'shuffle_picking' => 1];
		$items = [
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 1],
		];

		$this->calculator->calculateFromItems($playlist, $items);

		static::assertSame(3, $this->calculator->getCountEntries());
		static::assertLessThan(150, $this->calculator->getDuration());
		static::assertLessThanOrEqual($this->calculator->getDuration(), $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 3,
			'count_owner_items' => 3,
			'filesize'          => 450,
			'duration'          => 50,
			'owner_duration'    => 50
		];
		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		static::assertSame($expectedMetrics, $frontendMetrics);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle2(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
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
		static::assertSame($expectedMetrics, $frontendMetrics);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsAdjustsMetricsWithShuffle3(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
		$playlist = ['UID' => 1, 'time_limit' => 0,  'shuffle' => 1, 'shuffle_picking' => 1];
		$items = [
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
			['item_filesize' => 150, 'item_duration' => 50, 'UID' => 2],
		];

		$this->calculator->calculateFromItems($playlist, $items);

		static::assertSame(3, $this->calculator->getCountEntries());
		static::assertLessThan(150, $this->calculator->getDuration());
		static::assertLessThanOrEqual($this->calculator->getDuration(), $this->calculator->getOwnerDuration());

		$expectedMetrics = [
			'count_items'       => 3,
			'count_owner_items' => 0,
			'filesize'          => 450,
			'duration'          => 50,
			'owner_duration'    => 0
		];

		$frontendMetrics =  $this->calculator->getMetricsForFrontend();
		static::assertSame($expectedMetrics, $frontendMetrics);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateFromItemsThrowsExceptionWhenTimeLimitExceeded(): void
	{
		$this->itemsRepositoryMock->expects($this->never())->method($this->anything());
		$this->configMock->expects($this->never())->method($this->anything());
		$playlist = ['UID' => 1, 'name' => 'Test Playlist', 'time_limit' => 60, 'shuffle' => 0];
		$items = [
			['item_filesize' => 100, 'item_duration' => 70, 'UID' => 1]
		];
		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Exceeds time limit 60s of playlist: Test Playlist');
		$this->calculator->calculateFromItems($playlist, $items);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateFromPlaylistData(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->configMock->expects($this->never())->method($this->anything());
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
		static::assertSame($expectedMetrics, $frontendMetrics);

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateFromPlaylistDataHandlesEmptyPlaylist(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
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

		static::assertSame($expectedMetrics, $this->calculator->getMetricsForFrontend());

		$expectedMetrics2 = [
			'filesize' => 0,
			'duration' => 0,
			'owner_duration' => 0
		];
		static::assertSame($expectedMetrics2, $this->calculator->getMetricsForPlaylistTable());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationWithoutTimeLimit(): void
	{
		$this->expectUntouched($this->itemsRepositoryMock, $this->aclValidatorMock, $this->configMock);
		$playlist = ['UID' => 1, 'time_limit' => 0];
		$media = ['metadata' => ['duration' => 50]];

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);

		static::assertSame(50, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationExceedsTimeLimitPartially(): void
	{
		$this->configMock->expects($this->never())->method($this->anything());
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

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);

		static::assertSame(20, $result); // Expected remaining time
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationExceedsTimeLimitFull(): void
	{
		$this->configMock->expects($this->never())->method($this->anything());
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

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('isSimpleAdmin')->with(1)->willReturn(false);

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);


		static::assertSame(0, $result); // Expected remaining time
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCalculateRemainingMediaDurationHandlesDefaultDuration(): void
	{
		$this->itemsRepositoryMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$playlist = ['UID' => 1, 'time_limit' => 0];
		$media = ['metadata' => []]; // No duration metadata


		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('duration', 'playlists', 'Defaults')
			->willReturn('15');

		$result = $this->calculator->calculateRemainingMediaDuration($playlist, $media);
		static::assertSame(15, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
