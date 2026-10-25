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

namespace Tests\Unit\Framework\Localization\Translate;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemoryCache;
use Tests\Support\RecordingTranslationLoader;

/**
 * Translator with a real MessageFormatter (the plural rules are the point of it), an in-memory loader
 * and an in-memory cache. Only the language code of Locales is faked.
 */
class TranslatorTest extends TestCase
{
	private string $languageCode = 'en';
	private RecordingTranslationLoader $loader;
	private MemoryCache $cache;
	private Translator $translator;

	protected function setUp(): void
	{
		parent::setUp();

		$this->languageCode = 'en';
		$localesStub        = $this->createStub(Locales::class);
		$localesStub->method('getLanguageCode')->willReturnCallback(fn(): string => $this->languageCode);

		$this->loader     = new RecordingTranslationLoader($this->translations());
		$this->cache      = new MemoryCache();
		$this->translator = new Translator($localesStub, $this->loader, new MessageFormatterFactory(), $this->cache);
	}

	/**
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private function translations(): array
	{
		return [
			'en' => ['main' => [
				'save'          => 'Save',
				'greeting'      => 'Hello, {name}!',
				'not_found'     => 'Item {item_id} of {name} not found.',
				'options'       => ['opt1' => 'Option 1', 'opt2' => 'Option 2'],
				'item_count'    => '{count, plural, one {# item} other {# items}}',
				'time_unit_ago' => ['days' => '{count, plural, one {1 day ago} other {# days ago}}'],
				'broken_format' => '{when, date}',
			]],
			'de' => ['main' => [
				'save'       => 'Speichern',
				'item_count' => '{count, plural, one {# Eintrag} other {# Einträge}}',
			]],
			'ru' => ['main' => [
				'item_count' => '{count, plural, one {# файл} few {# файла} many {# файлов} other {# файла}}',
			]],
		];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReturnsTranslation(): void
	{
		static::assertSame('Save', $this->translator->translate('save', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReplacesPlaceholders(): void
	{
		static::assertSame('Hello, John!', $this->translator->translate('greeting', 'main', ['{name}' => 'John']));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateUsesTheLanguageOfTheCurrentLocale(): void
	{
		static::assertSame('Save', $this->translator->translate('save', 'main'));

		$this->languageCode = 'de';

		static::assertSame('Speichern', $this->translator->translate('save', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReturnsAnEmptyStringForAMissingKey(): void
	{
		static::assertSame('', $this->translator->translate('missing_key', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReturnsAnEmptyStringWhenTheEntryIsASection(): void
	{
		static::assertSame('', $this->translator->translate('options', 'main'));
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateThrowsWhenTheLoaderFindsNoFile(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Translation file not found: en/unknown_module.ini');

		$this->translator->translate('save', 'unknown_module');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslationsAreLoadedOnceAndCachedPerLanguageAndModule(): void
	{
		$this->translator->translate('save', 'main');
		$this->translator->translate('greeting', 'main');
		$this->languageCode = 'de';
		$this->translator->translate('save', 'main');
		$this->translator->translate('save', 'main');

		static::assertSame([['en', 'main'], ['de', 'main']], $this->loader->loaded);
		static::assertSame(['lang_en_main', 'lang_de_main'], array_keys($this->cache->store));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCachedTranslationsAreUsedWithoutTheLoader(): void
	{
		$this->cache->store['lang_de_main'] = ['save' => 'Aus dem Cache'];
		$this->languageCode = 'de';

		static::assertSame('Aus dem Cache', $this->translator->translate('save', 'main'));
		static::assertSame([], $this->loader->loaded);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAnEntryInTheCacheWhichIsNoArrayIsIgnored(): void
	{
		$this->cache->store['lang_en_main'] = 'garbage';

		static::assertSame('Save', $this->translator->translate('save', 'main'));
		static::assertSame([['en', 'main']], $this->loader->loaded);
	}

	/**
	 * The cache is only an optimization.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateWorksWhenTheCacheIsDown(): void
	{
		$this->cache->down = true;

		static::assertSame('Save', $this->translator->translate('save', 'main'));
		// the loaded translations stay in memory for the request
		static::assertSame('Save', $this->translator->translate('save', 'main'));
		static::assertSame([['en', 'main']], $this->loader->loaded);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateExceptionUsesKeyModuleAndReplacements(): void
	{
		$exception = new TranslatableModuleException('main', 'not_found', ['item_id' => 7, 'name' => 'Lobby']);

		static::assertSame('Item 7 of Lobby not found.', $this->translator->translateException($exception));
	}

	/**
	 * The error handler falls back to the translation key when there is no text.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateExceptionWithoutATranslationIsEmpty(): void
	{
		$exception = new TranslatableModuleException('main', 'no_such_key');

		static::assertSame('', $this->translator->translateException($exception));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateArrayForOptions(): void
	{
		static::assertSame(['opt1' => 'Option 1', 'opt2' => 'Option 2'], $this->translator->translateArrayForOptions('options', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateArrayForOptionsIsEmptyForATextOrAMissingKey(): void
	{
		static::assertSame([], $this->translator->translateArrayForOptions('save', 'main'));
		static::assertSame([], $this->translator->translateArrayForOptions('missing_key', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('pluralProvider')]
	public function testTranslateWithPluralAppliesThePluralRulesOfTheLanguage(string $languageCode, int $count, string $expected): void
	{
		$this->languageCode = $languageCode;

		static::assertSame($expected, $this->translator->translateWithPlural('item_count', 'main', $count));
	}

	/**
	 * @return array<string, array{string, int, string}>
	 */
	public static function pluralProvider(): array
	{
		return [
			'en one'   => ['en', 1, '1 item'],
			'en other' => ['en', 5, '5 items'],
			'en zero'  => ['en', 0, '0 items'],
			'de one'   => ['de', 1, '1 Eintrag'],
			'de other' => ['de', 2, '2 Einträge'],
			'ru one'   => ['ru', 1, '1 файл'],
			'ru few'   => ['ru', 3, '3 файла'],
			'ru many'  => ['ru', 5, '5 файлов'],
		];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateWithPluralIsEmptyForASection(): void
	{
		static::assertSame('', $this->translator->translateWithPlural('options', 'main', 5));
	}

	/**
	 * A missing translation is an empty text, not an error: an empty pattern can not be formatted.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateWithPluralIsEmptyForAMissingKey(): void
	{
		static::assertSame('', $this->translator->translateWithPlural('missing_key', 'main', 5));
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testTranslateWithPluralThrowsWhenTheMessageCanNotBeFormatted(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('MessageFormatter error: ');

		$this->translator->translateWithPlural('broken_format', 'main', 1, ['when' => 'not a date']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateArrayWithPlural(): void
	{
		static::assertSame('1 day ago', $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'main', 1));
		static::assertSame('5 days ago', $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'main', 5));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('missingArrayEntryProvider')]
	public function testTranslateArrayWithPluralIsEmptyForAMissingEntry(string $key, string $section): void
	{
		static::assertSame('', $this->translator->translateArrayWithPlural($key, $section, 'main', 5));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function missingArrayEntryProvider(): array
	{
		return [
			'missing key'               => ['hours', 'time_unit_ago'],
			'missing section'           => ['days', 'no_such_section'],
			'section is a plain text'   => ['days', 'save'],
		];
	}
}
