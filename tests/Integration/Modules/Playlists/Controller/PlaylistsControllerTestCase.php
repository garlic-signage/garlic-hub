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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The base of the tests of the playlist endpoints: the controllers of the real container with the real services
 * and the database. Users: 1 admin, 2 anna, 3 bernd. Playlist 1 (anna) has the items 1 image, 2 video, 3 the playlist 2,
 * 4 pdf and 5 template, playlist 2 (bernd, time limit 600) has the items 6 and 7, playlist 3 (anna) is empty.
 */
abstract class PlaylistsControllerTestCase extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"size\":5000,\"duration\":40}' WHERE checksum = 'checksum-video'");
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"size\":300}' WHERE checksum <> 'checksum-video'");
	}

	protected function csrf(): string
	{
		return $this->container->get(CsrfToken::class)->getToken();
	}

	/**
	 * @param array<string,mixed> $body
	 */
	protected function request(string $method = 'POST', string $path = '/async/playlists', array $body = []): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest($method, $path)->withParsedBody($body);
	}

	/**
	 * @param array<string,mixed> $body
	 * @return array<string,mixed>
	 */
	protected function withToken(array $body): array
	{
		return ['csrf_token' => $this->csrf()] + $body;
	}

	protected function newResponse(): Response
	{
		return new Response();
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function json(?ResponseInterface $response): array
	{
		static::assertNotNull($response);
		$decoded = json_decode((string) $response->getBody(), true);
		static::assertIsArray($decoded, 'The answer is no JSON: ' . $response->getBody());

		return $decoded;
	}

	protected function assertSuccess(?ResponseInterface $response): void
	{
		static::assertNotNull($response);
		static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		static::assertTrue($this->json($response)['success'] ?? false);
	}

	protected function assertError(?ResponseInterface $response, string $message): void
	{
		static::assertNotNull($response);
		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => $message], $this->json($response));
	}

	protected function text(string $key, string $module = 'playlists'): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	protected function databaseValue(string $sql, mixed ...$params): mixed
	{
		return $this->connection->fetchOne($sql, $params);
	}
}
