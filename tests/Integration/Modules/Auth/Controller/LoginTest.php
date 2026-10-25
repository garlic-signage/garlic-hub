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

namespace Tests\Integration\Modules\Auth\Controller;

use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use App\Framework\Security\CsrfToken;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginController;
use App\Modules\Auth\LoginThrottle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ArraySession;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The login page of the browser: controller, AuthService, throttle, remember me cookie, session and
 * database work together. Users of the fixture: 10 regular (de_DE), 11 deleted, 12 blocked, 13 not verified,
 * 14 other. All have the password Test1234!
 */
#[Group('auth')]
class LoginTest extends ContainerTestCase
{
	private const string PASSWORD = 'Test1234!';

	private LoginController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$this->loadFixture('auth/users.sql');
		$this->controller = $this->container->get(LoginController::class);
	}

	/**
	 * @param array<string,mixed> $changes replaces or (null) removes entries of a correct post
	 */
	private function login(string $username, string $password = self::PASSWORD, array $changes = []): ResponseInterface
	{
		$post = ['username' => $username, 'password' => $password, 'csrf_token' => $this->container->get(CsrfToken::class)->getToken()];
		foreach ($changes as $key => $value)
		{
			if ($value === null)
				unset($post[$key]);
			else
				$post[$key] = $value;
		}

		return $this->controller->login(new ServerRequestFactory()->createServerRequest('POST', '/login')->withParsedBody($post), new Response());
	}

	private function session(): Session
	{
		return $this->container->get(Session::class);
	}

	private function cookie(): Cookie
	{
		return $this->container->get(Cookie::class);
	}

	/**
	 * @return list<string>
	 */
	private function flashErrors(): array
	{
		/** @var array<string,list<string>> $flash */
		$flash = $_SESSION['slimFlash'] ?? [];

		return $flash['error'] ?? [];
	}

	private function tokens(int $UID): int
	{
		return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_tokens WHERE UID = ? AND purpose = 'autologin'", [$UID]);
	}

	private function assertRedirect(ResponseInterface $response, string $location): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}

	public function testALoginFillsTheSessionAndSetsNoCookieWithoutTheBox(): void
	{
		$this->assertRedirect($this->login('regular'), '/');

		static::assertSame(10, $this->session()->get('user')['UID'] ?? null);
		static::assertSame('de_DE', $this->session()->get('locale'));
		$session = $this->session();
		static::assertInstanceOf(ArraySession::class, $session);
		static::assertSame(1, $session->regenerations);
		static::assertFalse($this->cookie()->hasCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN));
		static::assertSame(0, $this->tokens(10));
	}

	public function testTheBoxSetsTheRememberMeCookieWhichBringsTheUserBack(): void
	{
		$this->assertRedirect($this->login('regular', self::PASSWORD, ['autologin' => 'on']), '/');

		static::assertTrue($this->cookie()->hasCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN));
		static::assertSame(1, $this->tokens(10));
		static::assertSame(10, $this->container->get(AuthService::class)->loginByCookie()?->getMain()['UID']);
	}

	public function testALoginWithTheEmailAddressWorks(): void
	{
		$this->assertRedirect($this->login('REGULAR@example.com'), '/');
		static::assertSame(10, $this->session()->get('user')['UID'] ?? null);
	}

	/**
	 * @return array<string,array{string,string,array<string,mixed>}>
	 */
	public static function failedLogins(): array
	{
		return [
			'wrong password'    => ['regular', 'wrong', []],
			'unknown user'      => ['nobody', self::PASSWORD, []],
			'sql in the name'   => ["regular' OR '1'='1", self::PASSWORD, []],
			'password missing'  => ['regular', '', ['password' => null]],
			'user missing'      => ['regular', '', ['username' => null]],
			'user is an array'  => ['regular', self::PASSWORD, ['username' => ['regular']]],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('failedLogins')]
	public function testAFailedLoginLeadsBackToTheLoginPageWithTheSameAnswer(string $username, string $password, array $changes): void
	{
		$this->assertRedirect($this->login($username, $password, $changes), '/login');

		static::assertNull($this->session()->get('user'));
		static::assertSame(['Invalid credentials.'], $this->flashErrors());
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function usersWhoMayNotLogin(): array
	{
		return ['deleted' => ['deleted', 'login//account_deleted'], 'blocked' => ['blocked', 'login//account_locked'], 'not verified' => ['unverified', 'login//account_inactive']];
	}

	#[DataProvider('usersWhoMayNotLogin')]
	public function testAUserWhoMayNotLoginGetsTheReason(string $username, string $message): void
	{
		$this->assertRedirect($this->login($username), '/login');

		static::assertNull($this->session()->get('user'));
		static::assertSame([$message], $this->flashErrors());
	}

	public function testTooManyFailuresLockTheLoginEvenForTheRightPassword(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->login('regular', 'wrong');
		$_SESSION['slimFlash'] = [];

		$this->assertRedirect($this->login('regular'), '/login');

		static::assertNull($this->session()->get('user'));
		static::assertSame(['Too many failed login attempts. Please try again later.'], $this->flashErrors());
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongTokens(): array
	{
		return ['wrong' => [['csrf_token' => 'forged']], 'empty' => [['csrf_token' => '']], 'missing' => [['csrf_token' => null]]];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('wrongTokens')]
	public function testALoginWithoutTheTokenOfThePageIsRefused(array $changes): void
	{
		$this->assertRedirect($this->login('regular', self::PASSWORD, $changes), '/login');

		static::assertNull($this->session()->get('user'));
		static::assertSame(['Invalid CSRF token'], $this->flashErrors());
	}

	/**
	 * The AuthMiddleware tries the remember me cookie of every guest, also on the login page, and the
	 * controller uses the same AuthService in the same request. A cookie which does not work any more
	 * (expired, replaced by another browser) must not stop the login with the password.
	 */
	public function testAnOutdatedRememberMeCookieDoesNotStopTheLoginWithThePassword(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");
		static::assertNull($this->container->get(AuthService::class)->loginByCookie());

		$this->assertRedirect($this->login('regular'), '/');

		static::assertSame(10, $this->session()->get('user')['UID'] ?? null);
		static::assertSame([], $this->flashErrors());
	}

	public function testALogoutEndsSessionCookieAndTokens(): void
	{
		$this->login('regular', self::PASSWORD, ['autologin' => 'on']);
		$token = $this->container->get(\App\Shared\User\UserTokenService::class)->generateToken();
		$this->container->get(\App\Shared\User\UserTokenService::class)->insertToken(14, $token, \App\Shared\User\TokenPurposes::AUTOLOGIN);

		$response = $this->controller->logout(new ServerRequestFactory()->createServerRequest('GET', '/logout'), new Response());

		$this->assertRedirect($response, '/login');
		static::assertNull($this->session()->get('user'));
		static::assertFalse($this->cookie()->hasCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN));
		static::assertSame(0, $this->tokens(10));
		static::assertSame(1, $this->tokens(14), 'The login of the other user is not touched.');
	}
}
