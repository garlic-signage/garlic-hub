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

use App\Modules\Playlists\Services\ConditionalPlayService;
use App\Modules\Playlists\Services\ItemsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

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
		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->conditionalPlayService = new ConditionalPlayService($this->itemServiceMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testFetchConditionalSucceed(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$mockItem = ['conditional' => serialize(['key' => 'value'])];

		$this->itemServiceMock->expects($this->once())->method('setUID')->with(2);
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($mockItem);

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->fetchConditionalByItemId($itemId);

		static::assertSame(['key' => 'value'], $result['conditional']);
	}

	#[Group('units')]
	public function testFetchConditionalReturnsEmptyArrayWhenConditionalIsEmpty(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$mockItem = ['conditional' => ''];

		$this->itemServiceMock->expects($this->once())->method('setUID')->with(2);
		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($mockItem);

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->fetchConditionalByItemId($itemId);

		static::assertEmpty($result['conditional']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchConditionalHandlesException(): void
	{
		$itemId = 1;

		$this->itemServiceMock->expects($this->once())->method('setUID')
			->with(2)
			->willThrowException($this->createStub(Throwable::class));

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error conditional fetch:'));

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->fetchConditionalByItemId($itemId);
		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testSaveConditionalPlaySucceed(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$requestData = ['key' => 'value'];

		$itemMockData = ['id' => 1, 'conditional' => serialize(['key' => 'value'])];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($itemMockData);

		$this->itemServiceMock->expects($this->once())->method('updateField')
			->with($itemId, 'conditional', serialize($requestData))
			->willReturn(1);

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->saveConditionalPlay($itemId, $requestData);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testSaveConditionalPlayFailsOnNoItem(): void
	{
		$itemId = 1;
		$requestData = ['key' => 'value'];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn([]);

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error save conditional play: No item found.'));

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->saveConditionalPlay($itemId, $requestData);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSaveConditionalPlayFailsOnUpdate(): void
	{
		$itemId = 1;
		$requestData = ['key' => 'value'];

		$itemMockData = ['id' => 1, 'conditional' => serialize(['key' => 'value'])];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($itemMockData);

		$this->itemServiceMock->expects($this->once())->method('updateField')
			->with($itemId, 'conditional', serialize($requestData))
			->willReturn(0);

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error save conditional play: Could not save for item.'));

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->saveConditionalPlay($itemId, $requestData);

		static::assertFalse($result);
	}



	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testSaveConditionalPlayHandlesException(): void
	{
		$itemId = 1;
		$requestData = ['key' => 'value'];

		$this->itemServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willThrowException($this->createStub(Throwable::class));

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error save conditional play:'));

		$this->conditionalPlayService->setUID(2);
		$result = $this->conditionalPlayService->saveConditionalPlay($itemId, $requestData);

		static::assertFalse($result);
	}



}
