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

namespace Tests\Integration\Framework\Utils;

use MessageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * "x ago" needs a text for every unit in every language, and a text that is a valid plural message.
 */
#[Group('io')]
class TimeUnitsTranslationTest extends TestCase
{
	private const string DIRECTORY = __DIR__ . '/../../../../translations';
	private const array UNITS      = ['seconds', 'minutes', 'hours', 'days', 'months', 'years'];

	/**
	 * @return array<string, array{string}>
	 */
	public static function languageProvider(): array
	{
		$languages = [];
		foreach (scandir(self::DIRECTORY) ?: [] as $entry)
		{
			if ($entry[0] !== '.' && is_dir(self::DIRECTORY . '/' . $entry))
				$languages[$entry] = [$entry];
		}

		return $languages;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function section(string $language): array
	{
		$data = parse_ini_file(self::DIRECTORY . '/' . $language . '/main.ini', true, INI_SCANNER_RAW);
		static::assertIsArray($data);
		static::assertIsArray($data['time_unit_ago'] ?? null, "$language/main.ini has no section [time_unit_ago].");

		return $data['time_unit_ago'];
	}

	#[DataProvider('languageProvider')]
	public function testEveryLanguageHasATextForEveryUnit(string $language): void
	{
		$section = $this->section($language);

		foreach (self::UNITS as $unit)
		{
			static::assertArrayHasKey($unit, $section, "$language misses the text for $unit.");
			static::assertNotSame('', trim((string) $section[$unit]), "$language has an empty text for $unit.");
		}
	}

	#[DataProvider('languageProvider')]
	public function testEveryTextIsAPluralMessageWhichShowsTheNumber(string $language): void
	{
		foreach ($this->section($language) as $unit => $pattern)
		{
			$formatter = @MessageFormatter::create($language, (string) $pattern);
			static::assertInstanceOf(MessageFormatter::class, $formatter, "$language/$unit is no valid message.");

			$text = $formatter->format(['count' => 7]);
			static::assertIsString($text, "$language/$unit can not be formatted.");
			static::assertStringContainsString('7', $text, "$language/$unit does not show the number.");
		}
	}

	/**
	 * English has the plural in the text for more than one: "5 hours ago", not "5 hour ago".
	 * Units which are written wrong today are listed with the wrong text. The test fails when the text is repaired,
	 * then the unit has to be removed from the list.
	 */
	public function testTheEnglishTextsUseThePluralForMoreThanOne(): void
	{
		$knownWrong = ['hours' => '5 hour ago'];
		$section    = $this->section('en');

		foreach (self::UNITS as $unit)
		{
			$text = (string) MessageFormatter::create('en', (string) $section[$unit])->format(['count' => 5]);

			static::assertSame($knownWrong[$unit] ?? '5 ' . $unit . ' ago', $text, "English text of $unit.");
		}
	}
}
