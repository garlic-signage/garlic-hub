<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use App\Modules\Auth\AuthMiddleware;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Shared\User\UserEntity;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
	private AuthService&MockObject $authServiceMock;
	private Session&MockObject $sessionMock;
	private Cookie&Stub $cookieStub;
	private RequestHandlerInterface&MockObject $handlerMock;
	private ResponseInterface $handledResponse;
	private AuthMiddleware $middleware;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->authServiceMock = $this->createMock(AuthService::class);
		$this->sessionMock     = $this->createMock(Session::class);
		$this->cookieStub      = $this->createStub(Cookie::class);
		$this->handlerMock     = $this->createMock(RequestHandlerInterface::class);
		$this->handledResponse = new Response();

		$this->middleware = new AuthMiddleware($this->authServiceMock, $this->sessionMock, $this->cookieStub);
	}

	/**
	 * @return list<array{string}>
	 */
	public static function providePublicPaths(): array
	{
		return [['/create-initial'], ['/set-locales/de_DE'], ['/smil-index'], ['/force-password'], ['/legals']];
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[DataProvider('providePublicPaths')] #[Group('units')]
	public function testPublicSectionsNeedNoLogin(string $path): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->authServiceMock->expects($this->never())->method($this->anything());
		$this->expectHandled();

		static::assertSame($this->handledResponse, $this->process($path));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testLoggedInUserPasses(): void
	{
		$this->sessionMock->expects($this->once())->method('exists')->with('user')->willReturn(true);
		$this->authServiceMock->expects($this->never())->method('loginByCookie');
		$this->expectHandled();

		static::assertSame($this->handledResponse, $this->process('/playlists'));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testGuestIsRedirectedToLogin(): void
	{
		$this->expectGuestWithoutCookie();
		$this->handlerMock->expects($this->never())->method('handle');

		$response = $this->process('/playlists');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testGuestGetsUnauthorizedJsonForAsync(): void
	{
		$this->expectGuestWithoutCookie();
		$this->handlerMock->expects($this->never())->method('handle');

		$response = $this->process('/async/playlists');

		static::assertSame(401, $response->getStatusCode());
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'message' => 'Unauthorized access. Please log in first.'],
			json_decode((string) $response->getBody(), true));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testGuestGetsUnauthorizedForJsonAcceptHeader(): void
	{
		$this->expectGuestWithoutCookie();
		$this->handlerMock->expects($this->never())->method('handle');

		$response = $this->process('/playlists', ['Accept' => 'application/json']);

		static::assertSame(401, $response->getStatusCode());
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testAutoLoginCookieLogsInAndFillsSession(): void
	{
		$this->sessionMock->method('exists')->willReturn(false);
		$this->expectCookieLogin();
		$this->expectHandled();

		static::assertSame($this->handledResponse, $this->process('/playlists'));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testInvalidAutoLoginCookieRedirectsToLogin(): void
	{
		$this->sessionMock->method('exists')->willReturn(false);
		$this->sessionMock->expects($this->never())->method('set');
		$this->cookieStub->method('hasCookie')
			->willReturnCallback(static fn(string $name): bool => $name === AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		$this->authServiceMock->expects($this->once())->method('loginByCookie')->willReturn(null);
		$this->handlerMock->expects($this->never())->method('handle');

		static::assertSame('/login', $this->process('/playlists')->getHeaderLine('Location'));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testLoginPageIsShownToGuests(): void
	{
		$this->expectGuestWithoutCookie();
		$this->expectHandled();

		static::assertSame($this->handledResponse, $this->process('/login'));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testLoginPageRedirectsLoggedInUsersHome(): void
	{
		$this->sessionMock->method('exists')->willReturn(false);
		$this->expectCookieLogin();
		$this->handlerMock->expects($this->never())->method('handle');

		$response = $this->process('/login');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
	}

	/**
	 * @param array<string,string> $headers
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	private function process(string $path, array $headers = []): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest('GET', $path);
		foreach ($headers as $name => $value)
		{
			$request = $request->withHeader($name, $value);
		}

		return $this->middleware->process($request, $this->handlerMock);
	}

	private function expectGuestWithoutCookie(): void
	{
		$this->sessionMock->expects($this->once())->method('exists')->with('user')->willReturn(false);
		$this->cookieStub->method('hasCookie')->willReturn(false);
		$this->authServiceMock->expects($this->never())->method('loginByCookie');
	}

	private function expectHandled(): void
	{
		$this->handlerMock->expects($this->once())->method('handle')->willReturn($this->handledResponse);
	}

	private function expectCookieLogin(): void
	{
		$userEntityStub = $this->createStub(UserEntity::class);
		$userEntityStub->method('getMain')->willReturn(['UID' => 1, 'locale' => 'en_US']);
		$this->cookieStub->method('hasCookie')
			->willReturnCallback(static fn(string $name): bool => $name === AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		$this->authServiceMock->expects($this->once())->method('loginByCookie')->willReturn($userEntityStub);

		$this->sessionMock->expects($this->exactly(2))->method('set')
			->willReturnCallback(function (string $key, string|array $value): void
			{
				match ($key)
				{
					'user'   => static::assertSame(['UID' => 1, 'locale' => 'en_US'], $value),
					'locale' => static::assertSame('en_US', $value),
					default  => static::fail('unexpected session key ' . $key)
				};
			});
	}
}
