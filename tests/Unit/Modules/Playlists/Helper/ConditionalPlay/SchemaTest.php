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

namespace Tests\Unit\Modules\Playlists\Helper\ConditionalPlay;

use App\Modules\Playlists\Helper\ConditionalPlay\Schema;
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

	#[Group('units')]
	public function testParseReturnsEmptyArrayWithoutCondition(): void
	{
		static::assertSame([], $this->schema->parse([]));
		static::assertSame([], $this->schema->parse(['date' => [], 'time' => [], 'weekdays' => []]));
		static::assertSame([], $this->schema->parse(['item_id' => 5, 'csrf_token' => 'x']));
	}

	#[Group('units')]
	public function testParseKeepsEditorStructure(): void
	{
		$input = [
			'date'     => ['from' => '2026-02-28', 'until' => '2026-03-01'],
			'time'     => ['from' => '08:00', 'until' => '17:30:15'],
			'weekdays' => ['1' => ['from' => 0, 'until' => 96], '64' => ['from' => '4', 'until' => '8']]
		];

		static::assertSame([
			'date'     => ['from' => '2026-02-28', 'until' => '2026-03-01'],
			'time'     => ['from' => '08:00', 'until' => '17:30:15'],
			'weekdays' => [1 => ['from' => 0, 'until' => 96], 64 => ['from' => 4, 'until' => 8]]
		], $this->schema->parse($input));
	}

	#[Group('units')]
	public function testParseFillsMissingSections(): void
	{
		static::assertSame([
			'date'     => ['from' => '', 'until' => ''],
			'time'     => ['from' => '', 'until' => ''],
			'weekdays' => [2 => ['from' => 1, 'until' => 2]]
		], $this->schema->parse(['weekdays' => [2 => ['from' => 1, 'until' => 2]]]));
	}

	#[Group('units')]
	public function testParseKeepsEmptyPeriodsOfEnabledCondition(): void
	{
		static::assertSame([
			'date'     => ['from' => '', 'until' => ''],
			'time'     => ['from' => '', 'until' => ''],
			'weekdays' => []
		], $this->schema->parse(['date' => ['from' => '', 'until' => ''], 'time' => ['from' => '', 'until' => ''], 'weekdays' => []]));
	}

	#[Group('units')]
	public function testParseDropsUnknownKeys(): void
	{
		$result = $this->schema->parse([
			'date'  => ['from' => '2026-01-01', 'until' => '', 'extra' => 'x'],
			'other' => ['a' => 'b']
		]);

		static::assertIsArray($result);
		static::assertSame(['from' => '2026-01-01', 'until' => ''], $result['date']);
		static::assertArrayNotHasKey('other', $result);
	}

	/**
	 * @param array<mixed> $input
	 */
	#[Group('units')]
	#[DataProvider('invalidInputProvider')]
	public function testParseRejectsInvalidInput(array $input): void
	{
		static::assertNull($this->schema->parse($input));
	}

	/**
	 * @return array<string,array{array<mixed>}>
	 */
	public static function invalidInputProvider(): array
	{
		return [
			'date injection'        => [['date' => ['from' => "2026-01-01') or ('1'='1", 'until' => '']]],
			'date wrong format'     => [['date' => ['from' => '01.02.2026', 'until' => '']]],
			'date not existing'     => [['date' => ['from' => '2026-02-30', 'until' => '']]],
			'date with trailing nl' => [['date' => ['from' => "2026-02-01\n", 'until' => '']]],
			'date not a string'     => [['date' => ['from' => 20260201, 'until' => '']]],
			'date not an array'     => [['date' => '2026-02-01']],
			'time hour 24'          => [['time' => ['from' => '24:00', 'until' => '']]],
			'time with trailing nl' => [['time' => ['from' => "10:00\n", 'until' => '']]],
			'time minute 60'        => [['time' => ['from' => '10:60', 'until' => '']]],
			'time injection'        => [['time' => ['from' => "10:00')", 'until' => '']]],
			'time not an array'     => [['time' => '10:00']],
			'weekday bit unknown'   => [['weekdays' => [3 => ['from' => 0, 'until' => 1]]]],
			'weekday bit 128'       => [['weekdays' => [128 => ['from' => 0, 'until' => 1]]]],
			'weekday name'          => [['weekdays' => ['monday' => ['from' => 0, 'until' => 1]]]],
			'weekday not an array'  => [['weekdays' => [2 => 5]]],
			'weekdays not an array' => [['weekdays' => 'all']],
			'range negative'        => [['weekdays' => [2 => ['from' => -1, 'until' => 1]]]],
			'range too large'       => [['weekdays' => [2 => ['from' => 0, 'until' => 97]]]],
			'range reversed'        => [['weekdays' => [2 => ['from' => 10, 'until' => 5]]]],
			'range missing until'   => [['weekdays' => [2 => ['from' => 10]]]],
			'range not numeric'     => [['weekdays' => [2 => ['from' => 'a', 'until' => 'b']]]],
			'range float'           => [['weekdays' => [2 => ['from' => 1.5, 'until' => 2]]]],
		];
	}
}
