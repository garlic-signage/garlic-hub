<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Utils\SsrfValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The names are resolved by a table of the test, so no test needs a network.
 */
class SsrfValidatorTest extends TestCase
{
	/** @var array<string,string> host => IP */
	private const array DNS = [
		'media.example.com' => '93.184.216.34',
		'intranet.example'  => '10.0.0.5',
		'router.example'    => '192.168.1.1',
		'docker.example'    => '172.17.0.2',
		'loopback.example'  => '127.0.0.1',
		'metadata.example'  => '169.254.169.254',
		'carrier.example'   => '100.100.100.200',
		'zero.example'      => '0.0.0.0',
		'multicast.example' => '224.0.0.1',
		'broadcast.example' => '255.255.255.255',
	];

	private function validator(): SsrfValidator
	{
		return new SsrfValidator(static fn(string $host): string => self::DNS[$host] ?? $host);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidUrlProvider(): array
	{
		return [
			'no host'                  => ['not a url'],
			'empty'                    => [''],
			'only a scheme'            => ['http://'],
			'ftp'                      => ['ftp://media.example.com/file.jpg'],
			'file'                     => ['file:///etc/passwd'],
			'gopher'                   => ['gopher://media.example.com/'],
			'javascript'               => ['javascript:alert(1)'],
			'data url'                 => ['data:text/plain;base64,AAAA'],
			'no scheme'                => ['//media.example.com/a.jpg'],
			'upper case file'          => ['FILE:///etc/passwd'],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidUrlProvider')]
	public function testAnUrlWhichIsNoHttpAddressIsInvalid(string $url): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('url_invalid');

		$this->validator()->validateAndResolveUrl($url);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function notAllowedProvider(): array
	{
		return [
			'private 10'                => ['http://intranet.example/a.jpg'],
			'private 192.168'           => ['https://router.example/a.jpg'],
			'private 172.16/12'         => ['http://docker.example/a.jpg'],
			'loopback'                  => ['http://loopback.example/a.jpg'],
			'cloud metadata'            => ['http://metadata.example/latest/meta-data/'],
			'shared address space'      => ['http://carrier.example/'],
			'this network'              => ['http://zero.example/'],
			'multicast'                 => ['http://multicast.example/'],
			'broadcast'                 => ['http://broadcast.example/'],
			'loopback as address'       => ['http://127.0.0.1/a.jpg'],
			'loopback with port'        => ['http://127.0.0.1:8080/admin'],
			'private as address'        => ['http://10.1.2.3/a.jpg'],
			'metadata as address'       => ['http://169.254.169.254/latest/meta-data/'],
			'shared space as address'   => ['http://100.64.0.1/'],
			'user info trick'           => ['http://media.example.com@loopback.example/a.jpg'],
		];
	}

	#[Group('units')]
	#[DataProvider('notAllowedProvider')]
	public function testAnAddressOfTheOwnNetworkIsNotAllowed(string $url): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('url_not_allowed');

		$this->validator()->validateAndResolveUrl($url);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function notResolvableProvider(): array
	{
		return [
			'unknown host'  => ['http://nowhere.example/a.jpg'],
			'ipv6 literal'  => ['http://[::1]/a.jpg'],
			'ipv6 public'   => ['http://[2001:db8::1]/a.jpg'],
		];
	}

	#[Group('units')]
	#[DataProvider('notResolvableProvider')]
	public function testAHostWithoutAnIpv4AddressIsNotResolvable(string $url): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('url_not_resolvable');

		$this->validator()->validateAndResolveUrl($url);
	}

	/**
	 * @throws TranslatableModuleException
	 */
	#[Group('units')]
	public function testAPublicAddressGivesTheIpToPin(): void
	{
		static::assertSame(
			['ip' => '93.184.216.34', 'host' => 'media.example.com', 'port' => 443, 'path' => '/img/a.jpg'],
			$this->validator()->validateAndResolveUrl('https://media.example.com/img/a.jpg?size=big')
		);
	}

	/**
	 * @return array<string, array{string,int,string}>
	 */
	public static function portAndPathProvider(): array
	{
		return [
			'http default'    => ['http://media.example.com/a.jpg', 80, '/a.jpg'],
			'https default'   => ['https://media.example.com/a.jpg', 443, '/a.jpg'],
			'own port'        => ['http://media.example.com:8080/a.jpg', 8080, '/a.jpg'],
			'no path'         => ['https://media.example.com', 443, '/'],
			'upper case host' => ['HTTPS://media.example.com/a.jpg', 443, '/a.jpg'],
		];
	}

	/**
	 * @throws TranslatableModuleException
	 */
	#[Group('units')]
	#[DataProvider('portAndPathProvider')]
	public function testPortAndPathAreTakenFromTheUrl(string $url, int $port, string $path): void
	{
		$result = $this->validator()->validateAndResolveUrl($url);

		static::assertSame($port, $result['port']);
		static::assertSame($path, $result['path']);
		static::assertSame('93.184.216.34', $result['ip']);
	}

	/**
	 * A public address as host needs no name server.
	 *
	 * @throws TranslatableModuleException
	 */
	#[Group('units')]
	public function testAPublicIpAddressIsAllowed(): void
	{
		static::assertSame('93.184.216.34', $this->validator()->validateAndResolveUrl('http://93.184.216.34/a.jpg')['ip']);
	}

	/**
	 * Without an own resolver the system is asked: localhost is always the own machine.
	 */
	#[Group('units')]
	public function testTheSystemResolverKnowsLocalhost(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('url_not_allowed');

		new SsrfValidator()->validateAndResolveUrl('http://localhost/a.jpg');
	}
}
