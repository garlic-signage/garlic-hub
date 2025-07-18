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

namespace Tests\Unit\Modules\Playlists\Services\InsertItems;

use App\Framework\Exceptions\FrameworkException;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\InsertItems\Media;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\WidgetsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaTest extends TestCase
{
	private MediaService&MockObject $mediaServiceMock;
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private WidgetsService&MockObject $widgetsServiceMock;
	private LoggerInterface&MockObject $loggerMock;
	private Media $media;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaServiceMock = $this->createMock(MediaService::class);
		$this->itemsRepositoryMock = $this->createMock(ItemsRepository::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);
		$this->widgetsServiceMock = $this->createMock(WidgetsService::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->media = new Media(
			$this->itemsRepositoryMock,
			$this->mediaServiceMock,
			$this->playlistsServiceMock,
			$this->playlistMetricsCalculatorMock,
			$this->widgetsServiceMock,
			$this->loggerMock
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInsertSuccess(): void
	{
		$this->checkAclMockSuccessful();

		$this->mediaServiceMock->expects($this->once())->method('fetchMedia')
			->with('mediaId')
			->willReturn(['metadata' => ['size' => 1024], 'filename' => 'test_file.mp4', 'checksum' => 'abc123', 'mimetype' => 'video/mp4', 'paths' => ['/path']]);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingMediaDuration')
			->willReturn(5000);

		$this->itemsRepositoryMock->expects($this->once())->method('updatePositionsWhenInserted')
			->with(1, 2);

		$this->itemsRepositoryMock->expects($this->once())->method('insert')
			->willReturn(123);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->willReturnSelf();

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getMetricsForFrontend')
			->willReturn(['frontend_metrics']);

		$this->itemsRepositoryMock->expects($this->once())->method('commitTransaction');

		$result = $this->media->insert(1, 'mediaId', 2);

		static::assertNotEmpty($result);
		static::assertArrayHasKey('playlist_metrics', $result);
		static::assertArrayHasKey('item', $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testInsertSuccessWithWidget(): void
	{
		$this->checkAclMockSuccessful();

		$this->mediaServiceMock->expects($this->once())->method('fetchMedia')
			->with('mediaId')
			->willReturn(['metadata' => ['size' => 1024], 'filename' => 'test_file.mp4', 'checksum' => 'abc123', 'mimetype' => 'application/widget', 'config_data' => 'some config xml', 'paths' => ['/path']]);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingMediaDuration')
			->willReturn(5000);
		$this->widgetsServiceMock->expects($this->once())->method('prepareContentData')
			->with('some config xml', [], true);

		$this->itemsRepositoryMock->expects($this->once())->method('updatePositionsWhenInserted')
			->with(1, 2);

		$this->itemsRepositoryMock->expects($this->once())->method('insert')
			->willReturn(123);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->willReturnSelf();

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getMetricsForFrontend')
			->willReturn(['frontend_metrics']);

		$this->itemsRepositoryMock->expects($this->once())->method('commitTransaction');

		$result = $this->media->insert(1, 'mediaId', 2);

		static::assertNotEmpty($result);
		static::assertArrayHasKey('playlist_metrics', $result);
		static::assertArrayHasKey('item', $result);
	}

	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertPlaylistNotAccessible(): void
	{
		$this->media->setUID(1);
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn([]);

		$this->mediaServiceMock->expects($this->never())->method('fetchMedia');

		$this->itemsRepositoryMock->expects($this->once())->method('rollBackTransaction');

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error insert media: Playlist is not accessible'));

		$result = $this->media->insert(1, 'invalidMediaId', 2);

		static::assertEmpty($result);
	}


	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertMediaNotAccessible(): void
	{
		$this->checkAclMockSuccessful();

		$this->mediaServiceMock->expects($this->once())->method('fetchMedia')
			->with('invalidMediaId')
			->willReturn([]);

		$this->itemsRepositoryMock->expects($this->once())->method('rollBackTransaction');

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error insert media: Media is not accessible'));

		$result = $this->media->insert(1, 'invalidMediaId', 2);

		static::assertEmpty($result);
	}

	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertItemInsertFails(): void
	{
		$this->checkAclMockSuccessful();

		$this->mediaServiceMock->expects($this->once())->method('fetchMedia')
			->with('mediaId')
			->willReturn(['metadata' => ['size' => 1024], 'filename' => 'test_file.mp4', 'checksum' => 'abc123', 'mimetype' => 'video/mp4', 'paths' => ['/path']]);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingMediaDuration')
			->willReturn(5000);

		$this->itemsRepositoryMock->expects($this->once())->method('insert')
			->willReturn(0);

		$this->itemsRepositoryMock->expects($this->once())
			->method('rollBackTransaction');

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error insert media: Playlist item could not be inserted.'));

		$result = $this->media->insert(1, 'mediaId', 2);

		static::assertEmpty($result);
	}

	private function checkAclMockSuccessful(): void
	{
		$this->media->setUID(1);
		$this->itemsRepositoryMock->expects($this->once())->method('beginTransaction');
		$this->mediaServiceMock->expects($this->once())->method('setUID')
			->with(1);
		$this->playlistsServiceMock->method('setUID')->with(1);
		$this->playlistsServiceMock->method('loadPlaylistForEdit')->willReturn(['some_stuff']);
	}
}
