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
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\Builder\Preparers\ScreenTimesPreparer;
use DateInterval;
use DateInvalidOperationException;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ScreenTimesPreparerTest extends TestCase
{
	private PlayerEntity&Stub $playerEntityStub;
	private ScreenTimesPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->preparer         = new ScreenTimesPreparer($this->playerEntityStub);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareReturnsEmpty(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::COMPATIBLE);

		$result = $this->preparer->prepare();

		static::assertEmpty($result);
	}

	/**
	 * @throws DateInvalidOperationException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareReturnsScreenTimesForValidScreenTimes(): void
	{
		$screenTimes = [
			[
				'day' => 0,
				'periods' => [
					['start' => '08:00', 'end' => '12:00'],
					['start' => '14:00', 'end' => '18:00']
				]
			],
		];

		$datetime = new DateTime();
		$datetime->sub(new DateInterval('P1M'));
		$date = $datetime->format('Y-m-d');
		$expectedResult = [[
			'BEGIN_WALLCLOCKS' => 'wallclock(R/'.$date.'+w1T12:00:00/P1W);wallclock(R/'.$date.'+w1T18:00:00/P1W)',
			'END_WALLCLOCKS' => 'wallclock(R/'.$date.'+w1T08:00:00/P1W);wallclock(R/'.$date.'+w1T14:00:00/P1W)',
		]];

		$this->playerEntityStub->method('getModel')->willReturn(PlayerModel::GARLIC);
		$this->playerEntityStub->method('getScreenTimes')->willReturn($screenTimes);

		$result = $this->preparer->prepare();

		static::assertEquals($expectedResult, $result);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareReturnsEmptyArrayWhenNoValidScreenTimesExist(): void
	{
		$this->playerEntityStub
			->method('getModel')
			->willReturn(PlayerModel::GARLIC);

		$this->playerEntityStub
			->method('getScreenTimes')
			->willReturn([]);

		$result = $this->preparer->prepare();

		static::assertEmpty($result);
	}


}
