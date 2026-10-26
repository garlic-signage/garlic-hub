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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;
use Tests\Blackbox\Support\Browser;

/**
 * Every route which can change something (POST, PUT, PATCH, DELETE) refuses a request without the CSRF token of the
 * session, also a route which is added later: the routes are read from config/route.php. The admin sends them, he
 * may do everything, so only the token decides. /api is left out, OAuth clients call it without a session.
 */
#[Group('blackbox')]
class CsrfOverHttpTest extends BlackboxTestCase
{
	private Browser $admin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
		$this->loadFixture('playlists/items.sql');

		$this->admin = $this->browser();
		$this->admin->login('admin', 'Test1234!');
	}

	/**
	 * @return array<string,array{string,string}> method and path, placeholders replaced by 1
	 */
	public static function changingRoutes(): array
	{
		$lines = file(__DIR__ . '/../../../config/route.php', FILE_IGNORE_NEW_LINES) ?: [];
		$group  = '';
		$routes = [];
		foreach ($lines as $line)
		{
			if (preg_match('/\$app->group\(\'([^\']*)\'/', $line, $match))
				$group = $match[1];

			if (str_starts_with(ltrim($line), '//') || !preg_match('/\$group->(post|put|patch|delete)\(\'([^\']+)\'/', $line, $match))
				continue;

			if ($group === '/api')
				continue;

			$path = preg_replace(['/\[[^\]]*\]/', '/\{[^}]+\}/'], ['', '1'], $group . $match[2]);
			$routes[strtoupper($match[1]) . ' ' . $path] = [strtoupper($match[1]), (string) $path];
		}

		return $routes;
	}

	public function testTheRoutesAreFound(): void
	{
		$routes = self::changingRoutes();

		static::assertGreaterThan(30, count($routes));
		static::assertArrayHasKey('DELETE /async/playlists', $routes);
		static::assertArrayHasKey('POST /users/edit', $routes);
	}

	/**
	 * @return array<string,string> the content of every table, to see that nothing changed
	 */
	private function database(): array
	{
		$tables = $this->connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
		$content = [];
		foreach ($tables as $table)
			$content[(string) $table] = md5(serialize($this->connection->fetchAllAssociative('SELECT * FROM "' . $table . '"')));

		return $content;
	}

	/**
	 * A logged-in user is sent away from the login before any check: the login is tried by a guest with a session.
	 */
	private function visitorFor(string $path): Browser
	{
		if ($path !== '/login')
			return $this->admin;

		$guest = $this->browser();
		$guest->get('/login');

		return $guest;
	}

	/**
	 * @return array<string,array{string,string,array<string,string>}>
	 */
	public static function requestsWithoutToken(): array
	{
		$requests = [];
		foreach (self::changingRoutes() as $name => [$method, $path])
		{
			$requests[$name . ' without token'] = [$method, $path, []];
			$requests[$name . ' with a forged one'] = [$method, $path, ['csrf_token' => 'forged']];
		}

		return $requests;
	}

	/**
	 * @param array<string,string> $token
	 */
	#[DataProvider('requestsWithoutToken')]
	public function testARequestWithoutTheTokenOfTheSessionIsRefusedAndChangesNothing(string $method, string $path, array $token): void
	{
		$before = $this->database();
		// the fields of the requests: whatever a route needs, it does not get further than the check
		$fields = $token + ['playlist_id' => '1', 'item_id' => '1', 'media_id' => '1', 'node_id' => '1', 'UID' => '3', 'player_id' => '1', 'template_id' => '1', 'name' => 'changed'];

		$response = $this->visitorFor($path)->request($method, $path, ['form_params' => $fields]);

		if (str_starts_with($path, '/async/'))
		{
			static::assertSame(403, $response->getStatusCode(), $method . ' ' . $path);
			static::assertSame(['success' => false, 'error_message' => 'CSRF token validation error.'], json_decode((string) $response->getBody(), true));
		}
		else
		{
			static::assertSame(303, $response->getStatusCode(), $method . ' ' . $path);
			static::assertSame($path, $response->getHeaderLine('Location'));
		}
		static::assertSame($before, $this->database(), $method . ' ' . $path . ' changed the database');
	}

	/**
	 * The stock search sends the token since the CsrfMiddleware: with it the request gets to the controller.
	 */
	public function testTheStockSearchWithTheTokenGetsToTheController(): void
	{
		$response = $this->admin->request('POST', '/async/mediapool/searchStockImages', ['form_params' => [
			'csrf_token' => $this->admin->csrfToken('/mediapool'),
			'api_url'    => 'https://evil.example.com/api',
		]]);

		// refused by the controller (host not allowed), not by the CSRF check
		$answer = json_decode((string) $response->getBody(), true);
		static::assertIsArray($answer);
		static::assertFalse($answer['success']);
		static::assertNotSame('CSRF token validation error.', $answer['error_message'] ?? '');
	}
}
