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

namespace App\Framework\Core\Locales;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;

/**
 * Class Locales
 *
 * This class manages locale-related functionalities such as retrieving
 * available locales, determining the current locale, and extracting specific
 * locale configurations like language code, country code, and formatting.
 * It ensures that the locale configurations are correctly loaded and validated
 * against the system's configuration.
 */
class Locales
{
	const string CONFIG_MODULE_NAME = 'locales';
	private Config $config;
	private LocaleExtractorInterface $localeExtractor;
	private string $currentLocale;
	private string $defaultLocale;
	/** @var string[] */
	private array $availableLocales;

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function __construct(Config $config, LocaleExtractorInterface $localeExtractor)
	{
		$this->config           = $config;
		$this->localeExtractor  = $localeExtractor;
		$this->availableLocales = $this->loadAvailableLocales();
		$this->defaultLocale    = $this->config->getConfigValue('default_locale', self::CONFIG_MODULE_NAME, 'general');
	}

	public function getCurrentLocale(): string
	{
		return $this->currentLocale;
	}

	public function getDefaultLocale(): string
	{
		return $this->defaultLocale;
	}

	/**
	 * @return string[]
	 */
	public function getAvailableLocales(): array
	{
		return $this->availableLocales;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getLanguageCode(): string
	{
		return $this->getConfigValue($this->currentLocale, 'language_code');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getCountryCode(): string
	{
		return $this->getConfigValue($this->currentLocale, 'country_code');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getDateFormat(): string
	{
		return $this->getConfigValue($this->currentLocale, 'date_format');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function getDateTimeFormat(): string
	{
		return $this->getConfigValue($this->currentLocale, 'date_time_format');
	}

	public function isLocaleValid(string $locale): bool
	{
		return in_array($locale, $this->availableLocales, true);
	}

	public function determineCurrentLocale(): void
	{
		$locale              = $this->localeExtractor->extractLocale($this->availableLocales);
		$this->currentLocale =  $this->isLocaleValid($locale) ? $locale : $this->defaultLocale;
	}

	/**
	 * @return string[]
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function loadAvailableLocales(): array
	{
		$locales = $this->config->getFullConfigDataByModule(self::CONFIG_MODULE_NAME);
		unset($locales['general']); // Remove general configuration like default locale

		if (empty($locales))
			throw new FrameworkException('No locales configured in the system.');

		return array_keys($locales);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function getConfigValue(string $locale, string $key): string
	{
		$value = $this->config->getConfigValue($key, self::CONFIG_MODULE_NAME, $locale);

		if (empty($value))
			throw new FrameworkException("Missing configuration for $key in locale $locale.");

		return $value;
	}
}
