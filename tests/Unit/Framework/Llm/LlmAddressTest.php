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

namespace Tests\Unit\Framework\Llm;

use App\Framework\Llm\LlmAddress;
use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Llm\LlmException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The address of an LLM comes from the user: private networks are allowed (an own Ollama), link-local addresses
 * (metadata of the cloud) and addresses with credentials, query or fragment are not.
 */
class LlmAddressTest extends TestCase
{
	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function urls(): array
	{
		return [
			'https'               => ['https://api.openai.com/v1', true],
			'local ollama'        => ['http://localhost:11434/v1', true],
			'lan'                 => ['http://192.168.1.10:11434/v1', true],
			'ipv6'                => ['http://[::1]:11434/v1', true],
			'ftp'                 => ['ftp://example.com/v1', false],
			'file'                => ['file:///etc/passwd', false],
			'no host'             => ['http:///v1', false],
			'credentials'         => ['http://user:secret@example.com/v1', false],
			'at in path'          => ['http://example.com/@internal', false],
			'query'               => ['http://grafana:3000/api/search?q=', false],
			'empty query'         => ['http://grafana:3000/api/search?', false],
			'fragment'            => ['http://grafana:3000/api/search#', false],
		];
	}

	#[Group('units')]
	#[DataProvider('urls')]
	public function testOnlyAPlainHttpAddressIsValid(string $url, bool $valid): void
	{
		static::assertSame($valid, LlmAddress::isValidUrl($url));
		static::assertSame($valid, LlmClientFactory::isValidUrl($url));
	}

	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function addresses(): array
	{
		return [
			'metadata'            => ['169.254.169.254', true],
			'link-local'          => ['169.254.0.1', true],
			'link-local end'      => ['169.254.255.255', true],
			'before'              => ['169.253.255.255', false],
			'after'               => ['169.255.0.0', false],
			'loopback'            => ['127.0.0.1', false],
			'lan'                 => ['10.0.0.1', false],
			'ipv6 link-local'     => ['fe80::1', true],
			'ipv6 link-local end' => ['febf::1', true],
			'ipv6 after'          => ['fec0::1', false],
			'ipv6 loopback'       => ['::1', false],
			'aws ipv6 metadata'   => ['fd00:ec2::254', true],
			'mapped metadata'     => ['::ffff:169.254.169.254', true],
			'mapped lan'          => ['::ffff:10.0.0.1', false],
			// all four bytes count: from the 13th byte on it would look like fe80::/10
			'mapped fe 80'        => ['::ffff:10.254.128.1', false],
			'no address'          => ['ollama', false],
		];
	}

	#[Group('units')]
	#[DataProvider('addresses')]
	public function testLinkLocalAddressesAreBlocked(string $address, bool $blocked): void
	{
		static::assertSame($blocked, LlmAddress::isBlockedAddress($address));
	}

	/**
	 * A name is checked by its addresses: a name of the attacker can point to the metadata service.
	 */
	#[Group('units')]
	public function testANameIsBlockedWhenOneOfItsAddressesIsLinkLocal(): void
	{
		$address = new LlmAddress(static fn(string $host): array => match ($host)
		{
			'metadata.evil.test' => ['10.0.0.1', '169.254.169.254'],
			'ollama'             => ['172.18.0.5'],
			default              => []
		});

		static::assertTrue($address->isBlocked('http://metadata.evil.test/v1'));
		static::assertTrue($address->isBlocked('http://169.254.169.254/latest'));
		static::assertTrue($address->isBlocked('http://[fe80::1]:8080/v1'));
		static::assertFalse($address->isBlocked('http://ollama:11434/v1'));
		static::assertFalse($address->isBlocked('http://unknown.test/v1'), 'a name without address fails at the request');
	}

	#[Group('units')]
	public function testTheFactoryCreatesNoClientForABlockedAddress(): void
	{
		$requests = new MockHandler([]);
		$factory  = new LlmClientFactory(new Client(['handler' => HandlerStack::create($requests)]), static fn(string $host): array => $host === 'evil.test' ? ['169.254.169.254'] : []);

		foreach (['http://evil.test/v1', 'http://169.254.169.254/v1'] as $url)
		{
			foreach ([LlmConnection::API_OPENAI, LlmConnection::API_AUTOMATIC1111] as $api)
			{
				try
				{
					$factory->create(new LlmConnection($api, $url, 'model'));
					static::fail('Expected LlmException for ' . $url);
				}
				catch (LlmException $e)
				{
					static::assertSame('llm_blocked_address', $e->getTranslationKey());
				}
			}
		}
		// a private address stays allowed: no exception
		$factory->create(new LlmConnection(LlmConnection::API_OPENAI, 'http://ollama:11434/v1', 'model'));
		$this->addToAssertionCount(1);
	}
}
