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


namespace App\Modules\Mediapool\Utils;

class SsrfValidator
{
	/**
	 * @return array{ip: string, host: string, port: int, path: string}
	 */
	public function validateAndResolveUrl(string $url): array
	{
		$parsed = parse_url($url);

		if (!isset($parsed['scheme'], $parsed['host']))
			throw new \InvalidArgumentException('Invalid URL.');

		if (!in_array($parsed['scheme'], ['https', 'http'], true))
			throw new \InvalidArgumentException('URL scheme not allowed.');

		$host = $parsed['host'];
		$port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
		$ip   = gethostbyname($host);

		if ($ip === $host)
			throw new \InvalidArgumentException('DNS resolution failed.');

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
			throw new \InvalidArgumentException('URL resolves to forbidden IP range.');

		if (str_starts_with($ip, '169.254.'))
			throw new \InvalidArgumentException('URL resolves to forbidden IP range.');

		return ['ip' => $ip, 'host' => $host, 'port' => $port, 'path' => $parsed['path'] ?? '/'];
	}
}