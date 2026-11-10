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

namespace Tests\Unit\Modules\Playlists\Helper\ExportSmil\Utils;

use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\Utils\Trigger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TriggerTest extends TestCase
{

	#[Group('units')]
	public function testHasTriggersReturnsTrueWhenTriggersExist(): void
	{
		$trigger = new Trigger(['wallclocks' => ['example']]);
		static::assertTrue($trigger->hasTriggers());
	}

	#[Group('units')]
	public function testHasTriggersReturnsFalseWhenTriggersDoNotExist(): void
	{
		$trigger = new Trigger([]);
		static::assertFalse($trigger->hasTriggers());
	}

	#[Group('units')]
	public function testDetermineTriggerWallClocksEndlessYearRepeat(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => 0,
				'repeat_years' => 1,
				'repeat_months' => 0,
				'repeat_weeks' => 0,
				'repeat_days' => 0,
				'repeat_hours' => 0,
				'repeat_minutes' => 0,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(R/2023-11-03T10:00:00/P1Y)', $result);
	}

	#[Group('units')]
	public function testDetermineWallClocksNoRepeat(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => -1,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 1, // error should be ignored
				'repeat_days' => 0,
				'repeat_hours' => 0,
				'repeat_minutes' => 0,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(2023-11-03T10:00:00)', $result);
	}

	#[Group('units')]
	public function testDetermineWallClocks4WeeksRepeat(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => 4,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 1,
				'repeat_days' => 0,
				'repeat_hours' => 0,
				'repeat_minutes' => 0,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(R4/2023-11-03T10:00:00/P1W)', $result);
	}

	#[Group('units')]
	public function testDetermineWallClocks2MonthAndDayRepeat(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => 2,
				'repeat_years' => 0,
				'repeat_months' => 1,
				'repeat_weeks' => 0,
				'repeat_days' => 1,
				'repeat_hours' => 0,
				'repeat_minutes' => 0,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(R2/2023-11-03T10:00:00/P1M1D)', $result);
	}

	#[Group('units')]
	public function testDetermineWallClocksEvery90min(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => 0,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 0,
				'repeat_days' => 0,
				'repeat_hours' => 1,
				'repeat_minutes' => 30,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(R/2023-11-03T10:00:00/PT1H30M)', $result);
	}

	#[Group('units')]
	public function testDetermineWallClocksSomeAbsurd(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2023-11-03T10:00:00',
				'repeat_counts' => 0,
				'repeat_years' => 1,
				'repeat_months' => 1,
				'repeat_weeks' => 1,
				'repeat_days' => 1,
				'repeat_hours' => 1,
				'repeat_minutes' => 1,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(R/2023-11-03T10:00:00/P1Y1M1W1DT1H1M)', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerFirstWednesdayAfterDatetime(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2025-11-03T10:00:00',
				'repeat_counts' => -1,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 1,
				'repeat_days' => 0,
				'repeat_hours' => 14,
				'repeat_minutes' => 0,
				'weekday' => 3,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(2025-11-03+w3T10:00:00)', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerFirstTuesdayBeforeDatetime(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2025-11-03T10:00:00',
				'repeat_counts' => -1,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 1,
				'repeat_days' => 0,
				'repeat_hours' => 14,
				'repeat_minutes' => 0,
				'weekday' => -2,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(2025-11-03-w2T10:00:00)', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerWallclockIntervalsError(): void
	{
		$wallclocks = [
			[
				'iso_date' => '2025-11-03T10:00:00',
				'repeat_counts' => 15,
				'repeat_years' => 0,
				'repeat_months' => 0,
				'repeat_weeks' => 0,
				'repeat_days' => 0,
				'repeat_hours' => 0,
				'repeat_minutes' => 0,
				'weekday' => 0,
			],
		];
		$triggers = new Trigger(['wallclocks' => $wallclocks]);
		$result = $triggers->determineTrigger();
		static::assertEquals('wallclock(2025-11-03T10:00:00)', $result);
	}



	#[Group('units')]
	public function testDetermineTriggerReturnsExpectedStringForAccessKeys(): void
	{
		$accessKeys = [['accesskey' => 'A']];
		$triggers = new Trigger(['accesskeys' => $accessKeys]);
		$result = $triggers->determineTrigger();
		static::assertEquals('accesskey(A)', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerReturnsExpectedStringForTouches(): void
	{
		$touches = [['touch_item_id' => '123']];
		$triggers = new Trigger(['touches' => $touches]);
		$result = $triggers->determineTrigger();
		static::assertEquals(Base::ID_PREFIX.'123.activateEvent', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerReturnsExpectedStringForNotifies(): void
	{
		$notifies = [['notify' => 'event']];
		$triggers = new Trigger(['notifies' => $notifies]);
		$result = $triggers->determineTrigger();
		static::assertEquals('notify(event)', $result);
	}

	#[Group('units')]
	public function testDetermineTriggerReturnsEmptyStringWhenNoTriggers(): void
	{
		$triggers = new Trigger([]);
		$result = $triggers->determineTrigger();
		static::assertEquals('', $result);
	}

	/**
	 * @param array<string,mixed> $wallclock
	 */
	private static function wallclock(array $wallclock): string
	{
		return (new Trigger(['wallclocks' => [$wallclock + ['iso_date' => '2025-11-03T10:00:00']]]))->determineTrigger();
	}

	#[Group('units')]
	public function testEveryUnitOfTheIntervalIsWrittenAlone(): void
	{
		$units = ['repeat_years' => 'P2Y', 'repeat_months' => 'P2M', 'repeat_weeks' => 'P2W', 'repeat_days' => 'P2D', 'repeat_hours' => 'PT2H', 'repeat_minutes' => 'PT2M'];
		foreach ($units as $field => $interval)
			static::assertSame('wallclock(R3/2025-11-03T10:00:00/' . $interval . ')', self::wallclock(['repeat_counts' => 3, $field => 2]), $field);
	}

	#[Group('units')]
	public function testOnlyNumbersOfTheDatabaseReachTheSmilFile(): void
	{
		$wallclock = ['repeat_counts' => '3x', 'repeat_years' => '1x', 'repeat_months' => '2x', 'repeat_weeks' => '3x', 'repeat_days' => '4x', 'repeat_hours' => '5x', 'repeat_minutes' => '6x', 'weekday' => '2x'];

		static::assertSame('wallclock(R3/2025-11-03+w2T10:00:00/P1Y2M3W4DT5H6M)', self::wallclock($wallclock));
	}

	#[Group('units')]
	public function testNegativeUnitsAreLeftOut(): void
	{
		$wallclock = ['repeat_counts' => 0, 'repeat_years' => -1, 'repeat_months' => -1, 'repeat_weeks' => -1, 'repeat_days' => 1, 'repeat_hours' => -1, 'repeat_minutes' => -1];

		static::assertSame('wallclock(R/2025-11-03T10:00:00/P1D)', self::wallclock($wallclock));
		static::assertSame('wallclock(R/2025-11-03T10:00:00/PT5M)', self::wallclock(['repeat_counts' => 0, 'repeat_hours' => -1, 'repeat_minutes' => 5]));
		static::assertSame('wallclock(R/2025-11-03T10:00:00/PT5H)', self::wallclock(['repeat_counts' => 0, 'repeat_hours' => 5, 'repeat_minutes' => -1]));
	}

	#[Group('units')]
	public function testMissingValuesAreNoRepeatAndNoWeekday(): void
	{
		static::assertSame('wallclock(2025-11-03T10:00:00)', self::wallclock(['repeat_weeks' => 1]));
		static::assertSame('wallclock(R/2025-11-03T10:00:00/P1D)', self::wallclock(['repeat_counts' => 0, 'repeat_days' => 1]));
		static::assertSame('wallclock(R/2025-11-03T10:00:00/PT15M)', self::wallclock(['repeat_counts' => 0, 'repeat_minutes' => 15]));
		static::assertSame('wallclock(R/2025-11-03T10:00:00/PT2H)', self::wallclock(['repeat_counts' => 0, 'repeat_hours' => 2]));
	}

	#[Group('units')]
	public function testTheWeekdayGoesFromMinus7To7(): void
	{
		static::assertSame('wallclock(2025-11-03-w7T10:00:00)', self::wallclock(['weekday' => -7]));
		static::assertSame('wallclock(2025-11-03+w7T10:00:00)', self::wallclock(['weekday' => 7]));
		static::assertSame('wallclock(2025-11-03+w1T10:00:00)', self::wallclock(['weekday' => 1]));
		static::assertSame('wallclock(2025-11-03-w1T10:00:00)', self::wallclock(['weekday' => -1]));
		static::assertSame('wallclock(2025-11-03T10:00:00)', self::wallclock(['weekday' => 8]));
		static::assertSame('wallclock(2025-11-03T10:00:00)', self::wallclock(['weekday' => -8]));
	}

	#[Group('units')]
	public function testSeveralTriggersOfEveryKindAreJoined(): void
	{
		$trigger = new Trigger([
			'wallclocks' => [['iso_date' => '2025-11-03T10:00:00'], ['iso_date' => '2025-11-04T11:00:00']],
			'accesskeys' => [['accesskey' => 'a'], ['accesskey' => 'b']],
			'touches'    => [['touch_item_id' => '1'], ['touch_item_id' => '2']],
			'notifies'   => [['notify' => 'x'], ['notify' => 'y']],
		]);

		static::assertSame(
			'wallclock(2025-11-03T10:00:00);wallclock(2025-11-04T11:00:00);accesskey(a);accesskey(b);'
			. Base::ID_PREFIX . '1.activateEvent;' . Base::ID_PREFIX . '2.activateEvent;notify(x);notify(y)',
			$trigger->determineTrigger()
		);
	}

	#[Group('units')]
	public function testUnknownTriggersAreLeftOut(): void
	{
		$trigger = new Trigger(['unknown' => [['x' => 'y']], 'notifies' => [['notify' => 'x']]]);

		static::assertSame('notify(x)', $trigger->determineTrigger());
	}
}
