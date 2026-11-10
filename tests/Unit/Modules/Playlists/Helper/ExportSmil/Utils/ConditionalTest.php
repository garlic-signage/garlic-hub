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

use App\Modules\Playlists\Helper\ExportSmil\Utils\Conditional;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ConditionalTest extends TestCase
{
	private Conditional $conditionalMock;

	protected function setUp(): void
	{
		parent::setUp();
		$this->conditionalMock = new Conditional([]);
	}

	#[Group('units')]
	public function testDetermineExprAttributeReturnsEmptyWhenNoConditional(): void
	{
		$this->conditionalMock = new Conditional([]);
		$result = $this->conditionalMock->determineExprAttribute();

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testDetermineExprAttributeWithDateConditions(): void
	{
		$conditions = [
			'date' => ['from' => '2023-11-01', 'until' => '2023-12-31'],
			'time' => [],
			'weekdays' => []
		];

		$this->conditionalMock = new Conditional($conditions);
		$result = $this->conditionalMock->determineExprAttribute();

		$expected = 'expr="adapi-compare(substring-before(adapi-date(), \'T\'), \'2023-11-01\')&gt;=0 and adapi-compare(substring-before(adapi-date(), \'T\'), \'2023-12-31\')&lt;=0" ';
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testDetermineExprAttributeWithDateTimeConditions(): void
	{
		$conditions = [
			'date' => ['from' => '2023-11-01', 'until' => '2023-12-31'],
			'time' => ['from' => '04:00:00', 'until' => '12:00:00'],
			'weekdays' => []
		];

		$this->conditionalMock = new Conditional($conditions);
		$result = $this->conditionalMock->determineExprAttribute();

		$expected = 'expr="adapi-compare(substring-before(adapi-date(), \'T\'), \'2023-11-01\')&gt;=0 and adapi-compare(substring-before(adapi-date(), \'T\'), \'2023-12-31\')&lt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'04:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'12:00:00\')&lt;=0" ';
		static::assertSame($expected, $result);
	}


	#[Group('units')]
	public function testDetermineExprAttributeWithTimeConditions(): void
	{
		$conditions = [
			'date' => [],
			'time' => ['from' => '08:00:00', 'until' => '18:00:00'],
			'weekdays' => []
		];

		$this->conditionalMock = new Conditional($conditions);
		$result = $this->conditionalMock->determineExprAttribute();

		$expected = 'expr="adapi-compare(substring-after(adapi-date(), \'T\'), \'08:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'18:00:00\')&lt;=0" ';
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testDetermineExprAttributeWithWeektimesConditions(): void
	{
		$conditions = [
			'date' => [],
			'time' => [],
			'weekdays' => [
				1 => ['from' => 32, 'until' => 48], // 08:00:00 to 12:00:00
				2 => ['from' => 64, 'until' => 80]  // 16:00:00 to 20:00:00
			]
		];

		$this->conditionalMock = new Conditional($conditions);
		$result = $this->conditionalMock->determineExprAttribute();

		$expectedWeektimes = '((0=adapi-weekday() and adapi-compare(substring-after(adapi-date(), \'T\'), \'08:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'12:00:00\')&lt;=0) or (1=adapi-weekday() and adapi-compare(substring-after(adapi-date(), \'T\'), \'16:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'20:00:00\')&lt;=0))';
		$expected = 'expr="' . $expectedWeektimes . '" ';

		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testDetermineExprAttributeWithWeektimesDateimeCombination(): void
	{
		$conditions = [
			'date' => ['from' => '2024-02-01', 'until' => '2024-05-31'],
			'time' => [],
			'weekdays' => [
				1 => ['from' => 0, 'until' => 96]
			]
		];

		$this->conditionalMock = new Conditional($conditions);
		$result = $this->conditionalMock->determineExprAttribute();

		$expectedWeektimes = 'adapi-compare(substring-before(adapi-date(), \'T\'), \'2024-02-01\')&gt;=0 and adapi-compare(substring-before(adapi-date(), \'T\'), \'2024-05-31\')&lt;=0 and ((0=adapi-weekday() and adapi-compare(substring-after(adapi-date(), \'T\'), \'00:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'23:59:59\')&lt;=0))';
		$expected = 'expr="' . $expectedWeektimes . '" ';

		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testTheWeekdaysAreTheBits1To64AndNothingAbove(): void
	{
		$conditions = ['date' => [], 'time' => [], 'weekdays' => [64 => ['from' => 4, 'until' => 8], 128 => ['from' => 4, 'until' => 8]]];

		$result = (new Conditional($conditions))->determineExprAttribute();

		static::assertSame('expr="((6=adapi-weekday() and adapi-compare(substring-after(adapi-date(), \'T\'), \'01:00:00\')&gt;=0 and adapi-compare(substring-after(adapi-date(), \'T\'), \'02:00:00\')&lt;=0))" ', $result);
	}
}
