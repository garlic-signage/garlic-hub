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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Users\Controller\UsersController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * Deleting a user and finding users by name (the autocomplete). Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class UsersControllerTest extends UsersControllerTestCase
{
	private UsersController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(UsersController::class);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function delete(array $body, bool $withToken = true): ResponseInterface
	{
		return $this->controller->delete($this->request('DELETE', body: $withToken ? $this->withToken($body) : $body), $this->newResponse());
	}

	private function find(string $name): ResponseInterface
	{
		return $this->controller->findByName($this->request('GET', '/users/find/' . $name, null), $this->newResponse(), ['username' => $name]);
	}

	/**
	 * @return list<string> the names found
	 */
	private function namesFound(string $name): array
	{
		$names = array_column($this->json($this->find($name)), 'name');
		sort($names);

		return $names;
	}

	public function testTheAdminDeletesAUser(): void
	{
		$this->actingAs(1);

		$this->assertSuccess($this->delete(['UID' => '3']));

		static::assertSame(0, (int) $this->databaseValue('SELECT status FROM user_main WHERE UID = 3'));
		static::assertSame('deleted-3@garlic-hub.com', $this->databaseValue('SELECT email FROM user_main WHERE UID = 3'));
	}

	public function testADeletionNeedsTheCsrfToken(): void
	{
		$this->actingAs(1);

		$this->assertError($this->delete(['UID' => '3'], withToken: false), 'CSRF token mismatch.');

		static::assertSame('bernd@example.com', $this->databaseValue('SELECT email FROM user_main WHERE UID = 3'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}> the requests which name no user
	 */
	public static function requestsWithoutUser(): array
	{
		return ['no uid' => [[]], 'zero' => [['UID' => '0']], 'empty' => [['UID' => '']], 'text' => [['UID' => 'abc']]];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('requestsWithoutUser')]
	public function testADeletionNeedsAUser(array $body): void
	{
		$this->actingAs(1);

		$this->assertError($this->delete($body), 'No UID');

		static::assertSame(3, (int) $this->databaseValue('SELECT status FROM user_main WHERE UID = 1'));
	}

	public function testADeletionWithoutBodyNeedsTheCsrfToken(): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->delete($this->request('DELETE', body: null), $this->newResponse()), 'CSRF token mismatch.');
	}

	public function testAListAsUserIsNoUser(): void
	{
		$this->actingAs(1);

		$this->assertError($this->delete(['UID' => ['3']]), 'No UID');

		static::assertSame(3, (int) $this->databaseValue('SELECT status FROM user_main WHERE UID = 1'));
	}

	public function testACsrfTokenWhichIsNoTextIsNoToken(): void
	{
		$this->actingAs(1);

		$this->assertError($this->delete(['csrf_token' => ['x'], 'UID' => '3'], withToken: false), 'CSRF token mismatch.');
	}

	public function testAUserDoesNotDeleteAnotherUser(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->delete(['UID' => '3']);
		}
		finally
		{
			static::assertSame('bernd@example.com', $this->databaseValue('SELECT email FROM user_main WHERE UID = 3'));
		}
	}

	public function testAnUnknownUserIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->delete(['UID' => '99']);
	}

	public function testTheAdminFindsUsersByTheStartOfTheName(): void
	{
		$this->actingAs(1);

		static::assertSame(['anna'], $this->namesFound('an'));
		static::assertSame([['id' => 3, 'name' => 'bernd']], $this->json($this->find('bernd')));
	}

	public function testTheAnswerIsAPlainListWithoutEnvelope(): void
	{
		$this->actingAs(1);

		static::assertArrayNotHasKey('success', $this->json($this->find('an')));
		static::assertSame(['admin', 'anna', 'bernd'], $this->namesFound(''));
	}

	public function testAUserFindsOnlyHerself(): void
	{
		$this->actingAs(2);

		static::assertSame(['anna'], $this->namesFound(''));
		static::assertSame([], $this->namesFound('bernd'));
	}

	public function testDeletedUsersAreNotFound(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 0 WHERE UID = 3');

		static::assertSame([], $this->namesFound('bernd'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function namesWithWildcards(): array
	{
		return ['percent' => ['%'], 'underscore' => ['_'], 'in the middle' => ['a%a'], 'quote' => ["a'"]];
	}

	#[DataProvider('namesWithWildcards')]
	public function testAWildcardIsNoWildcard(string $name): void
	{
		$this->actingAs(1);

		static::assertSame([], $this->namesFound($name));
	}
}
