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

namespace Tests\Integration\Modules\Users\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Security\CsrfToken;
use App\Modules\Users\Controller\UserTokenController;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who sees which users, who may delete users and handle their tokens.
 * Fixtures: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class UserAccessTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	public function testAdminSeesAllUsers(): void
	{
		static::assertSame(['admin', 'anna', 'bernd'], $this->usernamesVisibleFor(1));
	}

	public function testUserSeesOnlyHerself(): void
	{
		static::assertSame(['anna'], $this->usernamesVisibleFor(2));
		static::assertSame(['bernd'], $this->usernamesVisibleFor(3));
	}

	public function testTheListNeverContainsPasswordsOrSessions(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersDatatableService::class);
		$service->loadDatatable();

		foreach ($service->getCurrentFilterResults() as $row)
		{
			static::assertArrayNotHasKey('password', $row);
			static::assertArrayNotHasKey('session_id', $row);
		}
	}

	public function testAdminMayDeleteAUserWithTheTokens(): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::AUTOLOGIN);

		$this->container->get(UsersAdminService::class)->deleteUser(3);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main WHERE UID = 3'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testDeletedUserLosesTheRights(): void
	{
		$this->actingAs(1);

		$this->container->get(UsersAdminService::class)->deleteUser(1);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 1'));
	}

	/**
	 * The UID of user_main is not an AUTOINCREMENT: after deleting the last user the next new user
	 * gets the same UID. Whatever is left of the old user would belong to the new one.
	 */
	public function testANewUserDoesNotInheritTheRightsOfADeletedUser(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (3, 8, 'player')");
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);

		$service->deleteUser(3);
		$newUID = $service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = ?', [$newUID]));
	}

	public function testUserMayNotDeleteAnotherUser(): void
	{
		$this->actingAs(2);

		try
		{
			$this->container->get(UsersAdminService::class)->deleteUser(3);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
		}
	}

	public function testUserMayNotDeleteHerself(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->container->get(UsersAdminService::class)->deleteUser(2);
	}

	public function testDeletingAnUnknownUserIsNotFound(): void
	{
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->container->get(UsersAdminService::class)->deleteUser(999);
	}

	public function testUserMayNotRefreshTheTokenOfAnotherUser(): void
	{
		$this->actingAs(2);
		$token = $this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$before = $this->tokenHashes(3);

		$response = $this->tokenRequest('refresh', ['UID' => 3, 'purpose' => 'password_reset']);

		static::assertSame(403, $response->getStatusCode());
		static::assertSame($before, $this->tokenHashes(3));
		static::assertNotSame('', $token);
	}

	public function testUserMayNotDeleteTheTokensOfAnotherUser(): void
	{
		$this->actingAs(2);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);

		$response = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'password_reset']);

		static::assertSame(403, $response->getStatusCode());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
	}

	public function testTokenRequestWithoutValidCsrfTokenIsRejected(): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$request = (new ServerRequestFactory())->createServerRequest('DELETE', '/users/tokens')
			->withParsedBody(['UID' => 3, 'purpose' => 'password_reset', 'csrf_token' => 'forged']);

		$response = $this->container->get(UserTokenController::class)->delete($request, new Response());

		static::assertNotSame(200, $response->getStatusCode());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
	}

	public function testAdminMayRefreshAndDeleteTokens(): void
	{
		$this->actingAs(1);
		$old = $this->createToken(3, TokenPurposes::PASSWORD_RESET);
		$hashBefore = $this->tokenHashes(3);

		$refresh = $this->tokenRequest('refresh', ['UID' => 3, 'purpose' => 'password_reset']);
		static::assertSame(200, $refresh->getStatusCode());
		static::assertNotSame($hashBefore, $this->tokenHashes(3));
		static::assertNull($this->container->get(UserTokenService::class)->findByToken($old));

		$delete = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'password_reset']);
		static::assertSame(200, $delete->getStatusCode());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 3'));
	}

	public function testUnknownPurposeIsNotEditable(): void
	{
		$this->actingAs(1);

		$response = $this->tokenRequest('delete', ['UID' => 3, 'purpose' => 'anything']);

		static::assertSame(400, $response->getStatusCode());
	}

	/**
	 * @return list<string>
	 */
	private function usernamesVisibleFor(int $UID): array
	{
		$this->actingAs($UID);
		$service = $this->container->get(UsersDatatableService::class);
		$service->loadDatatable();

		return array_column($service->getCurrentFilterResults(), 'username');
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function tokenRequest(string $action, array $data): ResponseInterface
	{
		$data['csrf_token'] = $this->container->get(CsrfToken::class)->getToken();
		$request = (new ServerRequestFactory())->createServerRequest($action === 'delete' ? 'DELETE' : 'POST', '/users/tokens')
			->withParsedBody($data);

		return $this->container->get(UserTokenController::class)->$action($request, new Response());
	}

	private function createToken(int $UID, TokenPurposes $purpose): string
	{
		$tokenService = $this->container->get(UserTokenService::class);
		$token        = $tokenService->generateToken();
		$tokenService->insertToken($UID, $token, $purpose);

		return $token;
	}

	/**
	 * @return list<string>
	 */
	private function tokenHashes(int $UID): array
	{
		return array_map('strval', $this->connection->fetchFirstColumn('SELECT token FROM user_tokens WHERE UID = ? ORDER BY token', [$UID]));
	}
}
