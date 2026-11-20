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

namespace App\Framework\Http;

use Psr\Log\LoggerInterface;

/**
 * The address of the visitor for the login limits and the logs. Behind a reverse proxy REMOTE_ADDR is the proxy:
 * then the address comes from X-Forwarded-For, but only when REMOTE_ADDR is a trusted proxy. Its hops are read from
 * the right, the first one which is no trusted proxy is the visitor. A request straight from the internet has a
 * public REMOTE_ADDR, so nobody outside can give another address in the header.
 *
 * No proxy is trusted by default (config main, trusted_proxies): without a proxy a visitor in the own network could
 * give another address. Networks which trust almost everybody are ignored and logged.
 */
final readonly class ClientIp
{
	// public networks larger than these trust almost everybody: Cloudflare has /12 and /29 at most
	private const int MIN_PUBLIC_PREFIX_IPV4 = 8;
	private const int MIN_PUBLIC_PREFIX_IPV6 = 16;
	private const array PRIVATE_NETWORKS     = ['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1', 'fc00::/7'];
	private const string IPV4_MAPPED_PREFIX  = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

	/** @var list<array{string, int}> the address in bytes and the length of the prefix */
	private array $trustedNetworks;

	/**
	 * @param string $trustedProxies addresses or networks (CIDR), separated by commas
	 */
	public function __construct(string $trustedProxies, LoggerInterface $logger)
	{
		$networks = [];
		foreach (explode(',', $trustedProxies) as $entry)
		{
			$entry = trim($entry);
			if ($entry === '')
				continue;

			$network = self::parseNetwork($entry);
			if ($network === null)
				$logger->warning('Trusted proxy ignored, it is no address or network: ' . $entry);
			elseif (self::isTooLarge($network))
				$logger->warning('Trusted proxy ignored, the public network is too large: ' . $entry);
			else
				$networks[] = $network;
		}
		$this->trustedNetworks = $networks;
	}

	/**
	 * @param array<string,mixed> $server $_SERVER
	 */
	public function resolve(array $server): string
	{
		$remote = is_string($server['REMOTE_ADDR'] ?? null) ? $server['REMOTE_ADDR'] : '';
		if ($remote === '')
			return 'unknown';
		if (!$this->isTrusted($remote))
			return $remote;

		$forwarded = is_string($server['HTTP_X_FORWARDED_FOR'] ?? null) ? $server['HTTP_X_FORWARDED_FOR'] : '';
		$client    = $remote;
		foreach (array_reverse(explode(',', $forwarded)) as $hop)
		{
			$hop = trim($hop);
			// no address: the hops before it are not known, the last trusted proxy is the one who sent the request
			if (self::toBytes($hop) === null)
				break;

			$client = $hop;
			if (!$this->isTrusted($hop))
				break;
		}

		return $client;
	}

	private function isTrusted(string $address): bool
	{
		$bytes = self::toBytes($address);
		if ($bytes === null)
			return false;

		// a dual stack socket gives IPv4 as ::ffff:10.0.0.1
		if (strlen($bytes) === 16 && str_starts_with($bytes, self::IPV4_MAPPED_PREFIX))
			$bytes = substr($bytes, 12);

		foreach ($this->trustedNetworks as $network)
		{
			if (self::contains($network, $bytes))
				return true;
		}

		return false;
	}

	/**
	 * @return array{string, int}|null
	 */
	private static function parseNetwork(string $entry): ?array
	{
		$parts = explode('/', $entry, 2);
		$bytes = self::toBytes($parts[0]);
		if ($bytes === null)
			return null;

		$bits = strlen($bytes) * 8;
		if (!isset($parts[1]))
			return [$bytes, $bits];

		if (!ctype_digit($parts[1]) || (int) $parts[1] > $bits)
			return null;

		return [$bytes, (int) $parts[1]];
	}

	/**
	 * A private network may be large (fc00::/7), a public one not.
	 *
	 * @param array{string, int} $network
	 */
	private static function isTooLarge(array $network): bool
	{
		[$bytes, $prefix] = $network;
		$minimum = strlen($bytes) === 4 ? self::MIN_PUBLIC_PREFIX_IPV4 : self::MIN_PUBLIC_PREFIX_IPV6;
		if ($prefix >= $minimum)
			return false;

		foreach (self::PRIVATE_NETWORKS as $private)
		{
			$privateNetwork = self::parseNetwork($private);
			if ($privateNetwork !== null && $privateNetwork[1] <= $prefix && self::contains($privateNetwork, $bytes))
				return false;
		}

		return true;
	}

	/**
	 * @param array{string, int} $network
	 */
	private static function contains(array $network, string $bytes): bool
	{
		[$networkBytes, $prefix] = $network;
		if (strlen($networkBytes) !== strlen($bytes))
			return false;

		$fullBytes = intdiv($prefix, 8);
		if (substr($networkBytes, 0, $fullBytes) !== substr($bytes, 0, $fullBytes))
			return false;

		$restBits = $prefix % 8;
		if ($restBits === 0)
			return true;

		$mask = (0xff << (8 - $restBits)) & 0xff;

		return (ord($networkBytes[$fullBytes]) & $mask) === (ord($bytes[$fullBytes]) & $mask);
	}

	private static function toBytes(string $address): ?string
	{
		if (filter_var($address, FILTER_VALIDATE_IP) === false)
			return null;

		$bytes = inet_pton($address);

		// @infection-ignore-all a valid address always converts
		return $bytes !== false ? $bytes : null;
	}
}
