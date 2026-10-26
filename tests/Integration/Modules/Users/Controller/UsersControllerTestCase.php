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

namespace Tests\Integration\Modules\Users\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\HtmlFields;

/**
 * The base of the tests of the user endpoints: the controllers of the real container with the real services and the
 * database. Users: 1 admin (module admin), 2 anna and 3 bernd (no rights). The controllers keep the input of the request,
 * so every test makes one request.
 */
abstract class UsersControllerTestCase extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadUsers();
	}

	protected function loadUsers(): void
	{
		$this->loadFixture('acl/users.sql');
	}


	/**
	 * @param array<string,mixed>|null $body
	 */
	protected function request(string $method = 'POST', string $path = '/users', ?array $body = []): ServerRequestInterface
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
	protected function json(ResponseInterface $response): array
	{
		$decoded = json_decode((string) $response->getBody(), true);
		static::assertIsArray($decoded, 'The answer is no JSON: ' . $response->getBody());

		return $decoded;
	}

	protected function assertSuccess(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		static::assertTrue($this->json($response)['success'] ?? false);
	}

	protected function assertError(ResponseInterface $response, string $message, int $status = 400): void
	{
		static::assertSame($status, $response->getStatusCode());
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

	protected function page(ResponseInterface $response): PageResponse
	{
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @return array<string,array<string,string>> the attributes of the fields of the form by their names
	 */
	protected function fields(PageResponse $page): array
	{
		$data = $page->getTemplateData();

		return HtmlFields::fromPrepared(['visible' => $data['form_element'] ?? [], 'hidden' => $data['element_hidden'] ?? []]);
	}

	protected function text(string $key, string $module = 'users'): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	protected function databaseValue(string $sql, mixed ...$params): mixed
	{
		return $this->connection->fetchOne($sql, $params);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function userRow(int $UID): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM user_main WHERE UID = ?', [$UID]);
		static::assertIsArray($row);

		return $row;
	}

	protected function countUsers(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main');
	}

	protected function countTokens(int $UID, ?string $purpose = null): int
	{
		if ($purpose === null)
			return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ?', [$UID]);

		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ? AND purpose = ?', [$UID, $purpose]);
	}
}
