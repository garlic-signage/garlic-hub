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

use App\Framework\Exceptions\TranslatableModuleException;

/**
 * Decides whether the server may fetch an address which a user has typed in, and names the one IP
 * address the request has to use. Addresses of the own network are not allowed.
 */
class SsrfValidator
{
	/** the name of a host is resolved to one IPv4 address, a host without one is not reachable */
	private readonly \Closure $resolver;

	/**
	 * @param (callable(string): string)|null $resolver gives the IP for a host name, or the name itself when there is none
	 */
	public function __construct(?callable $resolver = null)
	{
		$this->resolver = $resolver !== null ? $resolver(...) : gethostbyname(...);
	}

	/**
	 * @return array{ip: string, host: string, port: int, path: string}
	 * @throws TranslatableModuleException
	 */
	public function validateAndResolveUrl(string $url): array
	{
		$parsed = parse_url($url);

		if (!isset($parsed['scheme'], $parsed['host']))
			throw new TranslatableModuleException('mediapool', 'url_invalid', ['url' => $url]);

		if (!in_array(strtolower($parsed['scheme']), ['https', 'http'], true))
			throw new TranslatableModuleException('mediapool', 'url_invalid', ['url' => $url]);

		$host = $parsed['host'];
		$port = $parsed['port'] ?? (strtolower($parsed['scheme']) === 'https' ? 443 : 80);
		$ip   = ($this->resolver)($host);

		// an IP address as host resolves to itself and is checked like every other
		if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
			throw new TranslatableModuleException('mediapool', 'url_not_resolvable', ['url' => $url]);

		if (!$this->isPublicIp($ip))
			throw new TranslatableModuleException('mediapool', 'url_not_allowed', ['url' => $url]);

		return ['ip' => $ip, 'host' => $host, 'port' => $port, 'path' => $parsed['path'] ?? '/'];
	}

	private function isPublicIp(string $ip): bool
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
			return false;

		// the filter has accepted an IPv4 address, so ip2long() gives a number
		$long = (int) ip2long($ip);

		// not covered by the filter: link local (cloud metadata services), the shared address space of carriers
		// (100.64.0.0/10, where some providers put their metadata services) and multicast
		return ($long & 0xFFFF0000) !== 0xA9FE0000
			&& ($long & 0xFFC00000) !== 0x64400000
			&& ($long & 0xF0000000) !== 0xE0000000;
	}
}
