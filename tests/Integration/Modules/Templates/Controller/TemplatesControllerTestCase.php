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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\Localization\Translate\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The base of the tests of the template endpoints: the controllers of the real container with the real services
 * and the database. Users: 1 admin, 2 anna, 3 bernd. Templates: 1 of the admin (public, with content), 2 of anna,
 * 3 of bernd (private, without content). In the edge edition only the admin may use templates.
 * The template 7 is used by two items of the playlists, the templates 1 to 3 are not used.
 */
abstract class TemplatesControllerTestCase extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
	}


	/**
	 * @param array<string,mixed> $body
	 */
	protected function request(string $method = 'POST', string $path = '/templates', array $body = []): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest($method, $path)->withParsedBody($body);
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

	protected function assertRedirect(ResponseInterface $response, string $location, ?string $error = null): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
		if ($error !== null)
			static::assertSame([$error], $this->flash('error'));
	}

	/**
	 * @return list<string> the messages for the next request (a redirect) and for this one (a form which is shown again)
	 */
	protected function flash(string $type): array
	{
		/** @var array<string,list<string>> $next */
		$next = $_SESSION['slimFlash'] ?? [];

		return array_values(array_merge($next[$type] ?? [], $this->container->get(Messages::class)->getMessage($type) ?? []));
	}

	protected function text(string $key, string $module = 'templates'): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	protected function databaseValue(string $sql, mixed ...$params): mixed
	{
		return $this->connection->fetchOne($sql, $params);
	}

	protected function countTemplates(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates');
	}
}
