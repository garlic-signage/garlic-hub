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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Http\Cookie;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Shared\User\UserEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * Which paths a guest may open, what he gets instead, and the login by the remember me cookie.
 * The session and the cookie are the real ones, the AuthService is a stub with the answer of the login by cookie.
 * With the real service and the database the same is tested in tests/Integration/Modules/Auth.
 */
class AuthMiddlewareTest extends TestCase
{
	private AuthService&Stub $authService;
	private MemorySession $session;
	private AuthMiddleware $middleware;
	private RequestHandlerInterface $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$_COOKIE = [];
		$this->authService = $this->createStub(AuthService::class);
		$this->session     = new MemorySession();
		$this->handler     = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new Response(200)->withHeader('X-Handled', $request->getUri()->getPath());
			}
		};
		$this->middleware = new AuthMiddleware($this->authService, $this->session, new Cookie());
	}

	protected function tearDown(): void
	{
		$_COOKIE = [];
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function process(string $path, array $headers = [], string $method = 'GET'): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest($method, $path);
		foreach ($headers as $name => $value)
			$request = $request->withHeader($name, $value);

		return $this->middleware->process($request, $this->handler);
	}

	private function assertHandled(ResponseInterface $response, string $path): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertSame($path, $response->getHeaderLine('X-Handled'));
	}

	private function assertRedirect(ResponseInterface $response, string $location): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
		static::assertFalse($response->hasHeader('X-Handled'));
	}

	/**
	 * The cookie of a user who is known: the login by cookie answers with him.
	 */
	private function rememberMeCookieOf(int $UID, string $locale = 'en_US'): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = 'cookie-token';
		$this->authService->method('loginByCookie')->willReturn(
			new UserEntity(['UID' => $UID, 'username' => 'user', 'locale' => $locale], [], [], [], [], [])
		);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function publicPaths(): array
	{
		return [
			'initial setup'  => ['/create-initial'],
			'languages'      => ['/set-locales/de_DE'],
			'player index'   => ['/smil-index'],
			'player index 2' => ['/smil-index/abc'],
			'force password' => ['/force-password'],
			'reset password' => ['/reset-password'],
			'register'       => ['/register'],
			'legals'         => ['/legals'],
			'privacy'        => ['/privacy'],
			'terms'          => ['/terms'],
			'cms'            => ['/cms/page'],
		];
	}

	#[Group('units')]
	#[DataProvider('publicPaths')]
	public function testPublicPathsNeedNoLogin(string $path): void
	{
		$this->assertHandled($this->process($path), $path);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function protectedPaths(): array
	{
		return [
			'start page'                => ['/'],
			'player'                    => ['/player'],
			'an unknown route'          => ['/anything-new'],
			'longer than a public name' => ['/set-localesX'],
			'public name as prefix'     => ['/smil-index.php'],
			'public name as suffix'     => ['/x/smil-index'],
			'public name in the path'   => ['/player/force-password'],
			'upper case'                => ['/SMIL-INDEX'],
			'double slash'              => ['//player'],
			'login as prefix'           => ['/logins'],
			'login upper case'          => ['/Login'],
			'encoded name'              => ['/%73mil-index'],
		];
	}

	#[Group('units')]
	#[DataProvider('protectedPaths')]
	public function testAGuestIsSentToTheLoginFromEveryOtherPath(string $path): void
	{
		$this->assertRedirect($this->process($path), '/login');
	}

	/**
	 * @return array<string,array{string,array<string,string>}>
	 */
	public static function jsonRequests(): array
	{
		return [
			'api'       => ['/api/authorize', []],
			'async'     => ['/async/player/push', []],
			'json only' => ['/player', ['Accept' => 'application/json']],
		];
	}

	/**
	 * @param array<string,string> $headers
	 */
	#[Group('units')]
	#[DataProvider('jsonRequests')]
	public function testAGuestGetsAJsonErrorWhereTheCallerExpectsJson(string $path, array $headers): void
	{
		$response = $this->process($path, $headers);

		static::assertSame(401, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'message' => 'Unauthorized access. Please log in first.'], json_decode((string) $response->getBody(), true));
	}

	#[Group('units')]
	public function testAGuestMayOpenTheLoginPage(): void
	{
		$this->assertHandled($this->process('/login'), '/login');
		$this->assertHandled($this->process('/login', [], 'POST'), '/login');
	}

	#[Group('units')]
	public function testALoggedInUserPassesAndGetsNoNewSession(): void
	{
		$this->session->set('user', ['UID' => 1]);

		$this->assertHandled($this->process('/playlists'), '/playlists');
		static::assertSame(0, $this->session->regenerations);
	}

	#[Group('units')]
	public function testALoggedInUserIsSentAwayFromTheLoginPage(): void
	{
		$this->session->set('user', ['UID' => 1]);

		$this->assertRedirect($this->process('/login'), '/');
	}

	#[Group('units')]
	public function testTheRememberMeCookieLogsTheUserInAndFillsTheSession(): void
	{
		$this->rememberMeCookieOf(7, 'de_DE');

		$this->assertHandled($this->process('/playlists'), '/playlists');

		static::assertSame(['UID' => 7, 'username' => 'user', 'locale' => 'de_DE'], $this->session->get('user'));
		static::assertSame('de_DE', $this->session->get('locale'));
	}

	/**
	 * Like the login by password: whoever knew the session id before must not know it now.
	 */
	#[Group('units')]
	public function testTheLoginByCookieGetsANewSessionId(): void
	{
		$this->rememberMeCookieOf(7);

		$this->process('/playlists');

		static::assertSame(1, $this->session->regenerations);
	}

	#[Group('units')]
	public function testALoggedInUserWithACookieIsNotLoggedInAgain(): void
	{
		$this->rememberMeCookieOf(7);
		$this->session->set('user', ['UID' => 1]);

		$this->process('/playlists');

		static::assertSame(['UID' => 1], $this->session->get('user'));
		static::assertSame(0, $this->session->regenerations);
	}

	#[Group('units')]
	public function testAnInvalidCookieLeadsToTheLoginAndTheSessionStaysEmpty(): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = 'stale';
		$this->authService->method('loginByCookie')->willReturn(null);

		$this->assertRedirect($this->process('/playlists'), '/login');

		static::assertSame([], $this->session->data);
		static::assertSame(0, $this->session->regenerations);
	}

	/**
	 * Without a cookie the AuthService is not asked: even a service which would let anybody in does not.
	 */
	#[Group('units')]
	public function testWithoutACookieNobodyIsLoggedIn(): void
	{
		$this->authService->method('loginByCookie')->willReturn(new UserEntity(['UID' => 1, 'locale' => 'en_US'], [], [], [], [], []));

		$this->assertRedirect($this->process('/playlists'), '/login');
		static::assertSame([], $this->session->data);
	}

	#[Group('units')]
	public function testAGuestWithAValidCookieIsSentAwayFromTheLoginPage(): void
	{
		$this->rememberMeCookieOf(7);

		$this->assertRedirect($this->process('/login'), '/');
		static::assertSame(7, $this->session->get('user')['UID'] ?? null);
	}

	#[Group('units')]
	public function testAGuestWithAnInvalidCookieMayStillOpenTheLoginPage(): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = 'stale';
		$this->authService->method('loginByCookie')->willReturn(null);

		$this->assertHandled($this->process('/login'), '/login');
	}
}
