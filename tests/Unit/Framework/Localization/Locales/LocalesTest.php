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

namespace Tests\Unit\Framework\Localization\Locales;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\LocaleExtractorInterface;
use App\Framework\Localization\Locales\Locales;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Config\RecordingConfigLoader;

class LocalesTest extends TestCase
{
	private LocaleExtractorInterface&Stub $extractorStub;

	protected function setUp(): void
	{
		parent::setUp();
		$this->extractorStub = $this->createStub(LocaleExtractorInterface::class);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetDefaultLocale(): void
	{
		static::assertSame('en_US', $this->locales()->getDefaultLocale());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetAvailableLocalesDoesNotContainTheGeneralSection(): void
	{
		static::assertSame(['en_US', 'de_DE'], $this->locales()->getAvailableLocales());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsLocaleValid(): void
	{
		$locales = $this->locales();

		static::assertTrue($locales->isLocaleValid('en_US'));
		static::assertTrue($locales->isLocaleValid('de_DE'));
		static::assertFalse($locales->isLocaleValid('fr_FR'));
		static::assertFalse($locales->isLocaleValid('de_de'));
		static::assertFalse($locales->isLocaleValid('general'));
		static::assertFalse($locales->isLocaleValid(''));
	}

	/**
	 * Before the locale is determined (e.g. an error before the LocaleMiddleware) the default is current.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheDefaultLocaleIsCurrentBeforeItIsDetermined(): void
	{
		$locales = $this->locales();

		static::assertSame('en_US', $locales->getCurrentLocale());
		static::assertSame('en', $locales->getLanguageCode());
	}

	/**
	 * The extractor only gets a valid answer when it is asked with the available locales.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDetermineCurrentLocaleTakesTheAnswerOfTheExtractor(): void
	{
		$this->extractorStub->method('extractLocale')
			->willReturnCallback(static fn(array $whiteList): string => $whiteList === ['en_US', 'de_DE'] ? 'de_DE' : 'xx_XX');
		$locales = $this->locales();

		$locales->determineCurrentLocale();

		static::assertSame('de_DE', $locales->getCurrentLocale());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('invalidLocaleProvider')]
	public function testDetermineCurrentLocaleFallsBackToTheDefault(string $extracted): void
	{
		$this->extractorStub->method('extractLocale')->willReturn($extracted);
		$locales = $this->locales();

		$locales->determineCurrentLocale();

		static::assertSame('en_US', $locales->getCurrentLocale());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidLocaleProvider(): array
	{
		return ['not configured' => ['fr_FR'], 'empty' => [''], 'general section' => ['general']];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheGettersReadTheConfigOfTheCurrentLocale(): void
	{
		$this->extractorStub->method('extractLocale')->willReturn('de_DE');
		$locales = $this->locales();
		$locales->determineCurrentLocale();

		static::assertSame('de', $locales->getLanguageCode());
		static::assertSame('DE', $locales->getCountryCode());
		static::assertSame('%d.%m.%Y', $locales->getDateFormat());
		static::assertSame('%d.%m.%Y %H:%i:%s', $locales->getDateTimeFormat());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheGettersOfTheDefaultLocale(): void
	{
		$this->extractorStub->method('extractLocale')->willReturn('en_US');
		$locales = $this->locales();
		$locales->determineCurrentLocale();

		static::assertSame('en', $locales->getLanguageCode());
		static::assertSame('US', $locales->getCountryCode());
		static::assertSame('%m-%d-%Y', $locales->getDateFormat());
		static::assertSame('%m-%d-%Y %H:%i:%s', $locales->getDateTimeFormat());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('missingKeyProvider')]
	public function testAMissingConfigValueThrows(string $key, string $method): void
	{
		$data = $this->localesData();
		unset($data['de_DE'][$key]);
		$this->extractorStub->method('extractLocale')->willReturn('de_DE');
		$locales = $this->locales($data);
		$locales->determineCurrentLocale();

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage("Missing configuration for $key in locale de_DE.");

		$locales->$method();
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function missingKeyProvider(): array
	{
		return [
			'language code'    => ['language_code', 'getLanguageCode'],
			'country code'     => ['country_code', 'getCountryCode'],
			'date format'      => ['date_format', 'getDateFormat'],
			'date time format' => ['date_time_format', 'getDateTimeFormat'],
		];
	}

	/**
	 * A default locale which is not configured would fail later on the first page of a new visitor,
	 * so the system refuses to start with it.
	 *
	 */
	#[Group('units')]
	#[DataProvider('invalidDefaultProvider')]
	public function testADefaultLocaleWhichIsNotConfiguredThrows(string $default, string $expectedMessage): void
	{
		$data = $this->localesData();
		$data['general']['default_locale'] = $default;

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage($expectedMessage);

		$this->locales($data);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidDefaultProvider(): array
	{
		return [
			'not configured' => ['fr_FR', "The default locale 'fr_FR' is not one of the configured locales: en_US, de_DE."],
			'wrong case'     => ['de_de', "The default locale 'de_de' is not one of the configured locales: en_US, de_DE."],
			'empty'          => ['', "The default locale '' is not one of the configured locales: en_US, de_DE."],
			'the section name' => ['general', "The default locale 'general' is not one of the configured locales: en_US, de_DE."],
		];
	}

	#[Group('units')]
	public function testWithoutAGeneralSectionTheDefaultIsMissing(): void
	{
		$data = $this->localesData();
		unset($data['general']);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage("The default locale ''");

		$this->locales($data);
	}

	#[Group('units')]
	public function testNoConfiguredLocaleThrows(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('No locales configured in the system.');

		$this->locales(['general' => ['default_locale' => 'en_US']]);
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function localesData(): array
	{
		return [
			'general' => ['default_locale' => 'en_US'],
			'en_US'   => ['language_code' => 'en', 'country_code' => 'US', 'date_format' => '%m-%d-%Y', 'date_time_format' => '%m-%d-%Y %H:%i:%s'],
			'de_DE'   => ['language_code' => 'de', 'country_code' => 'DE', 'date_format' => '%d.%m.%Y', 'date_time_format' => '%d.%m.%Y %H:%i:%s'],
		];
	}

	/**
	 * @param array<string, array<string, string>>|null $data
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function locales(?array $data = null): Locales
	{
		$config = new Config(new RecordingConfigLoader([Locales::CONFIG_MODULE_NAME => $data ?? $this->localesData()]));

		return new Locales($config, $this->extractorStub);
	}
}
