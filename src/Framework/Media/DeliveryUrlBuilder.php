<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program. If not, see <https://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Framework\Media;

/**
 * Builds the URLs public/delivery.php delivers files to players with.
 *
 * The file name stays in the path, not in the query: garlic-player takes the
 * extension of its cache file from the path.
 */
class DeliveryUrlBuilder
{
	private const string ENDPOINT = '/delivery.php';

	private readonly string $serverUrl;

	public function __construct(string $serverUrl)
	{
		// a slash at the end of the configured url must not give a double slash in the path
		$this->serverUrl = rtrim($serverUrl, '/');
	}

	public function mediapoolUrl(string $checksum, string $extension): string
	{
		return $this->serverUrl . self::ENDPOINT . '/' . $checksum . '.' . $extension;
	}

	/**
	 * @param string $name the name of the rendered image of a template item, see TemplateItemName
	 */
	public function templateUrl(string $name, string $extension): string
	{
		return $this->serverUrl . self::ENDPOINT . '/tpl/' . $name . '.' . $extension;
	}
}
