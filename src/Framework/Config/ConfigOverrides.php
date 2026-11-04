<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

/**
 * The values an admin changed, one ini file per module in var/settings: config/settings has the defaults
 * which an update replaces, var/settings stays. A file has only the changed keys, in the sections of the defaults.
 */
class ConfigOverrides
{
	private string $directory;

	public function __construct(string $directory)
	{
		$this->directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return array<string,string|array<string,string>> empty without changes
	 * @throws CoreException
	 */
	public function load(string $module): array
	{
		if (!is_file($this->fileName($module)))
			return [];

		// the same file names and the same parsing as the defaults
		return new IniConfigLoader($this->directory)->load($module);
	}

	/**
	 * Replaces the changes of the module. Written to a temporary file and renamed: an admin who
	 * saves at the same time never leaves half a file. Without changes the file is removed.
	 *
	 * @param array<string,array<string,string>> $sections section => key => value
	 * @throws CoreException
	 */
	public function save(string $module, array $sections): void
	{
		$fileName = $this->fileName($module);
		$sections = array_filter($sections, fn(array $values) => $values !== []);
		if ($sections === [])
		{
			if (is_file($fileName) && !unlink($fileName))
				throw new CoreException('Configuration overrides can not be removed: ' . $fileName);
			return;
		}

		if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory))
			throw new CoreException('Directory for configuration overrides can not be created: ' . $this->directory);

		$temporary = $fileName . '.' . bin2hex(random_bytes(4)) . '.tmp';
		if (file_put_contents($temporary, $this->toIni($sections)) === false || !rename($temporary, $fileName))
		{
			@unlink($temporary);
			throw new CoreException('Configuration overrides can not be written: ' . $fileName);
		}
	}

	/**
	 * @param array<string,array<string,string>> $sections
	 * @throws CoreException
	 */
	private function toIni(array $sections): string
	{
		$ini = '';
		foreach ($sections as $section => $values)
		{
			$ini .= '[' . $section . "]\n";
			foreach ($values as $key => $value)
			{
				// the defaults are read raw: a quote or a line break would end the value
				if (str_contains($value, '"') || preg_match('/[\r\n]/', $value) === 1)
					throw new CoreException('A configuration value must not contain quotes or line breaks: ' . $section . '.' . $key);

				$ini .= $key . ' = "' . $value . "\"\n";
			}
			$ini .= "\n";
		}

		return $ini;
	}

	private function fileName(string $module): string
	{
		return $this->directory . 'config_' . $module . '.ini';
	}
}
