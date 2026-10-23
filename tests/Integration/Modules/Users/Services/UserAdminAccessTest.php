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
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Security\CsrfToken;
use App\Modules\Users\Services\UsersAdminService;
use App\Shared\User\TokenPurposes;
use App\Modules\Users\Controller\ShowAdminController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may create, change and reset users. Only the module admin of the module users may.
 * Fixtures: 1 admin, 2 anna, 3 bernd.
 *
 * The tests send the same requests a browser would send to /users/edit.
 */
#[Group('acl')]
class UserAdminAccessTest extends ContainerTestCase
{
	private ShowAdminController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->controller = $this->container->get(ShowAdminController::class);
	}

	public function testUserMayNotResetThePasswordOfAnotherUser(): void
	{
		$this->actingAs(2);
		$before = $this->passwordOf(1);

		$this->attempt(['UID' => 1, 'resetPassword' => 1, 'username' => 'admin', 'email' => 'admin@example.com']);

		static::assertSame($before, $this->passwordOf(1));
		static::assertSame(0, $this->countTokens(1));
		static::assertFalse($this->container->get(\App\Framework\Http\Session::class)->exists('password_reset_token'));
	}

	public function testUserMayNotChangeAnotherUser(): void
	{
		$this->actingAs(2);

		$this->attempt(['UID' => 1, 'username' => 'hacked', 'email' => 'hacked@example.com', 'status' => 0, 'locale' => 'de_DE']);

		$admin = $this->userRow(1);
		static::assertSame('admin', $admin['username']);
		static::assertSame('admin@example.com', $admin['email']);
		static::assertSame(3, (int) $admin['status']);
	}

	public function testUserMayNotChangeHerOwnStatusOrRights(): void
	{
		$this->actingAs(2);

		$this->attempt(['UID' => 2, 'username' => 'anna', 'email' => 'anna@example.com', 'status' => 9]);

		static::assertSame(3, (int) $this->userRow(2)['status']);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 2'));
	}

	public function testUserMayNotCreateUsers(): void
	{
		$this->actingAs(2);

		$this->attempt(['UID' => 0, 'username' => 'evil', 'email' => 'evil@example.com', 'status' => 3, 'locale' => 'en_US']);

		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testAdminMayCreateAUserWithAnInitialPasswordToken(): void
	{
		$this->actingAs(1);

		$response = $this->post(['UID' => 0, 'username' => 'carla', 'email' => 'carla@example.com', 'status' => 2, 'locale' => 'en_US']);

		static::assertSame(302, $response->getStatusCode());
		$created = $this->connection->fetchAssociative("SELECT UID, status FROM user_main WHERE username = 'carla'");
		static::assertIsArray($created);
		static::assertSame(2, (int) $created['status']);
		static::assertSame(1, $this->countTokens((int) $created['UID']));
	}

	public function testAdminMayChangeAUser(): void
	{
		$this->actingAs(1);

		$this->post(['UID' => 3, 'username' => 'bernd2', 'email' => 'bernd2@example.com', 'status' => 1, 'locale' => 'de_DE']);

		$bernd = $this->userRow(3);
		static::assertSame('bernd2', $bernd['username']);
		static::assertSame('bernd2@example.com', $bernd['email']);
		static::assertSame(1, (int) $bernd['status']);
		static::assertSame('de_DE', $bernd['locale']);
	}

	public function testAdminMayResetAPassword(): void
	{
		$this->actingAs(1);

		$this->post(['UID' => 3, 'resetPassword' => 1, 'username' => 'bernd', 'email' => 'bernd@example.com']);

		static::assertSame('', $this->passwordOf(3));
		static::assertSame(1, $this->countTokens(3));
		static::assertTrue($this->container->get(\App\Framework\Http\Session::class)->exists('password_reset_token'));
	}

	public function testAdminMayResetAPasswordOfAUserWithRememberMeLogins(): void
	{
		$this->actingAs(1);
		$this->createToken(3, TokenPurposes::AUTOLOGIN);

		$this->post(['UID' => 3, 'resetPassword' => 1, 'username' => 'bernd', 'email' => 'bernd@example.com']);

		static::assertSame('', $this->passwordOf(3));
		static::assertSame(1, $this->countTokens(3, TokenPurposes::PASSWORD_RESET));
		// the reset ends the remember-me logins, as a password change does
		static::assertSame(0, $this->countTokens(3, TokenPurposes::AUTOLOGIN));
	}

	public function testASecondResetWhileTheLinkIsStillValidIsRejected(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$service->createPasswordResetToken(3);

		$this->expectException(TranslatableModuleException::class);
		$service->createPasswordResetToken(3);
	}

	public function testAnExpiredLinkDoesNotBlockANewReset(): void
	{
		$this->actingAs(1);
		$service = $this->container->get(UsersAdminService::class);
		$service->createPasswordResetToken(3);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		$token = $service->createPasswordResetToken(3);

		static::assertNotSame('', $token);
	}

	/**
	 * Sends the request of a user who is not allowed to do it. How it is rejected does not matter,
	 * nothing may have been changed afterwards.
	 *
	 * @param array<string,mixed> $data
	 */
	private function attempt(array $data): void
	{
		try
		{
			$this->post($data);
		}
		catch (ModuleException|AccessDeniedException)
		{
			static::addToAssertionCount(1);
		}
	}

	private function createToken(int $UID, TokenPurposes $purpose): string
	{
		$tokenService = $this->container->get(\App\Shared\User\UserTokenService::class);
		$token        = $tokenService->generateToken();
		$tokenService->insertToken($UID, $token, $purpose);

		return $token;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function post(array $data): ResponseInterface
	{
		$data['csrf_token'] = $this->container->get(CsrfToken::class)->getToken();
		$request = (new ServerRequestFactory())->createServerRequest('POST', '/users/edit')->withParsedBody($data);

		return $this->controller->store($request, new Response());
	}

	/**
	 * @return array<string,mixed>
	 */
	private function userRow(int $UID): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM user_main WHERE UID = ?', [$UID]);
		self::assertIsArray($row);

		return $row;
	}

	private function passwordOf(int $UID): string
	{
		return (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = ?', [$UID]);
	}

	private function countTokens(int $UID, ?TokenPurposes $purpose = null): int
	{
		if ($purpose === null)
			return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ?', [$UID]);

		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ? AND purpose = ?', [$UID, $purpose->value]);
	}
}
