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

namespace Tests\Integration\Modules\Player\Controller;

use App\Framework\Localization\Translate\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The base of the tests of the player endpoints: the controllers of the real container with the real services
 * and the database. Users: 1 admin, 2 anna, 3 bernd. Players: 1 and 2 belong to anna, 3 to bernd, 4 to the admin.
 * Playlists: 1 and 3 are master lists of anna, 2 is an internal list of bernd.
 */
abstract class PlayerControllerTestCase extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->loadFixture('playlists/playlists.sql');
	}


	/**
	 * @param array<string,mixed> $body
	 */
	protected function request(string $method, string $path, array $body = []): ServerRequestInterface
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

	protected function text(string $key, string $module = 'player'): string
	{
		return $this->container->get(Translator::class)->translate($key, $module);
	}

	protected function assertError(ResponseInterface $response, string $message): void
	{
		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => $message], $this->json($response));
	}

	protected function setPlayer(int $playerId, string $set, mixed ...$params): void
	{
		$this->connection->executeStatement('UPDATE player SET ' . $set . ' WHERE player_id = ' . $playerId, $params);
	}

	protected function playerField(int $playerId, string $field): mixed
	{
		return $this->connection->fetchOne('SELECT ' . $field . ' FROM player WHERE player_id = ?', [$playerId]);
	}

	protected function countPlayers(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player');
	}
}
