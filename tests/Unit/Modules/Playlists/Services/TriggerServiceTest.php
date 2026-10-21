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

use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\TriggerService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TriggerServiceTest extends TestCase
{
	private ItemsService&MockObject $itemServiceMock;
	private LoggerInterface&MockObject $loggerMock;
	private TriggerService $triggerService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->itemServiceMock = $this->createMock(ItemsService::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->triggerService = new TriggerService($this->itemServiceMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testValidItemId(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$playlistId = 2;
		$item = [
			'begin_trigger' => serialize(['trigger1', 'trigger2']),
			'playlist_id' => $playlistId,
		];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($item);

		$this->itemServiceMock->method('findMediaInPlaylist')
			->willReturn([]);

		$result = $this->triggerService->fetchBeginTriggerByItemId($itemId);

		static::assertEquals(['trigger1', 'trigger2'], $result->getItemData()['begin_trigger']);
	}

	#[Group('units')]
	public function testEmptyBeginTrigger(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$playlistId = 2;
		$item = [
			'begin_trigger' => '',
			'playlist_id' => $playlistId,
		];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($item);

		$this->itemServiceMock->method('findMediaInPlaylist')
			->willReturn([]);

		$result = $this->triggerService->fetchBeginTriggerByItemId($itemId);

		static::assertEmpty($result->getItemData()['begin_trigger']);
	}

	#[Group('units')]
	public function testExceptionHandling(): void
	{
		$itemId = 1;

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with($itemId)
			->willThrowException(new \Exception('Test Exception'));

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error begin trigger fetch: Test Exception');

		$this->triggerService->fetchBeginTriggerByItemId($itemId);
	}

	#[Group('units')]
	public function testSaveBeginTriggerSuccess(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$requestData = ['first' => 'trigger1', 'second' => 'trigger2'];
		$item = ['id' => $itemId];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($item);
		$this->itemServiceMock->expects($this->once())->method('updateField')
			->with($itemId, 'begin_trigger', serialize($requestData))
			->willReturn(1);

		$result = $this->triggerService->saveBeginTrigger($itemId, $requestData);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testSaveBeginTriggerNoItemFound(): void
	{
		$itemId = 1;
		$requestData = ['first' => 'trigger1', 'second' => 'trigger2'];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn([]);

		$this->itemServiceMock->expects($this->never())->method('updateField');

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error save trigger: No item found.');

		$result = $this->triggerService->saveBeginTrigger($itemId, $requestData);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSaveBeginTriggerNothingAffected(): void
	{
		$itemId = 1;
		$requestData = ['first' => 'trigger1', 'second' => 'trigger2'];
		$item = ['id' => $itemId];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($item);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error save trigger: Could not save begin trigger.');

		$result = $this->triggerService->saveBeginTrigger($itemId, $requestData);

		static::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTouchableMediaMediaExcludedFromTouchableMedia(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 2;
		$itemId = 3;

		$mediaItems = [
			['item_id' => 3, 'item_name' => 'Media 1'],
			['item_id' => 4, 'item_name' => 'Media 2'],
		];

		$this->itemServiceMock->expects($this->once())->method('findMediaInPlaylist')
			->with($playlistId)
			->willReturn($mediaItems);

		$this->triggerService->prepareTouchableMedia($playlistId, $itemId);
		$result = $this->triggerService->getTouchableMedia();

		static::assertEquals([['item_id' => 4, 'item_name' => 'Media 2']], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTouchableMediaEmptyMediaReturned(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 2;
		$itemId = 3;

		$this->itemServiceMock->expects($this->once())->method('findMediaInPlaylist')
			->with($playlistId)
			->willReturn([]);

		$this->triggerService->prepareTouchableMedia($playlistId, $itemId);
		$result = $this->triggerService->getTouchableMedia();

		static::assertEmpty($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTouchableMediaSingleMatchExcluded(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 5;
		$itemId = 10;

		$mediaItems = [
			['item_id' => 10, 'item_name' => 'Media A'],
			['item_id' => 11, 'item_name' => 'Media B'],
			['item_id' => 12, 'item_name' => 'Media C'],
		];

		$this->itemServiceMock->expects($this->once())->method('findMediaInPlaylist')
			->with($playlistId)
			->willReturn($mediaItems);

		$this->triggerService->prepareTouchableMedia($playlistId, $itemId);
		$result = $this->triggerService->getTouchableMedia();

		static::assertEquals(
			[
				['item_id' => 11, 'item_name' => 'Media B'],
				['item_id' => 12, 'item_name' => 'Media C']
			],
			$result
		);
	}


}
