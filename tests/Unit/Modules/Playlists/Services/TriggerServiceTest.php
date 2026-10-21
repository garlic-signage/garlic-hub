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

use App\Framework\Exceptions\NotFoundException;
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
		$this->loggerMock      = $this->createMock(LoggerInterface::class);

		$this->triggerService = new TriggerService($this->itemServiceMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testFetchBeginTriggerUnserializesTriggerAndExcludesItemFromTouchableMedia(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$item = ['item_id' => 3, 'begin_trigger' => serialize(['trigger1', 'trigger2']), 'playlist_id' => 2];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(3)->willReturn($item);
		$this->itemServiceMock->expects($this->once())->method('findMediaInPlaylist')->with(2)
			->willReturn([['item_id' => 3, 'item_name' => 'Media 1'], ['item_id' => 4, 'item_name' => 'Media 2']]);

		$result = $this->triggerService->fetchBeginTriggerByItemId(3);

		static::assertSame(['trigger1', 'trigger2'], $result['item_data']['begin_trigger']);
		static::assertSame([['item_id' => 4, 'item_name' => 'Media 2']], $result['touchable_media']);
	}

	#[Group('units')]
	public function testFetchEmptyBeginTriggerWithoutMedia(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willReturn(['item_id' => 1, 'begin_trigger' => '', 'playlist_id' => 2]);
		$this->itemServiceMock->expects($this->once())->method('findMediaInPlaylist')->with(2)->willReturn([]);

		$result = $this->triggerService->fetchBeginTriggerByItemId(1);

		static::assertSame([], $result['item_data']['begin_trigger']);
		static::assertSame([], $result['touchable_media']);
	}

	#[Group('units')]
	public function testFetchBeginTriggerPassesNotFound(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willThrowException(new NotFoundException('playlists', 'item_not_found'));
		$this->itemServiceMock->expects($this->never())->method('findMediaInPlaylist');

		$this->expectException(NotFoundException::class);
		$this->triggerService->fetchBeginTriggerByItemId(1);
	}

	#[Group('units')]
	public function testSaveBeginTrigger(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$requestData = ['first' => 'trigger1', 'second' => 'trigger2'];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)->willReturn(['item_id' => 1]);
		$this->itemServiceMock->expects($this->once())->method('updateField')
			->with(1, 'begin_trigger', serialize($requestData))
			->willReturn(1);

		$this->triggerService->saveBeginTrigger(1, $requestData);
	}

	/**
	 * Saving an unchanged trigger changes no rows, which is no error.
	 */
	#[Group('units')]
	public function testSaveUnchangedBeginTrigger(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)->willReturn(['item_id' => 1]);
		$this->itemServiceMock->expects($this->once())->method('updateField')->willReturn(0);

		$this->triggerService->saveBeginTrigger(1, ['first' => 'trigger1']);
	}

	#[Group('units')]
	public function testSaveBeginTriggerPassesNotFound(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willThrowException(new NotFoundException('playlists', 'item_not_found'));
		$this->itemServiceMock->expects($this->never())->method('updateField');

		$this->expectException(NotFoundException::class);
		$this->triggerService->saveBeginTrigger(1, ['first' => 'trigger1']);
	}
}
