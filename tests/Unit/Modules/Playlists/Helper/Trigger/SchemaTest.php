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

namespace Tests\Unit\Modules\Playlists\Helper\Trigger;

use App\Modules\Playlists\Helper\Trigger\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
	private Schema $schema;

	protected function setUp(): void
	{
		parent::setUp();
		$this->schema = new Schema();
	}

	/**
	 * @return array<string,mixed> a wallclock as the form sends it
	 */
	private static function wallclock(): array
	{
		return ['iso_date' => '2030-01-31T10:00:00', 'weekday' => '+3', 'repeat_counts' => '5', 'repeat_minutes' => '30', 'repeat_hours' => '1', 'repeat_days' => '0', 'repeat_weeks' => '', 'repeat_months' => 0, 'repeat_years' => '2'];
	}

	#[Group('units')]
	public function testAllTriggersAreParsedAndTheNumbersAreIntegers(): void
	{
		$input = [
			'csrf_token' => 'secret',
			'item_id'    => '1',
			'wallclocks' => [self::wallclock()],
			'accesskeys' => [['accesskey' => 'a'], ['accesskey' => '7']],
			'touches'    => [['touch_item_id' => '42']],
			'notifies'   => [['notify' => 'Go2']],
		];

		static::assertSame([
			'wallclocks' => [['iso_date' => '2030-01-31T10:00:00', 'weekday' => 3, 'repeat_counts' => 5, 'repeat_minutes' => 30, 'repeat_hours' => 1, 'repeat_days' => 0, 'repeat_weeks' => 0, 'repeat_months' => 0, 'repeat_years' => 2]],
			'accesskeys' => [['accesskey' => 'a'], ['accesskey' => '7']],
			'touches'    => [['touch_item_id' => 42]],
			'notifies'   => [['notify' => 'Go2']],
		], $this->schema->parse($input));
	}

	#[Group('units')]
	public function testNoTriggersAreNoTriggers(): void
	{
		static::assertSame([], $this->schema->parse(['csrf_token' => 'secret', 'item_id' => '1', 'foo' => 'bar', 'notifies' => []]));
	}

	#[Group('units')]
	public function testAWallclockWithoutRepeatsHasTheDefaults(): void
	{
		$result = $this->schema->parse(['wallclocks' => [['iso_date' => '2030-01-31T10:00:00']]]);

		static::assertSame(['iso_date' => '2030-01-31T10:00:00', 'weekday' => 0, 'repeat_counts' => -1, 'repeat_minutes' => 0, 'repeat_hours' => 0, 'repeat_days' => 0, 'repeat_weeks' => 0, 'repeat_months' => 0, 'repeat_years' => 0], $result['wallclocks'][0] ?? null);
	}

	/**
	 * @return array<string,array{array<mixed>}>
	 */
	public static function invalidInput(): array
	{
		$wallclock = static fn(array $changes): array => ['wallclocks' => [$changes + self::wallclock()]];

		return [
			'a trigger which is no list'            => [['notifies' => 'go']],
			'an entry which is no array'            => [['notifies' => ['go']]],
			'one invalid entry of many'             => [['notifies' => [['notify' => 'go'], ['notify' => 'no go']]]],
			'a date which leaves the attribute'     => [$wallclock(['iso_date' => '2030-01-01T10:00:00" evil="1'])],
			'a date without seconds'                => [$wallclock(['iso_date' => '2030-01-01T10:00'])],
			'a date which does not exist'           => [$wallclock(['iso_date' => '2030-02-30T10:00:00'])],
			'an hour of 24'                         => [$wallclock(['iso_date' => '2030-01-01T24:00:00'])],
			'a date as array'                       => [$wallclock(['iso_date' => ['2030-01-01T10:00:00']])],
			'no date'                               => [['wallclocks' => [['weekday' => 1]]]],
			'a weekday of 8'                        => [$wallclock(['weekday' => '8'])],
			'a weekday as text'                     => [$wallclock(['weekday' => 'mon'])],
			'repeats of -2'                         => [$wallclock(['repeat_counts' => '-2'])],
			'repeats of 10000'                      => [$wallclock(['repeat_counts' => '10000'])],
			'repeats without a number'              => [$wallclock(['repeat_counts' => ''])],
			'60 minutes'                            => [$wallclock(['repeat_minutes' => '60'])],
			'24 hours'                              => [$wallclock(['repeat_hours' => '24'])],
			'8 days'                                => [$wallclock(['repeat_days' => '8'])],
			'5 weeks'                               => [$wallclock(['repeat_weeks' => '5'])],
			'12 months'                             => [$wallclock(['repeat_months' => '12'])],
			'12 years'                              => [$wallclock(['repeat_years' => '12'])],
			'a negative interval'                   => [$wallclock(['repeat_hours' => '-1'])],
			'an interval as text'                   => [$wallclock(['repeat_hours' => '1H'])],
			'an accesskey of two characters'        => [['accesskeys' => [['accesskey' => 'ab']]]],
			'an empty accesskey'                    => [['accesskeys' => [['accesskey' => '']]]],
			'an accesskey with a quote'             => [['accesskeys' => [['accesskey' => '"']]]],
			'a touch without a number'              => [['touches' => [['touch_item_id' => 'abc']]]],
			'a touch of 0'                          => [['touches' => [['touch_item_id' => '0']]]],
			'a touch with a decimal'                => [['touches' => [['touch_item_id' => '1.5']]]],
			'a touch as array'                      => [['touches' => [['touch_item_id' => ['1']]]]],
			'an empty notify'                       => [['notifies' => [['notify' => '']]]],
			'a notify with a space'                 => [['notifies' => [['notify' => 'a b']]]],
			'a notify with a bracket'               => [['notifies' => [['notify' => 'go)']]]],
			'a notify of 65 characters'             => [['notifies' => [['notify' => str_repeat('a', 65)]]]],
			'a notify with a line break at the end' => [['notifies' => [['notify' => "go\n"]]]],
		];
	}

	/**
	 * @param array<mixed> $input
	 */
	#[DataProvider('invalidInput')]
	#[Group('units')]
	public function testInvalidValuesAreRejected(array $input): void
	{
		static::assertNull($this->schema->parse($input));
	}

	#[Group('units')]
	public function testTheWeekdayMayHaveASignAndIsAnInteger(): void
	{
		foreach (['-7' => -7, '+7' => 7, '0' => 0, '3' => 3, '-1' => -1] as $sent => $expected)
			static::assertSame($expected, $this->schema->parse(['wallclocks' => [['iso_date' => '2030-01-31T10:00:00', 'weekday' => $sent]]])['wallclocks'][0]['weekday'] ?? null, (string) $sent);
	}
}
