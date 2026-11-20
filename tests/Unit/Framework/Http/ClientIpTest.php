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

namespace Tests\Unit\Framework\Http;

use App\Framework\Config\IniConfigLoader;
use App\Framework\Http\ClientIp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\RecordingLogger;

/**
 * The address of the visitor behind trusted reverse proxies. The default of the trusted proxies comes from
 * config/settings/config_main.ini, so a change there is tested, too.
 */
#[Group('units')]
class ClientIpTest extends TestCase
{
	// Docker, nginx on the same host and internal proxies
	private const string PRIVATE_NETWORKS = '127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7';

	private static function defaultProxies(): string
	{
		$value = new IniConfigLoader(__DIR__ . '/../../../../config/settings')->load('main')['Proxy']['trusted_proxies'] ?? '';
		static::assertIsString($value);

		return IniConfigLoader::withoutComment($value);
	}

	/**
	 * @param array<string,mixed> $server
	 */
	private static function resolve(array $server, ?string $proxies = null): string
	{
		return new ClientIp($proxies ?? self::PRIVATE_NETWORKS, new NullLogger())->resolve($server);
	}

	/**
	 * @return array<string, array{string, string, string}> REMOTE_ADDR, X-Forwarded-For and the visitor
	 */
	public static function requests(): array
	{
		return [
			'straight from the internet, the header is ignored' => ['203.0.113.7', '198.51.100.1', '203.0.113.7'],
			'Docker'                                            => ['172.18.0.1', '203.0.113.7', '203.0.113.7'],
			'nginx on the same host'                            => ['127.0.0.1', '203.0.113.7', '203.0.113.7'],
			'IPv6 loopback'                                     => ['::1', '2001:db8::7', '2001:db8::7'],
			'IPv4 on a dual stack socket'                       => ['::ffff:127.0.0.1', '203.0.113.7', '203.0.113.7'],
			'an internal proxy behind another one'              => ['10.0.0.2', '203.0.113.7, 192.168.1.5', '203.0.113.7'],
			// the visitor wrote the first one himself: only the hop the proxy added counts
			'a forged hop on the left'                          => ['10.0.0.2', '1.2.3.4, 203.0.113.7', '203.0.113.7'],
			'a visitor in the private network'                  => ['10.0.0.2', '192.168.1.5', '192.168.1.5'],
			'a proxy without the header'                        => ['10.0.0.2', '', '10.0.0.2'],
			'no address in the header'                          => ['10.0.0.2', 'unknown', '10.0.0.2'],
			'no address between proxies'                        => ['10.0.0.2', '203.0.113.7, garbage, 192.168.1.5', '192.168.1.5'],
			'no REMOTE_ADDR'                                    => ['', '203.0.113.7', 'unknown'],
		];
	}

	#[DataProvider('requests')]
	public function testTheVisitor(string $remote, string $forwarded, string $visitor): void
	{
		$server = array_filter(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => $forwarded], static fn(string $value) => $value !== '');

		static::assertSame($visitor, self::resolve($server));
	}

	/**
	 * Without a proxy in front a visitor in the own network must not give another address.
	 */
	public function testByDefaultNoProxyIsTrusted(): void
	{
		static::assertSame('', self::defaultProxies());
		static::assertSame('192.168.1.5', self::resolve(['REMOTE_ADDR' => '192.168.1.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], self::defaultProxies()));
	}

	public function testWithoutTrustedProxiesTheHeaderIsIgnored(): void
	{
		static::assertSame('127.0.0.1', self::resolve(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], ''));
	}

	/**
	 * A public proxy in front (Cloudflare) is configured with its networks.
	 */
	public function testAConfiguredPublicProxy(): void
	{
		$proxies = '173.245.48.0/20, 104.16.0.0/13, 2400:cb00::/32';

		static::assertSame('203.0.113.7', self::resolve(['REMOTE_ADDR' => '104.23.1.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], $proxies));
		static::assertSame('2001:db8::7', self::resolve(['REMOTE_ADDR' => '2400:cb00::1', 'HTTP_X_FORWARDED_FOR' => '2001:db8::7'], $proxies));
		static::assertSame('104.24.0.1', self::resolve(['REMOTE_ADDR' => '104.24.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], $proxies), 'next to the network');
		static::assertSame('203.0.113.7', self::resolve(['REMOTE_ADDR' => '173.245.63.255', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], $proxies), 'the last address of the network');
		static::assertSame('173.245.64.0', self::resolve(['REMOTE_ADDR' => '173.245.64.0', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], $proxies), 'the one after it');
	}

	public function testASingleAddressIsTrustedAlone(): void
	{
		static::assertSame('203.0.113.7', self::resolve(['REMOTE_ADDR' => '198.51.100.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], '198.51.100.1'));
		static::assertSame('198.51.100.2', self::resolve(['REMOTE_ADDR' => '198.51.100.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], '198.51.100.1'));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function rejected(): array
	{
		return [
			'everybody IPv4'      => ['0.0.0.0/0'],
			'everybody IPv6'      => ['::/0'],
			'a huge public IPv4'  => ['64.0.0.0/2'],
			'a huge public IPv6'  => ['2000::/3'],
			'no address'          => ['proxy.example.com'],
			'a prefix too long'   => ['10.0.0.0/33'],
			'no number as prefix' => ['10.0.0.0/x'],
		];
	}

	/**
	 * The broken entry is logged and ignored, the others count.
	 */
	#[DataProvider('rejected')]
	public function testTooLargeOrBrokenEntriesAreIgnoredAndLogged(string $entry): void
	{
		$logger   = new RecordingLogger();
		$clientIp = new ClientIp($entry . ', 127.0.0.1', $logger);

		static::assertCount(1, $logger->messages['warning'] ?? []);
		static::assertStringEndsWith(': ' . $entry, $logger->messages['warning'][0]);
		static::assertSame('8.8.8.8', $clientIp->resolve(['REMOTE_ADDR' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
		static::assertSame('203.0.113.7', $clientIp->resolve(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
	}

	/**
	 * The limits: a public /8 (IPv6 /16) is allowed, a private network may be larger (fc00::/7).
	 */
	public function testThePrivateNetworksAndNetworksAtTheLimitAreAccepted(): void
	{
		$logger = new RecordingLogger();

		new ClientIp(self::PRIVATE_NETWORKS . ', 64.0.0.0/8, 2000::/16', $logger);

		static::assertSame([], $logger->messages);
	}
}
