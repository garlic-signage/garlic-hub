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

namespace Tests\Integration\Modules\Mediapool\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The base of the tests of the mediapool endpoints: the controllers of the real container with the real
 * services, the database and the session. A request is a request of the browser, the answer is read as JSON.
 * Users: 1 admin, 2 anna (directories 7, 8 Fotos, 9 Archiv), 3 bernd (10, 11 Privat, 12 Geteilt).
 */
abstract class ControllerTestCase extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
	}


	/**
	 * A request with the body the browser sends, as JSON or as form: the values are what the parser gives.
	 *
	 * @param array<string,mixed> $body
	 * @param array<string,mixed> $query
	 */
	protected function request(string $method, array $body = [], array $query = []): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest($method, '/async/mediapool')->withParsedBody($body)->withQueryParams($query);
	}


	/**
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	protected function json(ResponseInterface $response): array
	{
		$decoded = json_decode((string) $response->getBody(), true);
		static::assertIsArray($decoded, 'The answer is no JSON: ' . $response->getBody());

		return $decoded;
	}

	protected function newResponse(): Response
	{
		return new Response();
	}

	protected function assertSuccess(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		static::assertTrue($this->json($response)['success'] ?? false);
	}

	protected function assertError(ResponseInterface $response, string $message): void
	{
		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => $message], $this->json($response));
	}

	protected function databaseValue(string $sql, mixed ...$params): mixed
	{
		return $this->connection->fetchOne($sql, $params);
	}
}
