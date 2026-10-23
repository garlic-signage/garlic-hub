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

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Helper\Compose\ItemThumbnailResolver;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ItemsServiceTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private Transactions&MockObject $transactionsMock;
	private MediaService&MockObject $mediaServiceMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private ItemThumbnailResolver&MockObject $itemThumbnailResolverMock;
	private LoggerInterface&MockObject $loggerMock;
	private ItemsService $itemsService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock = $this->createMock(ItemsRepository::class);
		$this->transactionsMock = $this->createMock(Transactions::class);
		$this->mediaServiceMock = $this->createMock(MediaService::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);
		$this->itemThumbnailResolverMock = $this->createMock(ItemThumbnailResolver::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->itemsService = new ItemsService(
			$this->itemsRepositoryMock,
			$this->transactionsMock,
			$this->mediaServiceMock,
			$this->playlistsServiceMock,
			$this->playlistMetricsCalculatorMock,
			$this->itemThumbnailResolverMock,
			$this->loggerMock
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindMediaInPlaylist(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->playlistsServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$playlistId = 1;
		$expected = [
			['item_id' => 1, 'item_name' => 'Item 1'],
			['item_id' => 2, 'item_name' => 'Item 2']
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findMediaInPlaylistId')
			->with($playlistId)
			->willReturn($expected);

		static::assertSame($expected, $this->itemsService->findMediaInPlaylist($playlistId));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdForValidMediapoolVideo(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 123;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 1,
			'item_type' => 'mediapool',
			'mimetype' => 'video/mp4',
			'file_resource' => 'checksum_value'
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($itemData['playlist_id']);

		$media = ['metadata' => ['duration' => 120], 'config_data' => ''];
		$this->mediaServiceMock->expects($this->once())->method('fetchMediaByChecksum')
			->with($itemData['file_resource'])
			->willReturn($media);

		$result = $this->itemsService->fetchItemById($itemId);

		static::assertArrayHasKey('default_duration', $result);
		static::assertEquals(120, $result['default_duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdForValidMediapoolImage(): void
	{
		$this->itemThumbnailResolverMock->expects($this->never())->method($this->anything());
		$this->transactionsMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 123;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 1,
			'item_type' => 'mediapool',
			'mimetype' => 'image/webp',
			'file_resource' => 'checksum_value',
			'config_data' => ''
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($itemData['playlist_id']);

		$this->mediaServiceMock->expects($this->once())->method('fetchMediaByChecksum')->willReturn(['config_data' => '']);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getDefaultDuration')
			->willReturn(17);

		$result = $this->itemsService->fetchItemById($itemId);

		static::assertArrayHasKey('default_duration', $result);
		static::assertEquals(17, $result['default_duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdWithUnknownItem(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')->with(123)->willReturn([]);
		$this->playlistsServiceMock->expects($this->never())->method('loadPureById');
		$this->mediaServiceMock->expects($this->never())->method('fetchMediaByChecksum');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('item_not_found');
		$this->itemsService->fetchItemById(123);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdThrowsWhenMediaNotFound(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 123;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 1,
			'item_type' => 'mediapool',
			'mimetype' => 'video/mp4',
			'file_resource' => 'checksum_value'
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($itemData['playlist_id']);

		$this->mediaServiceMock->expects($this->once())->method('fetchMediaByChecksum')
			->with($itemData['file_resource'])
			->willReturn([]);

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('item_not_found');
		$this->itemsService->fetchItemById($itemId);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdForValidPlaylistItem(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 456;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 4,
			'file_resource' => '7',
			'item_type' => 'playlist'
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$playlist = ['playlist_id' => 7, 'time_limit' => 150];
		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($itemData['playlist_id']);

		$this->playlistsServiceMock->expects($this->once())->method('fetchById')
			->with($itemData['file_resource'])
			->willReturn($playlist);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with($playlist)
			->willReturn(new PlaylistMetrics(duration: 150));

		$result = $this->itemsService->fetchItemById($itemId);

		static::assertArrayHasKey('default_duration', $result);
		static::assertEquals(150, $result['default_duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdForPlaylistItemWithTimeLimitAndBiggeDefualtDuration(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 456;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 4,
			'file_resource' => '7',
			'item_type' => 'playlist'
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$playlist = ['playlist_id' => 7, 'time_limit' => 150];
		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($itemData['playlist_id']);

		$this->playlistsServiceMock->expects($this->once())->method('fetchById')
			->with($itemData['file_resource'])
			->willReturn($playlist);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with($playlist)
			->willReturn(new PlaylistMetrics(duration: 200));

		$result = $this->itemsService->fetchItemById($itemId);

		static::assertArrayHasKey('default_duration', $result);
		static::assertEquals(150, $result['default_duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchItemByIdDefaultDuration(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 101;
		$itemData = [
			'item_id' => $itemId,
			'playlist_id' => 4,
			'item_type' => 'template'
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$destinationPlaylist = ['playlist_name' => 'Test Playlist'];
		$this->playlistsServiceMock->expects($this->once())
			->method('loadPureById')
			->with($itemData['playlist_id'])

			->willReturn($destinationPlaylist);
		;

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getDefaultDuration')
			->willReturn(100);

		$result = $this->itemsService->fetchItemById($itemId);

		static::assertArrayHasKey('default_duration', $result);
		static::assertEquals(100, $result['default_duration']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadItemsByPlaylistIdForComposerAddsThumbnails(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->loggerMock);
		$playlistId = 1;
		$playlistData = ['playlist_id' => $playlistId];
		$itemsData = [
			['item_id' => 1, 'item_type' => ItemType::MEDIAPOOL->value],
			['item_id' => 2, 'item_type' => ItemType::PLAYLIST->value]
		];

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);
		$this->itemsRepositoryMock->expects($this->once())->method('findAllByPlaylistId')
			->with($playlistId)
			->willReturn($itemsData);
		$this->itemThumbnailResolverMock->expects($this->exactly(2))->method('resolve')
			->willReturnMap([
				[$itemsData[0], ['path' => '/thumbs/a.jpg', 'url' => '/media.php/thumb/a.jpg']],
				[$itemsData[1], ['path' => 'public/images/icons/playlist.svg', 'url' => '/images/icons/playlist.svg']]
			]);
		$this->expectPlaylistMetrics($playlistData);

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer($playlistId);

		static::assertSame((new PlaylistMetrics(duration: 42))->toFrontendArray(), $result['playlist_metrics']);
		static::assertSame($playlistData, $result['playlist']);
		static::assertCount(2, $result['items']);
		static::assertSame('/thumbs/a.jpg', $result['items'][0]['paths']['thumbnail']);
		static::assertSame('/media.php/thumb/a.jpg', $result['items'][0]['urls']['thumbnail']);
		static::assertSame('public/images/icons/playlist.svg', $result['items'][1]['paths']['thumbnail']);
		static::assertSame('/images/icons/playlist.svg', $result['items'][1]['urls']['thumbnail']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadItemsByPlaylistIdForComposerSkipsItemsWithoutThumbnail(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->loggerMock);
		$playlistId = 1;
		$playlistData = ['playlist_id' => $playlistId];
		$itemsData = [['item_id' => 1, 'item_type' => 'unknown']];

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);
		$this->itemsRepositoryMock->expects($this->once())->method('findAllByPlaylistId')
			->with($playlistId)
			->willReturn($itemsData);
		$this->itemThumbnailResolverMock->expects($this->once())->method('resolve')
			->with($itemsData[0])
			->willReturn(null);
		$this->expectPlaylistMetrics($playlistData);

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer($playlistId);

		static::assertCount(0, $result['items']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadItemsByPlaylistIdForComposerWithEmptyPlaylist(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->itemThumbnailResolverMock, $this->mediaServiceMock, $this->loggerMock);
		$playlistId = 1;
		$playlistData = ['playlist_id' => $playlistId];

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);

		$this->itemsRepositoryMock->expects($this->once())->method('findAllByPlaylistId')
			->with($playlistId)
			->willReturn([]);

		$this->expectPlaylistMetrics($playlistData);

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer($playlistId);

		static::assertSame((new PlaylistMetrics(duration: 42))->toFrontendArray(), $result['playlist_metrics']);

		static::assertArrayHasKey('items', $result);
		static::assertCount(0, $result['items']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateFieldSuccess(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 1;
		$fieldName = 'name';
		$fieldValue = 'New Playlist Name';
		$playlistId = 123;

		$itemData = ['playlist_id' => $playlistId, 'item_type' => 'playlist'];
		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$playlistData = ['playlist_id' => $playlistId, 'time_limit' => 0];
		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->willReturn($playlistData);

		$saveData = ['name' => 'New Playlist Name'];
		$this->itemsRepositoryMock->expects($this->once())->method('update')
			->with($itemId, $saveData)
			->willReturn(1);

		$result = $this->itemsService->updateField($itemId, $fieldName, $fieldValue);

		static::assertEquals(1, $result);
	}

	/**
	 * Serialized widget data must reach the repository untouched. Stripping tags would
	 * break the length prefixes and the whole content_data would be lost.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateFieldKeepsTagsInSerializedValue(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId     = 1;
		$fieldValue = serialize(['text' => '<b>Tom & "Jerry"</b>']);

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn(['playlist_id' => 123, 'item_type' => 'widget']);
		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->willReturn(['playlist_id' => 123, 'time_limit' => 0]);

		$this->itemsRepositoryMock->expects($this->once())->method('update')
			->with($itemId, ['content_data' => $fieldValue])
			->willReturn(1);

		static::assertEquals(1, $this->itemsService->updateField($itemId, 'content_data', $fieldValue));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateFieldThrowsWhenItemNotFound(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 1;
		$fieldName = 'name';
		$fieldValue = 'New Playlist Name';

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn([]);

		$this->playlistsServiceMock->expects($this->never())->method('loadPureById');
		$this->itemsRepositoryMock->expects($this->never())->method('update');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('item_not_found');
		$this->itemsService->updateField($itemId, $fieldName, $fieldValue);
	}

	#[Group('units')]
	public function testUpdateItemDurationSuccess(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 5;
		$wantedDuration = 120;
		$playlistId = 7;

		$itemData = ['item_id' => $itemId, 'playlist_id' => $playlistId, 'item_type' => 'default'];
		$playlistData = ['playlist_id' => $playlistId];
		$calculatedDuration = 100;

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingDuration')
			->with($playlistData, $wantedDuration)
			->willReturn($calculatedDuration);

		$saveData = ['item_duration' => $calculatedDuration];
		$this->itemsRepositoryMock->expects($this->once())->method('update')
			->with($itemId, $saveData)
			->willReturn(1);

		static::assertSame($calculatedDuration, $this->itemsService->updateItemDuration($itemId, $wantedDuration));
	}

	#[Group('units')]
	public function testUpdateItemDurationReturnsNullWhenNotUpdated(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 5;
		$wantedDuration = 120;
		$playlistId = 7;

		$itemData = ['item_id' => $itemId, 'playlist_id' => $playlistId, 'item_type' => 'default'];
		$playlistData = ['playlist_id' => $playlistId];
		$calculatedDuration = 100;

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingDuration')
			->with($playlistData, $wantedDuration)
			->willReturn($calculatedDuration);

		$saveData = ['item_duration' => $calculatedDuration];
		$this->itemsRepositoryMock->expects($this->once())->method('update')
			->with($itemId, $saveData)
			->willReturn(0);

		static::assertNull($this->itemsService->updateItemDuration($itemId, $wantedDuration));
	}

	#[Group('units')]
	public function testUpdateItemDurationItemNotFound(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId = 5;
		$wantedDuration = 120;

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn([]);

		$this->playlistsServiceMock->expects($this->never())->method('loadPureById');

		$this->playlistMetricsCalculatorMock->expects($this->never())->method('calculateRemainingDuration');
		$this->itemsRepositoryMock->expects($this->never())->method('update');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('item_not_found');
		$this->itemsService->updateItemDuration($itemId, $wantedDuration);
	}

	#[Group('units')]
	public function testUpdateItemDurationForPlaylist(): void
	{
		$this->expectUntouched($this->transactionsMock, $this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$itemId           = 5;

		$wantedDuration   = 300;
		$playlistId       = 7;
		$nestedPlaylistId = 8;

		$itemData     = [
			'item_id' => $itemId, 'playlist_id' => $playlistId,
			'item_type' => ItemType::PLAYLIST->value, 'file_resource' => (string)$nestedPlaylistId
		];
		$playlistData            = ['playlist_id' => $playlistId];
		$nestedPlaylistData      = ['playlist_id' => $nestedPlaylistId];
		$remainingNestedDuration = 250;
		$calculatedDuration      = 200;

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstById')
			->with($itemId)
			->willReturn($itemData);

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId)
			->willReturn($playlistData);

		$this->playlistsServiceMock->expects($this->once())->method('fetchById')
			->with($nestedPlaylistId)
			->willReturn($nestedPlaylistData);

		$this->playlistMetricsCalculatorMock->expects($this->exactly(2))->method('calculateRemainingDuration')
			->willReturnMap([
				[$nestedPlaylistData, $wantedDuration, $remainingNestedDuration],
				[$playlistData, $remainingNestedDuration, $calculatedDuration]
			]);

		$saveData = ['item_duration' => $calculatedDuration];
		$this->itemsRepositoryMock->expects($this->once())->method('update')
			->with($itemId, $saveData)
			->willReturn(1);

		static::assertSame($calculatedDuration, $this->itemsService->updateItemDuration($itemId, $wantedDuration));
	}

	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateItemOrderSuccess(): void
	{
		$this->expectUntouched($this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$playlistId = 10;
		$itemsOrder = ['5' => '3', '3' => '1', '8' => '2']; // phpstan treats this as array<int,string>

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId);

		$this->itemsRepositoryMock->expects($this->exactly(3))->method('updateItemOrder')
			->willReturnMap([
				[3, 5, 1],
				[1, 3, 1],
				[2, 8, 1],
			]);
		$this->transactionsMock->expects($this->once())->method('commit');

		$this->itemsService->updateItemOrder($playlistId, $itemsOrder);
	}

	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateItemOrderThrowsWhenOrderNotUpdated(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->expectUntouched($this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock);
		$playlistId = 10;
		$itemsOrder = [];
		$itemsOrder['5'] = '3';  // Explizite String-Zuweisung
		$itemsOrder['3'] = '1';
		$itemsOrder['8'] = '2';

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->playlistsServiceMock->expects($this->once())->method('loadPureById')
			->with($playlistId);

		$this->itemsRepositoryMock->expects($this->once())->method('updateItemOrder')
			->with(3, 5)->willReturn(0);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('item_order_not_updated');
		$this->itemsService->updateItemOrder($playlistId, $itemsOrder);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteItemSuccessfully(): void
	{
		$this->expectUntouched($this->mediaServiceMock, $this->itemThumbnailResolverMock, $this->loggerMock);
		$playlistId = 10;
		$itemId = 20;

		$playlistData = ['playlist_id' => $playlistId];
		$itemData = ['item_id' => $itemId, 'item_order' => 1];

		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')
			->with($playlistId)
			->willReturn($playlistData);

		$this->transactionsMock->expects($this->once())
			->method('begin');

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['item_id' => $itemId])
			->willReturn($itemData);

		$this->itemsRepositoryMock->expects($this->once())->method('delete')
			->with($itemId)
			->willReturn(1);

		$this->itemsRepositoryMock->expects($this->once())->method('updatePositionsWhenDeleted')
			->with($playlistId, $itemData['item_order']);

		$metrics = new PlaylistMetrics(1, 1, 10, 42, 42);
		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with($playlistData)
			->willReturn($metrics);

		$this->transactionsMock->expects($this->once())->method('commit');

		$result = $this->itemsService->delete($playlistId, $itemId);

		static::assertArrayHasKey('playlist_metrics', $result);
		static::assertArrayHasKey('delete_id', $result);
		static::assertSame($metrics->toFrontendArray(), $result['playlist_metrics']);
		static::assertEquals(1, $result['delete_id']);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeletePlaylistNotFound(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->expectUntouched($this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock);
		$playlistId = 10;
		$itemId = 20;

		$this->transactionsMock->expects($this->once())
			->method('begin');

		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')
			->with($playlistId)
			->willThrowException(new NotFoundException('playlists', 'playlist_not_found'));

		$this->itemsRepositoryMock->expects($this->never())->method('findFirstBy');

		$this->transactionsMock->expects($this->once())
			->method('rollBack');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('playlist_not_found');
		$this->itemsService->delete($playlistId, $itemId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteItemNotFound(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->expectUntouched($this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock);
		$playlistId = 10;
		$itemId = 20;
		$playlistData = ['playlist_id' => $playlistId];

		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')
			->with($playlistId)
			->willReturn($playlistData);

		$this->transactionsMock->expects($this->once())
			->method('begin');

		$this->itemsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['item_id' => $itemId])
			->willReturn([]);

		$this->transactionsMock->expects($this->once())
			->method('rollBack');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('item_not_found');
		$this->itemsService->delete($playlistId, $itemId);
	}

	/**
	 * Test deletion fails if the item cannot be deleted.
	 *
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteItemFailure(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->expectUntouched($this->mediaServiceMock, $this->playlistMetricsCalculatorMock, $this->itemThumbnailResolverMock);
		$playlistId = 10;
		$itemId = 20;

		$playlistData = ['playlist_id' => $playlistId];
		$itemData = ['item_id' => $itemId, 'item_order' => 1];

		$this->playlistsServiceMock->expects($this->once())
			->method('loadPlaylistForEdit')
			->with($playlistId)
			->willReturn($playlistData);

		$this->transactionsMock->expects($this->once())
			->method('begin');

		$this->itemsRepositoryMock->expects($this->once())
			->method('findFirstBy')
			->with(['item_id' => $itemId])
			->willReturn($itemData);

		$this->itemsRepositoryMock->expects($this->once())
			->method('delete')
			->with($itemId)
			->willReturn(0);

		$this->transactionsMock->expects($this->once())
			->method('rollBack');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('item_not_deleted');
		$this->itemsService->delete($playlistId, $itemId);
	}

	/**
	 * @param array<string,mixed> $playlistData
	 */
	private function expectPlaylistMetrics(array $playlistData): void
	{
		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with($playlistData)
			->willReturn(new PlaylistMetrics(duration: 42));
		$this->loggerMock->expects($this->never())->method('error');
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
