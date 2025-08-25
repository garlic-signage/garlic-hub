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

namespace App\Framework\Core\Translate;

use App\Framework\Exceptions\CoreException;

/**
 * Implements a translation loader that retrieves translations from INI files.
 * Files are expected to be located under a base directory, organized by language code and module names.
 */
class IniTranslationLoader implements TranslationLoaderInterface
{
	protected string $baseDirectory;

	public function __construct(string $baseDirectory)
	{
		$this->baseDirectory = rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 */
	public function load(string $languageCode, string $module): array
	{
		$filePath = $this->buildFilePath($languageCode, $module);

		if (!file_exists($filePath))
		{
			$filePath = $this->buildFilePath('en', $module);
			if (!file_exists($filePath))
			{
				throw new CoreException("Translation file not found: $filePath");
			}
		}

		$data = @parse_ini_file($filePath, true, INI_SCANNER_RAW);

		if (!is_array($data))
			throw new CoreException("Invalid INI file format: $filePath");

		return $data;
	}

	private function buildFilePath(string $languageCode, string $module): string
	{
		return $this->baseDirectory . $languageCode . '/' . $module . '.ini';
	}
}