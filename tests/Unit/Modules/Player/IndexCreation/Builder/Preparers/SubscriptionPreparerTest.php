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

namespace Tests\Unit\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\IndexCreation\Builder\Preparers\SubscriptionPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SubscriptionPreparerTest extends TestCase
{
	private PlayerEntity&Stub $playerEntityStub;
	private SubscriptionPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->preparer         = new SubscriptionPreparer($this->playerEntityStub);
	}

	#[Group('units')]
	public function testPrepareAddsTaskScheduleWhenCommandsExist(): void
	{
		$this->playerEntityStub
			->method('getCommands')
			->willReturn(['command1']);
		$this->playerEntityStub
			->method('getIndexPath')
			->willReturn('/path/to/index');

		$result = $this->preparer->prepare();

		static::assertCount(1, $result);
		static::assertEquals('TaskSchedule', $result[0]['SUBSCRIPTION_TYPE']);
	}

	#[Group('units')]
	public function testPrepareAddsReportSubscriptionsWhenReportsExist(): void
	{
		$this->playerEntityStub
			->method('getCommands')
			->willReturn([]);
		$this->playerEntityStub
			->method('getReports')
			->willReturn([
				'inventory' => true,
				'play' => true,
				'events' => true,
				'configuration' => true,
				'executions' => true
			]);
		$this->playerEntityStub
			->method('getReportServer')
			->willReturn('http://report.server');
		$this->playerEntityStub
			->method('getUuid')
			->willReturn('uuid_value');

		$result = $this->preparer->prepare();

		static::assertCount(5, $result);
		static::assertEquals('InventoryReport', $result[0]['SUBSCRIPTION_TYPE']);
		static::assertEquals('PlaylogCollection', $result[1]['SUBSCRIPTION_TYPE']);
		static::assertEquals('EventlogCollection', $result[2]['SUBSCRIPTION_TYPE']);
		static::assertEquals('SystemReport', $result[3]['SUBSCRIPTION_TYPE']);
		static::assertEquals('TaskExecutionReport', $result[4]['SUBSCRIPTION_TYPE']);
	}

	#[Group('units')]
	public function testPrepareReturnsEmptyArrayWhenNoCommandsOrReports(): void
	{
		$this->playerEntityStub
			->method('getCommands')
			->willReturn([]);
		$this->playerEntityStub
			->method('getReports')
			->willReturn([]);

		$result = $this->preparer->prepare();

		static::assertEmpty($result);
	}
}
