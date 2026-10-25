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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemoryCache;
use Tests\Support\RecordingTranslationLoader;

/**
 * "x ago" with the real Translator and MessageFormatter. The texts are those of translations/<language>/main.ini,
 * the files themselves are checked in tests/Integration/Framework/Utils/TimeUnitsTranslationTest.
 */
class TimeUnitsCalculatorTest extends TestCase
{
	private const string NOW = '2025-03-15 12:00:00';

	private string $languageCode = 'en';
	private TimeUnitsCalculator $calculator;

	protected function setUp(): void
	{
		parent::setUp();

		$this->languageCode = 'en';
		$locales = $this->createStub(Locales::class);
		$locales->method('getLanguageCode')->willReturnCallback(fn(): string => $this->languageCode);

		$translations = [
			'en' => ['main' => ['time_unit_ago' => [
				'seconds' => '{count, plural, one {1 second ago} other {# seconds ago}}',
				'minutes' => '{count, plural, one {1 minute ago} other {# minutes ago}}',
				'hours'   => '{count, plural, one {1 hour ago} other {# hours ago}}',
				'days'    => '{count, plural, one {1 day ago} other {# days ago}}',
				'months'  => '{count, plural, one {1 month ago} other {# months ago}}',
				'years'   => '{count, plural, one {1 year ago} other {# years ago}}',
			]]],
			'de' => ['main' => ['time_unit_ago' => [
				'days'    => '{count, plural, one {vor 1 Tag} other {vor # Tagen}}',
			]]],
		];

		$translator = new Translator($locales, new RecordingTranslationLoader($translations), new MessageFormatterFactory(), new MemoryCache());

		$this->calculator = new TimeUnitsCalculator($translator);
	}

	/**
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	private function distance(string $lastAccess, string $now = self::NOW): string
	{
		return $this->calculator->calculateLastAccess(new DateTime($now), new DateTime($lastAccess))->printDistance();
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCalculateLastAccess(): void
	{
		$result = $this->calculator->calculateLastAccess(new DateTime('2025-01-01 00:00:11'), new DateTime('2025-01-01 00:00:00'));

		static::assertSame($this->calculator, $result);
		static::assertSame(11, $this->calculator->getLastAccessTimeStamp());
	}

	#[Group('units')]
	public function testTheTimeStampIsZeroBeforeAnythingIsCalculated(): void
	{
		static::assertSame(0, $this->calculator->getLastAccessTimeStamp());
	}

	#[Group('units')]
	public function testCalculateLastAccessNegative(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Negative time difference.');

		$this->calculator->calculateLastAccess(new DateTime('2025-01-01 00:00:00'), new DateTime('2025-01-01 00:00:10'));
	}

	/**
	 * @return array<string, array{string, string}> time of the last access, text for "now" 2025-03-15 12:00:00
	 */
	public static function distanceProvider(): array
	{
		return [
			'same second'      => ['2025-03-15 12:00:00', '0 seconds ago'],
			'one second'       => ['2025-03-15 11:59:59', '1 second ago'],
			'seconds'          => ['2025-03-15 11:59:15', '45 seconds ago'],
			'one minute'       => ['2025-03-15 11:58:59', '1 minute ago'],
			'minutes'          => ['2025-03-15 11:15:00', '45 minutes ago'],
			'one hour'         => ['2025-03-15 10:59:00', '1 hour ago'],
			'hours'            => ['2025-03-15 05:00:00', '7 hours ago'],
			'one day'          => ['2025-03-14 11:00:00', '1 day ago'],
			'days'             => ['2025-03-10 12:00:00', '5 days ago'],
			'one month'        => ['2025-02-15 12:00:00', '1 month ago'],
			'months'           => ['2024-12-15 12:00:00', '3 months ago'],
			'one year'         => ['2024-03-15 12:00:00', '1 year ago'],
			'years'            => ['2020-03-15 12:00:00', '5 years ago'],
			'the biggest unit wins' => ['2023-01-10 08:30:15', '2 years ago'],
		];
	}

	/**
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	#[Group('units')]
	#[DataProvider('distanceProvider')]
	public function testPrintDistance(string $lastAccess, string $expected): void
	{
		static::assertSame($expected, $this->distance($lastAccess));
	}

	/**
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testPrintDistanceInTheLanguageOfTheVisitor(): void
	{
		$this->languageCode = 'de';

		static::assertSame('vor 5 Tagen', $this->distance('2025-03-10 12:00:00'));
		static::assertSame('vor 1 Tag', $this->distance('2025-03-14 12:00:00'));
	}

	/**
	 * A language which has no text for the unit gets an empty text, not an error.
	 *
	 * @throws FrameworkException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAUnitWithoutATextGivesAnEmptyString(): void
	{
		$this->languageCode = 'de';

		static::assertSame('', $this->distance('2025-03-15 11:00:00'));
	}

	#[Group('units')]
	public function testThereIsNoDistanceBeforeItIsCalculated(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('The last access has to be calculated before the distance is printed.');

		$this->calculator->printDistance();
	}
}
