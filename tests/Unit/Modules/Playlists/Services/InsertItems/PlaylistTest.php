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

use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\InsertItems\Playlist;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaylistTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private LoggerInterface&MockObject $loggerMock;

	private Playlist $playlist;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock = $this->createMock(ItemsRepository::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->playlist = new Playlist(
			$this->itemsRepositoryMock,
			$this->playlistsServiceMock,
			$this->playlistMetricsCalculatorMock,
			$this->loggerMock
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertSuccessful(): void
	{
		$playlistId = 1;
		$insertId = 2;
		$position = 1;
		$playlistTargetData = ['playlist_id' => 1, 'duration' => 300, 'filesize' => 2048, 'playlist_name' => 'Test'];
		$playlistInsertData = ['playlist_id' => 2, 'duration' => 150, 'filesize' => 1024, 'playlist_name' => 'Insert'];
		$playlistMetrics = ['some_metrics'];

		$this->playlist->setUID(1);
		$this->itemsRepositoryMock->expects($this->once())->method('beginTransaction');
		$this->checkAclMockSuccessful($playlistTargetData, $playlistInsertData);

		$this->itemsRepositoryMock->method('findAllPlaylistItemsByPlaylistId')
			->with($insertId)
			->willReturn([]);

		$this->itemsRepositoryMock->method('updatePositionsWhenInserted')
			->with($playlistId)
			->willReturn(1);

		$saveItem = [
			'playlist_id'   => $playlistId,
			'datasource'    => 'file',
			'UID'           => 1,
			'item_duration' => $playlistInsertData['duration'],
			'item_filesize' => $playlistInsertData['filesize'],
			'item_order'    => $position,
			'item_name'     => $playlistInsertData['playlist_name'],
			'item_type'     => ItemType::PLAYLIST->value,
			'file_resource' => $insertId,
			'mimetype'      => ''
		];

		$this->itemsRepositoryMock->method('insert')
			->with($saveItem)
			->willReturn(1);

		$this->playlistMetricsCalculatorMock
			->method('calculateFromPlaylistData')
			->willReturnSelf();
		$this->playlistMetricsCalculatorMock
			->method('getMetricsForFrontend')
			->willReturn($playlistMetrics);

		$this->itemsRepositoryMock->method('commitTransaction');

		$result = $this->playlist->insert($playlistId, $insertId, $position);

		$saveItem['item_id'] = 1;
		$saveItem['paths']['thumbnail'] = 'public/images/icons/playlist.svg';

		static::assertSame($playlistMetrics, $result['playlist_metrics']);
		static::assertSame($saveItem, $result['item']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertCheRecursionFailsSamePlaylist(): void
	{
		$playlistId = 1;
		$insertId = 1;
		$position = 1;
		$playlistTargetData = ['playlist_id' => 1, 'duration' => 300, 'filesize' => 2048, 'playlist_name' => 'Test'];
		$playlistInsertData = ['playlist_id' => 2, 'duration' => 150, 'filesize' => 1024, 'playlist_name' => 'Insert'];

		$this->playlist->setUID(1);
		$this->itemsRepositoryMock->expects($this->once())->method('beginTransaction');
		$this->checkAclMockSuccessful($playlistTargetData, $playlistInsertData);

		$this->itemsRepositoryMock->expects($this->never())->method('findAllPlaylistItemsByPlaylistId');

		$this->itemsRepositoryMock->expects($this->never())->method('updatePositionsWhenInserted');

		$this->itemsRepositoryMock->method('rollBackTransaction');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error insert playlist: Playlist recursion alert.');

		static::assertEmpty($this->playlist->insert($playlistId, $insertId, $position));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertCheRecursionFailsSamePlaylistAfterRecursion(): void
	{
		$playlistId = 1;
		$insertId = 2;
		$position = 1;
		$playlistTargetData = ['playlist_id' => 1, 'duration' => 300, 'filesize' => 2048, 'playlist_name' => 'Test'];
		$playlistInsertData = ['playlist_id' => 2, 'duration' => 150, 'filesize' => 1024, 'playlist_name' => 'Insert'];

		$this->playlist->setUID(1);
		$this->itemsRepositoryMock->expects($this->once())->method('beginTransaction');
		$this->checkAclMockSuccessful($playlistTargetData, $playlistInsertData);

		$this->itemsRepositoryMock->expects($this->once())->method('findAllPlaylistItemsByPlaylistId')
			->with($insertId)
			->willReturn([
				['file_resource' => $playlistId]
			]);

		$this->itemsRepositoryMock->expects($this->never())->method('updatePositionsWhenInserted');

		$this->itemsRepositoryMock->method('rollBackTransaction');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error insert playlist: Playlist recursion alert.');

		static::assertEmpty($this->playlist->insert($playlistId, $insertId, $position));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertFails(): void
	{
		$playlistId = 1;
		$insertId = 2;
		$position = 1;
		$playlistTargetData = ['playlist_id' => 1, 'duration' => 300, 'filesize' => 2048, 'playlist_name' => 'Test'];
		$playlistInsertData = ['playlist_id' => 2, 'duration' => 150, 'filesize' => 1024, 'playlist_name' => 'Insert'];

		$this->playlist->setUID(1);
		$this->itemsRepositoryMock->expects($this->once())->method('beginTransaction');
		$this->checkAclMockSuccessful($playlistTargetData, $playlistInsertData);

		$this->itemsRepositoryMock->method('findAllPlaylistItemsByPlaylistId')
			->with($insertId)
			->willReturn([]);

		$this->itemsRepositoryMock->method('updatePositionsWhenInserted')
			->with($playlistId)
			->willReturn(1);

		$saveItem = [
			'playlist_id'   => $playlistId,
			'datasource'    => 'file',
			'UID'           => 1,
			'item_duration' => $playlistInsertData['duration'],
			'item_filesize' => $playlistInsertData['filesize'],
			'item_order'    => $position,
			'item_name'     => $playlistInsertData['playlist_name'],
			'item_type'     => ItemType::PLAYLIST->value,
			'file_resource' => $insertId,
			'mimetype'      => ''
		];

		$this->itemsRepositoryMock->method('insert')
			->with($saveItem)
			->willReturn(0);

		$this->playlistMetricsCalculatorMock->expects($this->never())->method('calculateFromPlaylistData');

		$this->itemsRepositoryMock->method('rollBackTransaction');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error insert playlist: Playlist item could not inserted.');

		static::assertEmpty($this->playlist->insert($playlistId, $insertId, $position));
	}

	/**
	 * @param array<string,mixed> $playlistTargetData
	 * @param array<string,mixed> $playlistInsertData
	 * @return void
	 */
	private function checkAclMockSuccessful(array $playlistTargetData, array $playlistInsertData): void
	{
		$this->playlistsServiceMock->expects($this->exactly(2))->method('setUID')
			->with(1);
		$this->playlistsServiceMock->expects($this->exactly(2))->method('loadPlaylistForEdit')
			->willReturnMap([
				[$playlistTargetData['playlist_id'], $playlistTargetData],
				[$playlistInsertData['playlist_id'], $playlistInsertData]
			]);
	}
}
