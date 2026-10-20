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

use App\Modules\Playlists\Helper\Widgets\ContentDataPreparer;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\WidgetsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WidgetsServiceTest extends TestCase
{
	private ItemsService&MockObject $itemsServiceMock;
	private ContentDataPreparer&MockObject $contentDataMock;
	private LoggerInterface&MockObject $loggerMock;
	private WidgetsService $widgetsService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsServiceMock = $this->createMock(ItemsService::class);
		$this->contentDataMock = $this->createMock(ContentDataPreparer::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->widgetsService = new WidgetsService(
			$this->itemsServiceMock,
			$this->contentDataMock,
			$this->loggerMock
		);
	}

	#[Group('units')]
	public function testGetErrorTextReturnsDefaultEmptyValue(): void
	{
		$this->expectUntouched($this->itemsServiceMock, $this->contentDataMock, $this->loggerMock);
		static::assertSame('', $this->widgetsService->getErrorText());
	}

	#[Group('units')]
	public function testFetchWidgetByItemIdReturnsExpectedResult(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$UID = 12;
		$itemData = [
			'config_data' => '<config></config>',
			'content_data' => serialize(['key' => 'value']),
			'item_name' => 'Test Widget',
			'mimetype' => 'application/widget'
		];
		$this->widgetsService->setUID($UID);
		$this->itemsServiceMock->expects($this->once())->method('setUID')
			->with($UID);
		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($itemData);

		$this->contentDataMock->expects($this->once())->method('determinePreferences')
			->with('<config></config>')
			->willReturn(['prefs_key' => 'prefs_value']);


		$expected = [
			'item_id' => $itemId,
			'values' => ['key' => 'value'],
			'preferences' => ['prefs_key' => 'prefs_value'],
			'item_name' => 'Test Widget',
		];
		static::assertSame($expected, $this->widgetsService->fetchWidgetByItemId($itemId));
	}

	#[Group('units')]
	public function testFetchWidgetByItemIdReturnsEmptyWhenNoWidget(): void
	{
		$itemId = 1;
		$UID = 12;
		$itemData = [
			'config_data' => '<config></config>',
			'content_data' => serialize(['key' => 'value']),
			'item_name' => 'Test Widget',
			'mimetype' => 'text/html'
		];
		$this->widgetsService->setUID($UID);
		$this->itemsServiceMock->expects($this->once())->method('setUID')
			->with($UID);
		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($itemData);

		$this->contentDataMock->expects($this->never())->method('determinePreferences');
		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains('Error widget fetch: Not a widget item.'));

		static::assertEmpty($this->widgetsService->fetchWidgetByItemId($itemId));

	}

	#[Group('units')]
	public function testSaveWidgetSuccessfullySaves(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$requestData = ['key' => 'value'];
		$configData = '<config></config>';
		$serializedContent = serialize(['prepared_key' => 'prepared_value']);

		$itemData = [
			'config_data' => $configData,
			'content_data' => null,
			'item_name' => 'Test Widget',
			'mimetype' => 'application/widget'
		];

		$this->widgetsService->setUID(14);
		$this->itemsServiceMock->expects($this->once())->method('setUID')
			->with(14);

		$this->itemsServiceMock->expects($this->once())->method('fetchItemById')
			->with($itemId)
			->willReturn($itemData);

		$this->contentDataMock->expects($this->once())->method('prepareContentData')
			->with($configData, $requestData, false)
			->willReturn(['prepared_key' => 'prepared_value']);

		$this->itemsServiceMock->expects($this->once())
			->method('updateField')
			->with($itemId, 'content_data', $serializedContent);

		static::assertTrue($this->widgetsService->saveWidget($itemId, $requestData));
	}

	#[Group('units')]
	public function testSaveWidgetHandlesExceptionAndFails(): void
	{
		$this->contentDataMock->expects($this->never())->method($this->anything());
		$itemId = 1;
		$requestData = ['key' => 'value'];
		$exceptionMessage = 'Error saving data';

		$this->widgetsService->setUID(14);
		$this->itemsServiceMock->expects($this->once())->method('setUID')
			->with(14);

		$this->itemsServiceMock->expects($this->once())
			->method('fetchItemById')
			->with($itemId)
			->willThrowException(new \Exception($exceptionMessage));

		$this->loggerMock->expects($this->once())
			->method('error')
			->with(static::stringContains($exceptionMessage));

		static::assertFalse($this->widgetsService->saveWidget($itemId, $requestData));
		static::assertSame($exceptionMessage, $this->widgetsService->getErrorText());
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
