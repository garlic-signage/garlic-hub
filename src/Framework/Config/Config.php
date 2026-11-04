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

namespace App\Framework\Config;

use App\Framework\Exceptions\CoreException;
use Monolog\Level;

/**
 * The Config class manages application configuration settings.
 *
 * Provides methods to load, cache, and retrieve configuration data for different modules.
 * This class uses a ConfigLoaderInterface for flexibility in loading configurations.
 */
class Config
{
	public const string PLATFORM_EDITION_CORE = 'core';
	public const string PLATFORM_EDITION_ENTERPRISE = 'enterprise';
	public const string APP_CONTAINER_DOCKER = 'docker';
	/** the former name of the core edition, an older .env still has it */
	private const string LEGACY_EDITION_EDGE = 'edge';

	private ConfigLoaderInterface $configLoader;

	/** @var array<string,array<string,string|array<string,string>>> */
	private array $configCache = [];
	/** @var array<string,string> */
	private array $paths;
	/** @var array<string,string>  */
	private array $env;

	/**
	 * @param array<string,string> $paths
	 * @param array<string,string> $env
	 */
	public function __construct(ConfigLoaderInterface $configLoader, array $paths = [], array $env = [])
	{
		$this->configLoader = $configLoader;
		$this->paths        = $paths;
		$this->env          = $env;
	}

	public function getEnv(string $key): string
	{
		return $this->env[$key] ?? '';
	}

	/**
	 * An empty edition and the former name edge are the core edition. Any other value is a mistake
	 * in the .env: running it as core would apply the migrations of the wrong edition, so it fails.
	 *
	 * @throws CoreException
	 */
	public function getEdition(): string
	{
		$edition = trim($this->getEnv('APP_PLATFORM_EDITION'));
		if ($edition === '' || $edition === self::LEGACY_EDITION_EDGE)
			return self::PLATFORM_EDITION_CORE;

		if ($edition !== self::PLATFORM_EDITION_CORE && $edition !== self::PLATFORM_EDITION_ENTERPRISE)
			throw new CoreException("Unknown APP_PLATFORM_EDITION '" . $edition . "', expected core or enterprise.");

		return $edition;
	}

	public function getPaths(string $key): string
	{
		return $this->paths[$key] ?? '';
	}

	public function getLogLevel(): Level
	{
		return match ($this->getEnv('APP_ENV'))
		{
			'dev' => Level::Debug,
			'prod' => Level::Error,
			default => Level::Info,
		};
	}

	/**
	 * Retrieves a specific configuration value.
	 *
	 * Searches for the value in the given module and optional section.
	 *
	 * @return string The configuration value or null if not found.
	 */
	public function getConfigValue(string $key, string $module, ?string $section = null): string
	{
		$config = $this->getConfigForModule($module);

		if ($section !== null && array_key_exists($section, $config))
			$config = $config[$section];

		/** @var array<string,string> $config */
		return $config[$key] ?? '';
	}

	/**
	 * A switch of the settings (1 or 0), the defaults may have a comment behind the value.
	 */
	public function isSwitchOn(string $key, string $module, string $section): bool
	{
		return IniConfigLoader::withoutComment($this->getConfigValue($key, $module, $section)) === '1';
	}

	/**
	 * @param string $module
	 * @return array<string,mixed>
	 */
	public function getFullConfigDataByModule(string $module): array
	{
		return $this->getConfigForModule($module);
	}

	/**
	 * @return array<string,string|array<string,string>>
	 */
	private function getConfigForModule(string $module): array
	{
		if (!array_key_exists($module, $this->configCache))
			$this->configCache[$module] = $this->configLoader->load($module);

		return $this->configCache[$module];
	}

	/**
	 * @param string[] $modules An array of module names to preload.
	 */
	public function preloadModules(array $modules): void
	{
		foreach ($modules as $module)
		{
			$this->getConfigForModule($module);
		}
	}
}
