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

namespace App\Framework\System;

/**
 * The version of the hub: VERSION is in the repository and changes only with a release (1.3.0-dev),
 * VERSION_BUILD is written by the deploy script of the test server with the number of all commits:
 * 1.3.0-dev (Build: 1234). Releases and local installations have no VERSION_BUILD, there it stays 1.3.0-dev.
 */
readonly class AppVersion
{
	public function __construct(private string $systemDir)
	{}

	public function getVersion(): string
	{
		$version = $this->readFirstLine('VERSION');
		if ($version === '')
			return '';

		$build = $this->readFirstLine('VERSION_BUILD');
		if (!ctype_digit($build))
			return $version;

		return $version . ' (Build: ' . $build . ')';
	}

	private function readFirstLine(string $fileName): string
	{
		$path = $this->systemDir . '/' . $fileName;
		if (!is_file($path) || !is_readable($path))
			return '';

		$content = file_get_contents($path);
		if ($content === false)
			return '';

		return trim(strtok($content, "\n") ?: '');
	}
}
