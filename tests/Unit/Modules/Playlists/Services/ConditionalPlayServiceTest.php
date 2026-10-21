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

use App\Framework\Exceptions\AccessDeniedException;
use App\Modules\Playlists\Services\ConditionalPlayService;
use App\Modules\Playlists\Services\ItemsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConditionalPlayServiceTest extends TestCase
{
	private ItemsService&MockObject $itemServiceMock;
	private LoggerInterface&MockObject $loggerMock;
	private ConditionalPlayService $conditionalPlayService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemServiceMock = $this->createMock(ItemsService::class);
		$this->loggerMock      = $this->createMock(LoggerInterface::class);

		$this->conditionalPlayService = new ConditionalPlayService($this->itemServiceMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testFetchConditionalUnserializesSettings(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willReturn(['item_id' => 1, 'conditional' => serialize(['key' => 'value'])]);

		$result = $this->conditionalPlayService->fetchConditionalByItemId(1);

		static::assertSame(['key' => 'value'], $result['conditional']);
	}

	#[Group('units')]
	public function testFetchConditionalWithEmptyOrBrokenSettings(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->exactly(2))->method('fetchItemById')
			->willReturnOnConsecutiveCalls(['item_id' => 1, 'conditional' => ''], ['item_id' => 1, 'conditional' => 'no serialized data']);

		static::assertSame([], $this->conditionalPlayService->fetchConditionalByItemId(1)['conditional']);
		static::assertSame([], $this->conditionalPlayService->fetchConditionalByItemId(1)['conditional']);
	}

	#[Group('units')]
	public function testFetchConditionalPassesAccessDenied(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willThrowException(new AccessDeniedException('playlists', 'playlist_not_editable'));

		$this->expectException(AccessDeniedException::class);
		$this->conditionalPlayService->fetchConditionalByItemId(1);
	}

	#[Group('units')]
	public function testSaveConditionalPlay(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$requestData = ['key' => 'value'];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)->willReturn(['item_id' => 1]);
		$this->itemServiceMock->expects($this->once())->method('updateField')
			->with(1, 'conditional', serialize($requestData))
			->willReturn(1);

		$this->conditionalPlayService->saveConditionalPlay(1, $requestData);
	}

	#[Group('units')]
	public function testSaveConditionalPlayPassesAccessDenied(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')->with(1)
			->willThrowException(new AccessDeniedException('playlists', 'playlist_not_editable'));
		$this->itemServiceMock->expects($this->never())->method('updateField');

		$this->expectException(AccessDeniedException::class);
		$this->conditionalPlayService->saveConditionalPlay(1, ['key' => 'value']);
	}
}
