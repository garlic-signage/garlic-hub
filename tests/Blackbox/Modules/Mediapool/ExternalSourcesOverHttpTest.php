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

namespace Tests\Blackbox\Modules\Mediapool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Media from an address and the search at the stock platforms: the server must not fetch what it should not
 * (itself, the network behind it, files, cloud metadata) and passes only a few hosts and headers on.
 */
#[Group('blackbox')]
class ExternalSourcesOverHttpTest extends MediapoolTestCase
{
	/**
	 * @return array<string,array{string}>
	 */
	public static function addressesOfTheServerAndItsNetwork(): array
	{
		return [
			'localhost'              => ['http://localhost/x.png'],
			'loopback'               => ['http://127.0.0.1/x.png'],
			'loopback with a port'   => ['http://127.0.0.1:80/x.png'],
			'loopback as a number'   => ['http://2130706433/x.png'],
			'loopback in hex'        => ['http://0x7f000001/x.png'],
			'loopback short'         => ['http://127.1/x.png'],
			'loopback in octal'      => ['http://0177.0.0.1/x.png'],
			'zero address'           => ['http://0.0.0.0/x.png'],
			'ipv6 loopback'          => ['http://[::1]/x.png'],
			'ipv6 mapped'            => ['http://[::ffff:127.0.0.1]/x.png'],
			'private 10'             => ['http://10.0.0.1/x.png'],
			'private 172'            => ['http://172.16.0.1/x.png'],
			'private 192'            => ['http://192.168.1.1/x.png'],
			'cloud metadata'         => ['http://169.254.169.254/latest/meta-data/'],
			'carrier space'          => ['http://100.64.0.1/x.png'],
			'with a user'            => ['http://user:pass@127.0.0.1/x.png'],
			'a file'                 => ['file:///etc/passwd'],
			'gopher'                 => ['gopher://127.0.0.1:6379/_PING'],
			'ftp'                    => ['ftp://127.0.0.1/x.png'],
			'dict'                   => ['dict://127.0.0.1:11211/stat'],
			'no scheme'              => ['127.0.0.1/x.png'],
			'only a scheme'          => ['http://'],
			'javascript'             => ['javascript:alert(1)'],
			'data'                   => ['data:image/png;base64,iVBORw0KGgo='],
		];
	}

	#[DataProvider('addressesOfTheServerAndItsNetwork')]
	public function testTheServerFetchesNothingFromItselfOrItsNetwork(string $address): void
	{
		$before = $this->files();

		$response = $this->anna->request('POST', '/async/mediapool/uploadFromUrl', ['form_params' => [
			'node_id'       => '8',
			'external_link' => $address,
			'csrf_token'    => $this->anna->csrfToken('/mediapool'),
		]]);

		static::assertFalse($this->json($response)['success'] ?? true, $address);
		static::assertSame([], $this->newFilesSince($before));
		static::assertSame(0, $this->mediaCount());
	}

	public function testAnAddressNeedsTheCsrfTokenAndAFolderOfTheUser(): void
	{
		$noToken = $this->anna->request('POST', '/async/mediapool/uploadFromUrl', ['form_params' => ['node_id' => '8', 'external_link' => 'http://127.0.0.1/x.png']]);
		$foreign = $this->anna->request('POST', '/async/mediapool/uploadFromUrl', ['form_params' => ['node_id' => '11', 'external_link' => 'https://example.com/x.png', 'csrf_token' => $this->anna->csrfToken('/mediapool')]]);

		static::assertFalse($this->json($noToken)['success'] ?? true);
		static::assertFalse($this->json($foreign)['success'] ?? true);
		static::assertSame(0, $this->mediaCount());
	}

	public function testAListInsteadOfAnAddressIsNoAddress(): void
	{
		$response = $this->anna->request('POST', '/async/mediapool/uploadFromUrl', ['form_params' => [
			'node_id'       => '8',
			'external_link' => ['http://127.0.0.1/x.png'],
			'csrf_token'    => $this->anna->csrfToken('/mediapool'),
		]]);

		static::assertFalse($this->json($response)['success'] ?? true);
	}

	/**
	 * @return array<string,array{string,array<string,string>}> the address and the headers of the search
	 */
	public static function searchesWhichAreNotAllowed(): array
	{
		return [
			'a foreign host'            => ['https://evil.example.com/api', ['Authorization' => 'x']],
			'a host which starts like a platform' => ['https://pixabay.com.evil.com/api', ['Authorization' => 'x']],
			'a platform as the user'    => ['https://pixabay.com@evil.example.com/api', []],
			'a platform in the query'   => ['https://evil.example.com/?https://pixabay.com', []],
			'a platform in the path'    => ['https://evil.example.com/pixabay.com', []],
			'without https'             => ['http://pixabay.com/api', []],
			'the server itself'         => ['https://127.0.0.1/api', []],
			'a file'                    => ['file:///etc/passwd', []],
			'a cookie header'           => ['https://pixabay.com/api', ['Cookie' => 'GhSid=x']],
			'a host header'             => ['https://pixabay.com/api', ['Host' => 'evil.example.com']],
			'a header with a line break' => ['https://pixabay.com/api', ['Authorization' => "x\r\nHost: evil"]],
		];
	}

	/**
	 * @param array<string,string> $headers
	 */
	#[DataProvider('searchesWhichAreNotAllowed')]
	public function testTheSearchOnlyGoesToTheStockPlatforms(string $address, array $headers): void
	{
		$response = $this->anna->request('POST', '/async/mediapool/searchStockImages', ['form_params' => [
			'api_url'    => $address,
			'headers'    => $headers,
			'csrf_token' => $this->anna->csrfToken('/mediapool'),
		]]);

		$answer = json_decode((string) $response->getBody(), true);
		static::assertIsArray($answer, (string) $response->getBody());
		static::assertFalse($answer['success'] ?? true, $address . ' ' . json_encode($headers, JSON_THROW_ON_ERROR));
	}

	public function testAGuestCanNotUseTheServerAsProxy(): void
	{
		$response = $this->browser()->request('POST', '/async/mediapool/searchStockImages', ['form_params' => ['api_url' => 'https://pixabay.com/api', 'headers' => []]]);

		static::assertGreaterThanOrEqual(400, $response->getStatusCode());
	}
}
