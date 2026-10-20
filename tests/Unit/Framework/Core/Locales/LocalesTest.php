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

namespace Tests\Unit\Framework\Core\Locales;
use App\Framework\Core\Config\Config;
use App\Framework\Core\Locales\LocaleExtractorInterface;
use App\Framework\Core\Locales\Locales;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LocalesTest extends TestCase
{
	private Config&MockObject $configMock;
	private LocaleExtractorInterface&MockObject $localeExtractorMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock          = $this->createMock(Config::class);
		$this->localeExtractorMock = $this->createMock(LocaleExtractorInterface::class);
		$this->configMock->method('getFullConfigDataByModule')
						 ->with('locales')
						 ->willReturn([
							 'en_US' => ['language_code' => 'en', 'country_code' => 'US', 'date_format' => '%Y-%m-%d'],
							 'de_DE' => ['language_code' => 'de', 'country_code' => 'DE', 'date_format' => '%d-%m-%Y'],
							 'general' => ['default_locale' => 'en_US']
						 ]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetDefaultLocale(): void
	{
		$this->configMock->method('getConfigValue')
						 ->with('default_locale', 'locales', 'general')
						 ->willReturn('en_US');

		$locales = $this->initTestClass();
		static::assertSame('en_US', $locales->getDefaultLocale());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetAvailableLocales(): void
	{
		$this->configMock->method('getConfigValue')
						 ->with('default_locale', 'locales', 'general')
						 ->willReturn('en_US');
		$locales = $this->initTestClass();
		static::assertSame(['en_US', 'de_DE'], $locales->getAvailableLocales());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsLocaleValid(): void
	{
		$this->configMock->method('getConfigValue')
						 ->with('default_locale', 'locales', 'general')
						 ->willReturn('en_US');

		$locales = $this->initTestClass();

		static::assertTrue($locales->isLocaleValid('en_US'));
		static::assertFalse($locales->isLocaleValid('fr_FR'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDetermineCurrentLocale(): void
	{
		$this->configMock->method('getConfigValue')
						 ->with('default_locale', 'locales', 'general')
						 ->willReturn('de_DE');

		$locales = $this->initTestClass();
		$locales->determineCurrentLocale();

		static::assertSame('en_US', $locales->getCurrentLocale());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testDetermineCurrentLocaleFallbackToDefault(): void
	{
		$this->localeExtractorMock->expects($this->never())->method($this->anything());
		// do not use the setUp $localeExtractorMock here
		$localeExtractorMock = $this->createMock(LocaleExtractorInterface::class);
		$localeExtractorMock
			->method('extractLocale')
			->with(['en_US', 'de_DE'])
			->willReturn('fr_FR'); // not valid locale

		$this->configMock->method('getConfigValue')
						 ->with('default_locale', 'locales', 'general')
						 ->willReturn('en_US');

		$locales = new Locales($this->configMock, $localeExtractorMock);
		$locales->determineCurrentLocale();

		static::assertSame('en_US', $locales->getCurrentLocale());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetLanguageCode(): void
	{

		$this->configMock->method('getConfigValue')
						 ->willReturnCallback(function($key, $module_name, $section)
						 {
							 if ($section === 'general')
								 return 'en_US';

							 return 'en';
						});

		$locales = $this->initTestClass();
		$locales->determineCurrentLocale();
		static::assertSame('en', $locales->getLanguageCode());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetCountryCode(): void
	{
		$this->configMock->method('getConfigValue')
						 ->willReturnCallback(function($key, $module_name, $section)
						 {
							 if ($section === 'general')
								 return 'en_US';

							 return 'US';
						 });

		$locales = $this->initTestClass();

		$locales->determineCurrentLocale();
		static::assertSame('US', $locales->getCountryCode());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetDateFormat(): void
	{
		$this->configMock->method('getConfigValue')
						 ->willReturnCallback(function($key, $module_name, $section)
						 {
							 if ($section === 'general')
								 return 'en_US';

							 if ($key === 'date_format')
								 return 'd.m.Y';

							 return '';
						 });

		$locales = $this->initTestClass();

		$locales->determineCurrentLocale();

		static::assertSame('d.m.Y', $locales->getDateFormat());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetDateTimeFormat(): void
	{
		$this->configMock->method('getConfigValue')
						 ->willReturnCallback(function($key, $module_name, $section)
						 {
							 if ($section === 'general')
								 return 'en_US';

							 if ($key === 'date_time_format')
								 return 'd.m.Y h:i:s';

							 return '';
						 });

		$locales = $this->initTestClass();

		$locales->determineCurrentLocale();

		static::assertSame('d.m.Y h:i:s', $locales->getDateTimeFormat());
	}



	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadAvailableLocalesThrowsExceptionIfEmpty(): void
	{
		$this->localeExtractorMock->expects($this->never())->method($this->anything());
		// do not use setUp ConfigMock here
		$configMock = $this->createMock(Config::class);

		$configMock->method('getFullConfigDataByModule')
						 ->with('locales')
						 ->willReturn(['general' => ['default_locale' => 'en_US']]); // no valid Locale

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('No locales configured in the system.');

		new Locales($configMock, $this->localeExtractorMock);
	}


	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testGetConfigValueThrowsExceptionIfMissing(): void
	{
		$this->configMock->method('getConfigValue')
						 ->willReturnCallback(function($key, $module_name, $section)
						 {
							 if ($section === 'general')
								 return 'en_US';

							 return '';
						 });
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Missing configuration for language_code in locale en_US.');

		$locales = $this->initTestClass();
		$locales->determineCurrentLocale();
		$locales->getLanguageCode();

	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function initTestClass(): Locales
	{
		$this->localeExtractorMock
			->method('extractLocale')
			->with(['en_US', 'de_DE'])
			->willReturn('en_US');

		return new Locales($this->configMock, $this->localeExtractorMock);
	}
}
