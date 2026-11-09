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

namespace Tests\Integration\Modules\Auth\Services;

use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use App\Shared\User\UserStatus;
use App\Shared\Audit\AuditLog;
use Tests\Integration\Support\ArraySession;
use App\Modules\Account\Services\AccountService;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\UsersAdminService;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;
use App\Shared\User\TwoFactor\TwoFactorService;
use App\Framework\Config\Config;
use App\Framework\Config\ConfigOverrides;

/**
 * Which paths a guest, a logged-in user and a user with a remember-me cookie can reach.
 * Users of the fixture: 10 regular, 12 blocked.
 */
#[Group('auth')]
class AuthMiddlewareTest extends ContainerTestCase
{
	private AuthMiddleware $middleware;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->middleware = new AuthMiddleware(
			new AuthService(
				$this->container->get(UsersService::class),
				$this->container->get(AutoLoginService::class),
				$this->container->get('ModuleLogger'),
				new LoginThrottle($this->container->get(Psr16Adapter::class)),
			$this->container->get(AuditLog::class),
			$this->container->get(TwoFactorService::class)
			),
			$this->container->get(Session::class),
			$this->container->get(Cookie::class),
			$this->container->get(UserSession::class),
			$this->container->get(Config::class)
		);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function protectedPaths(): array
	{
		return [
			'start page'          => ['/'],
			'player list'         => ['/player'],
			'playlists'           => ['/playlists'],
			'an unknown route'    => ['/anything-new'],
			'upper case section'  => ['/SMIL-INDEX/abc'],
			'upper case login'    => ['/Login'],
			'media'               => ['/mediapool'],
		];
	}

	#[DataProvider('protectedPaths')]
	public function testGuestIsSentToTheLogin(string $path): void
	{
		$response = $this->process('GET', $path);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function apiPaths(): array
	{
		return ['api' => ['/api/authorize'], 'async' => ['/async/player/push']];
	}

	#[DataProvider('apiPaths')]
	public function testGuestGetsAJsonErrorOnApiPaths(string $path): void
	{
		$response = $this->process('GET', $path);

		static::assertSame(401, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertStringContainsString('"success":false', (string) $response->getBody());
	}

	public function testGuestGetsAJsonErrorWhenOnlyJsonIsAccepted(): void
	{
		$response = $this->process('GET', '/player', ['Accept' => 'application/json']);

		static::assertSame(401, $response->getStatusCode());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function publicPaths(): array
	{
		return [
			'smil index'     => ['/smil-index/abc'],
			'initial setup'  => ['/create-initial'],
			'force password' => ['/force-password'],
			'reset password' => ['/reset-password'],
			'languages'      => ['/set-locales'],
		];
	}

	#[DataProvider('publicPaths')]
	public function testPublicPathsNeedNoLogin(string $path): void
	{
		static::assertSame(200, $this->process('GET', $path)->getStatusCode());
	}

	public function testGuestMayOpenTheLoginPage(): void
	{
		static::assertSame(200, $this->process('POST', '/login')->getStatusCode());
		static::assertSame(200, $this->process('GET', '/login')->getStatusCode());
	}

	public function testLoggedInUserReachesProtectedPaths(): void
	{
		$this->actingAs(10);

		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());
		static::assertSame(200, $this->process('GET', '/async/player/push')->getStatusCode());
	}

	/**
	 * @return array<string,array{string,string,bool}> method, path, reached
	 */
	public static function pathsWhileTheSecondFactorIsMissing(): array
	{
		return [
			'the account'          => ['GET', '/account/settings', true],
			'its old address'      => ['GET', '/account/password', true],
			'setting it up'        => ['POST', '/account/two-factor', true],
			'logout'               => ['GET', '/logout', true],
			'with a slash'         => ['GET', '/account/settings/', true],
			'the password'         => ['POST', '/account/password', false],
			'new recovery codes'   => ['POST', '/account/two-factor/recovery-codes', false],
			'own models'           => ['POST', '/account/llm/connection', false],
			'the models of an url' => ['POST', '/account/llm/models', false],
			'set up by GET'        => ['GET', '/account/two-factor', false],
			'the player'           => ['GET', '/player', false],
		];
	}

	/**
	 * While the admin requires the second factor, a user without it reaches only the page of his account, setting it
	 * up and the logout.
	 */
	#[DataProvider('pathsWhileTheSecondFactorIsMissing')]
	public function testWithoutTheRequiredSecondFactorOnlyItsSetupIsReached(string $method, string $path, bool $reached): void
	{
		$this->container->get(ConfigOverrides::class)->save('users', ['TwoFactor' => ['required' => '1']]);
		$this->actingAs(10);

		$response = $this->process($method, $path);

		static::assertSame($reached ? 200 : 302, $response->getStatusCode());
		if (!$reached)
			static::assertSame('/account/settings#two_factor', $response->getHeaderLine('Location'));
	}

	public function testLoggedInUserIsSentAwayFromTheLoginPage(): void
	{
		$this->actingAs(10);

		$response = $this->process('GET', '/login');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
	}

	/**
	 * The admin blocks the user while he works: the user is cached from the first request,
	 * the next one must not let him in anymore.
	 */
	public function testAUserBlockedByTheAdminIsLoggedOutWithTheNextRequest(): void
	{
		$this->actingAs(10);
		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());

		$this->container->get(UsersAdminService::class)->updateUser(10, ['username' => 'regular', 'email' => 'Regular@Example.com', 'status' => UserStatus::BLOCKED->value]);

		$response = $this->process('GET', '/player');
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
		static::assertFalse($this->container->get(Session::class)->exists('user'));
		static::assertSame(401, $this->process('GET', '/async/player/push')->getStatusCode());
	}

	/**
	 * The user changes the password in another browser: this session was started with the old one and ends.
	 */
	public function testAPasswordChangeEndsTheOtherSessionsOfTheUser(): void
	{
		$this->actingAs(10);
		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());

		$this->container->get(AccountService::class)->updatePassword(10, 'New-Pass1!');

		$response = $this->process('GET', '/player');
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	/**
	 * Like the Orchestrator of the password change: the session in which the user changed the password stays.
	 */
	public function testTheSessionWhichChangedThePasswordStaysLoggedIn(): void
	{
		$this->actingAs(10);
		$accountService = $this->container->get(AccountService::class);

		$accountService->updatePassword(10, 'New-Pass1!');
		new UserSession($this->container->get(Session::class))->keepAfterPasswordChange($accountService->findLastPasswordChange(10));

		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());
	}

