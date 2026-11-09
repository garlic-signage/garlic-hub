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

namespace App\Framework\Llm;

use Closure;

/**
 * The address of an LLM comes from the user (own connections) or the admin. Private networks stay allowed: an Ollama
 * in the LAN or on the server is the usual own model. Never allowed:
 * - credentials, a query or a fragment in the address: the API path is appended to it, with them any path of any
 *   service could be asked and its answer read back as list of models;
 * - link-local addresses, also by a name which resolves to one: the metadata service of the cloud (169.254.169.254)
 *   gives the credentials of the server.
 */
final readonly class LlmAddress
{
	/** the IPv6 address of the metadata service of AWS, not link-local */
	private const string AWS_METADATA_IPV6 = 'fd00:ec2::254';

	/** @var Closure(string):list<string> the addresses of a host name */
	private Closure $resolve;

	/**
	 * @param (Closure(string):list<string>)|null $resolve for tests, by default the DNS
	 */
	public function __construct(?Closure $resolve = null)
	{
		$this->resolve = $resolve ?? self::resolveByDns(...);
	}

	public static function isValidUrl(string $url): bool
	{
		$scheme = parse_url($url, PHP_URL_SCHEME);
		$host   = parse_url($url, PHP_URL_HOST);
		if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '')
			return false;

		return strpbrk($url, '?#@') === false;
	}

	/**
	 * The host or one of its addresses is link-local (or the metadata service).
	 */
	public function isBlocked(string $url): bool
	{
		$host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
		$addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);
		foreach ($addresses as $address)
		{
			if (self::isBlockedAddress($address))
				return true;
		}

		return false;
	}

	public static function isBlockedAddress(string $address): bool
	{
		$binary = @inet_pton($address);
		if ($binary === false)
			return false;

		// an IPv4 address mapped into IPv6 (::ffff:a.b.c.d) is the IPv4 address
		if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10) . "\xff\xff"))
			$binary = substr($binary, 12);

		if (strlen($binary) === 4)
			return $binary[0] === "\xa9" && $binary[1] === "\xfe"; // 169.254.0.0/16

		// fe80::/10
		if ($binary[0] === "\xfe" && (ord($binary[1]) & 0xc0) === 0x80)
			return true;

		return $binary === inet_pton(self::AWS_METADATA_IPV6);
	}

	/**
	 * @return list<string>
	 */
	private static function resolveByDns(string $host): array
	{
		$addresses = gethostbynamel($host) ?: [];
		foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record)
		{
			if (is_string($record['ipv6'] ?? null))
				$addresses[] = $record['ipv6'];
		}

		return $addresses;
	}
}
