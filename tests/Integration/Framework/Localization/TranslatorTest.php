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

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\LocaleExtractorInterface;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\IniTranslationLoader;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemoryCache;

/**
 * The chain locale -> language code -> translation file -> text, with real config and translation files.
 */
#[Group('io')]
class TranslatorTest extends TestCase
{
	private const string RESOURCES = __DIR__ . '/../../../resources';

	private Locales $locales;
	private Translator $translator;

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$config   = new Config(new IniConfigLoader(self::RESOURCES . '/config_tests'));
		$extractor = $this->createStub(LocaleExtractorInterface::class);
		$extractor->method('extractLocale')->willReturnCallback(fn(): string => $_SERVER['TEST_LOCALE'] ?? 'en_US');

		$this->locales    = new Locales($config, $extractor);
		$this->translator = new Translator(
			$this->locales,
			new IniTranslationLoader(self::RESOURCES . '/translations_tests'),
			new MessageFormatterFactory(),
			new MemoryCache()
		);
	}

	protected function tearDown(): void
	{
		unset($_SERVER['TEST_LOCALE']);
		parent::tearDown();
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testTheTextComesFromTheFileOfTheLanguageOfTheLocale(): void
	{
		$this->useLocale('de_DE');

		static::assertSame('Hallo, Anna!', $this->translator->translate('greeting', 'greeting', ['{name}' => 'Anna']));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testAnEnglishLocaleReadsTheEnglishFile(): void
	{
		$this->useLocale('en_US');

		static::assertSame('Hello, Anna!', $this->translator->translate('greeting', 'greeting', ['{name}' => 'Anna']));
	}

	/**
	 * German has no file for the module "valid", the loader gives the English one.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testALanguageWithoutAFileGetsEnglish(): void
	{
		$this->useLocale('de_DE');

		static::assertSame('Username', $this->translator->translate('username', 'valid'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testSectionsAreAvailableAsOptions(): void
	{
		$this->useLocale('en_US');

		static::assertSame(['opt1' => 'Option 1', 'opt2' => 'Option 2'], $this->translator->translateArrayForOptions('options', 'sections'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testThePluralFollowsTheLanguageOfTheLocale(): void
	{
		$this->useLocale('en_US');
		static::assertSame('1 item', $this->translator->translateWithPlural('item_count', 'greeting', 1));
		static::assertSame('3 items', $this->translator->translateWithPlural('item_count', 'greeting', 3));

		$this->useLocale('de_DE');
		static::assertSame('1 Eintrag', $this->translator->translateWithPlural('item_count', 'greeting', 1));
		static::assertSame('3 Einträge', $this->translator->translateWithPlural('item_count', 'greeting', 3));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function testAPluralInASection(): void
	{
		$this->useLocale('en_US');

		static::assertSame('1 day ago', $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'sections', 1));
		static::assertSame('4 days ago', $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'sections', 4));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function useLocale(string $locale): void
	{
		$_SERVER['TEST_LOCALE'] = $locale;
		$this->locales->determineCurrentLocale();
	}
}