	public function testAPasswordResetByTheAdminEndsTheSessionsOfTheUser(): void
	{
		$this->actingAs(10);
		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());

		$this->container->get(UsersAdminService::class)->createPasswordResetToken(10);

		static::assertSame(302, $this->process('GET', '/player')->getStatusCode());
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	public function testAPasswordChangeOfAnotherUserKeepsTheSession(): void
	{
		$this->actingAs(10);

		$this->container->get(AccountService::class)->updatePassword(14, 'New-Pass1!');

		static::assertSame(200, $this->process('GET', '/player')->getStatusCode());
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function inactiveUsers(): array
	{
		return ['deleted' => [11], 'blocked' => [12], 'not verified' => [13], 'not existing' => [99]];
	}

	#[DataProvider('inactiveUsers')]
	public function testASessionOfAnInactiveUserIsNoLogin(int $UID): void
	{
		$this->container->get(Session::class)->set('user', ['UID' => $UID, 'username' => 'x', 'locale' => 'en_US']);

		$response = $this->process('GET', '/player');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	public function testRememberMeCookieLogsTheUserIn(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);

		$response = $this->process('GET', '/player');

		static::assertSame(200, $response->getStatusCode());
		$user = $this->container->get(Session::class)->get('user');
		static::assertIsArray($user);
		static::assertSame(10, $user['UID']);
		static::assertSame('de_DE', $this->container->get(Session::class)->get('locale'));
	}

	/**
	 * Like the login by password: whoever knew the session id before must not know it now.
	 */
	public function testTheLoginByCookieGetsANewSessionId(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);
		$session = $this->container->get(Session::class);
		static::assertInstanceOf(ArraySession::class, $session);

		$this->process('GET', '/player');

		static::assertSame(1, $session->regenerations);
	}

	public function testALoggedInUserKeepsTheSessionIdEvenWithACookie(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);
		$this->actingAs(10);
		$session = $this->container->get(Session::class);
		static::assertInstanceOf(ArraySession::class, $session);

		$this->process('GET', '/player');

		static::assertSame(0, $session->regenerations);
	}

	public function testAnOutdatedCookieDoesNotKeepAGuestFromTheLoginPage(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		static::assertSame(200, $this->process('POST', '/login')->getStatusCode());
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	public function testRememberMeCookieOfABlockedUserIsNoLogin(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(12);

		$response = $this->process('GET', '/player');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	public function testBrokenRememberMeCookieSendsToTheLogin(): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = 'garbage';

		$response = $this->process('GET', '/player');

		static::assertSame(302, $response->getStatusCode());
		static::assertFalse($this->container->get(Session::class)->exists('user'));
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function process(string $method, string $path, array $headers = []): ResponseInterface
	{
		$request = (new ServerRequestFactory())->createServerRequest($method, $path);
		foreach ($headers as $name => $value)
			$request = $request->withHeader($name, $value);

		$handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new Response(200);
			}
		};

		return $this->middleware->process($request, $handler);
	}
}
