<?php
/*
 GarlicSignage: Open Source Digital Signage Stack

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the GarlicSignage source code

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

namespace App\Framework\Core\Config;

use App\Framework\Exceptions\CoreException;

/**
 * Loader for module-specific configuration files in INI format.
 *
 * Reads and parses INI configuration files for the specified modules.
 * Validates the file's existence and syntax before loading.
 */
class IniConfigLoader implements ConfigLoaderInterface
{
	/**
	 * @var string The base path for configuration files.
	 */
	private string $configPath;

	/**
	 * Initializes the loader with the base configuration path.
	 *
	 * @param string $configPath The directory where configuration files are stored.
	 */
	public function __construct(string $configPath)
	{
		$this->configPath = rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Loads and parses the configuration for a specific module.
	 *
	 * @return array<string,string|array<string,string>>
	 * @throws CoreException
	 */
	public function load(string $module): array
	{
		$fileName = $this->buildConfigFileName($module);

		if (!is_file($fileName) || !is_readable($fileName))
			throw new CoreException("Unable to access configuration file: $fileName");

        /** @var array<string,string|array<string,string>>|false $config */
        $config = @parse_ini_file($fileName, true, INI_SCANNER_RAW);
        if ($config === false)
        {
            $error = error_get_last();
            throw new CoreException("Error parsing configuration file: $fileName. " . ($error['message'] ?? ''));
        }

		return $config;
	}

	/**
	 * Builds the full file path for a module's configuration file.
	 *
	 * @param string $module The name of the module.
	 * @return string The full path to the configuration file.
	 */
	private function buildConfigFileName(string $module): string
	{
		return $this->configPath . "config_$module.ini";
	}
}
