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


namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TimeUnitsCalculatorTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private TimeUnitsCalculator $timeUnitsCalculator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);

		$this->timeUnitsCalculator = new TimeUnitsCalculator($this->translatorMock);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCalculateLastAccess():void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());

		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-01 00:00:11'),
			new DateTime('2025-01-01 00:00:00')
		);

		static::assertSame(11, $this->timeUnitsCalculator->getLastAccessTimeStamp());
	}

	#[Group('units')]
	public function testCalculateLastAccessNegative(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Negative time difference.');

		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-01 00:00:00'),
			new DateTime('2025-01-01 00:00:10')
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceSecond(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-01 00:00:01'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('seconds', 'time_unit_ago', 'main', 1, [])
			->willReturn('1 second ago');
		static::assertSame('1 second ago', $this->timeUnitsCalculator->printDistance());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceMinutes(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-01 00:10:10'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('minutes', 'time_unit_ago', 'main', 10, [])
			->willReturn('10 minutes ago');
		static::assertSame('10 minutes ago', $this->timeUnitsCalculator->printDistance());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceHours(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-01 01:10:10'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('hours', 'time_unit_ago', 'main', 1, [])
			->willReturn('1 hour ago');
		static::assertSame('1 hour ago', $this->timeUnitsCalculator->printDistance());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceDays(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-01-03 01:10:10'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('days', 'time_unit_ago', 'main', 2, [])
			->willReturn('2 days ago');
		static::assertSame('2 days ago', $this->timeUnitsCalculator->printDistance());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceMonths(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2025-05-03 01:10:10'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('months', 'time_unit_ago', 'main', 4, [])
			->willReturn('4 months ago');
		static::assertSame('4 months ago', $this->timeUnitsCalculator->printDistance());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrintDistanceYears(): void
	{
		$this->timeUnitsCalculator->calculateLastAccess(
			new DateTime('2030-01-02 01:10:10'),
			new DateTime('2025-01-01 00:00:00')
		);

		$this->translatorMock->expects($this->once())->method('translateArrayWithPlural')
			->with('years', 'time_unit_ago', 'main', 5, [])
			->willReturn('5 years ago');
		static::assertSame('5 years ago', $this->timeUnitsCalculator->printDistance());
	}

}
