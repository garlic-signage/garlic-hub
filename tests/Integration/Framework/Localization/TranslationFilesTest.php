<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Framework\Localization;

use MessageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The real translation files of the application (translations/<language>/<module>.ini): every file can be
 * read, no language misses a text which English has, and every plural text is a valid ICU message.
 * English is the reference, the loader falls back to it for a missing file, but not for a missing key.
 */
#[Group('io')]
class TranslationFilesTest extends TestCase
{
	private const string DIRECTORY = __DIR__ . '/../../../../translations';

	/**
	 * Texts which a language misses today (language => file => keys). The test fails when the list is
	 * not exact: a new gap must not appear, a repaired one has to be removed from here.
	 */
	private const array KNOWN_GAPS = [
		'de' => ['player.ini' => ['player_settings'], 'main.ini' => ['UID.']],
		'el' => ['main.ini' => ['unexpected_error']],
		'es' => ['main.ini' => ['unexpected_error']],
		'fr' => ['main.ini' => ['unexpected_error']],
		'ru' => ['templates.ini' => ['replace_image'], 'main.ini' => ['unexpected_error']],
	];

	#[DataProvider('languageProvider')]
	public function testEveryFileOfTheLanguageCanBeRead(string $language): void
	{
		foreach ($this->files($language) as $file)
		{
			static::assertIsArray(@parse_ini_file($this->path($language, $file), true, INI_SCANNER_RAW), "$language/$file is no valid ini file.");
		}
	}

	#[DataProvider('languageProvider')]
	public function testALanguageMissesExactlyTheKnownTexts(string $language): void
	{
		$missing = [];
		foreach ($this->files('en') as $file)
		{
			$english = $this->keys('en', $file);
			$other   = is_file($this->path($language, $file)) ? $this->keys($language, $file) : [];
			$gap     = array_values(array_diff($english, $other));
			if ($gap !== [])
				$missing[$file] = $gap;
		}

		if ($language === 'en')
			static::assertSame([], $missing);
		else
			static::assertEqualsCanonicalizing(self::KNOWN_GAPS[$language] ?? [], $missing, "Texts of $language which English has but $language misses.");
	}

	#[DataProvider('languageProvider')]
	public function testEveryPluralTextIsAValidMessage(string $language): void
	{
		foreach ($this->files($language) as $file)
		{
			$data = parse_ini_file($this->path($language, $file), true, INI_SCANNER_RAW);
			foreach ($this->flatten(is_array($data) ? $data : []) as $key => $text)
			{
				if (!str_contains($text, 'plural'))
					continue;

				static::assertInstanceOf(MessageFormatter::class, @MessageFormatter::create($language, $text), "$language/$file: $key is no valid plural message.");
			}
		}
	}

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
	 * @return list<string>
	 */
	private function files(string $language): array
	{
		$files = glob(self::DIRECTORY . '/' . $language . '/*.ini') ?: [];

		return array_map('basename', $files);
	}

	private function path(string $language, string $file): string
	{
		return self::DIRECTORY . '/' . $language . '/' . $file;
	}

	/**
	 * @return list<string> keys, the ones in a section as "section.key"
	 */
	private function keys(string $language, string $file): array
	{
		$data = parse_ini_file($this->path($language, $file), true, INI_SCANNER_RAW);

		return array_keys($this->flatten(is_array($data) ? $data : []));
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,string>
	 */
	private function flatten(array $data, string $prefix = ''): array
	{
		$flat = [];
		foreach ($data as $key => $value)
		{
			if (is_array($value))
				$flat += $this->flatten($value, $prefix . $key . '.');
			else
				$flat[$prefix . $key] = (string) $value;
		}

		return $flat;
	}
}
