<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Core\Cookie;
use App\Framework\Core\Session;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Middleware\AuthMiddleware;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Profile\Entities\UserEntity;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private RequestHandlerInterface&MockObject $handlerMock;
	private AuthService&MockObject $authServiceMock;
	private Session&MockObject $sessionMock;
	private Cookie&MockObject $cookieMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->authServiceMock = $this->createMock(AuthService::class);
		$this->sessionMock = $this->createMock(Session::class);
		$this->cookieMock = $this->createMock(Cookie::class);
		$this->handlerMock = $this->createMock(RequestHandlerInterface::class);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|Exception
	 */
	#[Group('units')]
	public function testProcessHandlesPublicRoutes(): void
	{
		$uriInterfaceMock = $this->createMock(UriInterface::class);
		$this->requestMock->method('getUri')->willReturn($uriInterfaceMock);
		$uriInterfaceMock->method('getPath')->willReturn('/register');
		$this->requestMock->expects($this->never())->method('getAttribute');

		$this->handlerMock
			->expects($this->once())
			->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceMock);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfSessionNull(): void
	{
		$this->mockSecurePage();

		$this->requestMock->method('getAttribute')->with('session')->willReturn(null);

		$middleware = new AuthMiddleware($this->authServiceMock);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotAuth(): void
	{
		$this->mockSecurePage();
		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'cookie')
					return $this->cookieMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
		);

		$this->sessionMock->method('exists')->with('user')->willReturn(false);

		$middleware = new AuthMiddleware($this->authServiceMock);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotAuthBecauseOfCookie(): void
	{
		$this->mockSecurePage();
		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'cookie')
					return null;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
			);

		$this->sessionMock->method('exists')->with('user')->willReturn(false);

		$middleware = new AuthMiddleware($this->authServiceMock);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotWithLogin(): void
	{
		$this->mockPage('/login');
		$this->requestMock->method('getAttribute')->with('session')->willReturn(null);


		$middleware = new AuthMiddleware($this->authServiceMock);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotWithApiAccess(): void
	{
		$this->mockPage('/async/some-protected-api-call');
		$this->requestMock->method('getAttribute')->with('session')->willReturn(null);


		$middleware = new AuthMiddleware($this->authServiceMock);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(401, $response->getStatusCode());
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessHandlesAuthenticatedUser(): void
	{
		$this->mockSecurePage();
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('exists')->with('user')->willReturn(true);

		$this->handlerMock
			->expects($this->once())
			->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceMock);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessAutoLoginWithCookie(): void
	{
		$this->mockSecurePage();
		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'cookie')
					return $this->cookieMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
		);

		$this->sessionMock->method('exists')->with('user')->willReturn(false);

		$userEntityMock = $this->createMock(UserEntity::class);
		$this->authServiceMock->method('loginByCookie')->willReturn($userEntityMock);
		$this->cookieMock->method('hasCookie')->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN)->willReturn(true);
		$userEntityMock->method('getMain')->willReturn(['UID' => 1, 'locale' => 'en']);

		$this->sessionMock->expects($this->exactly(2))->method('set');

		$this->handlerMock->expects($this->once())->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceMock);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessAutoLoginWithCookieAndLogin(): void
	{
		$this->mockPage('/login');

		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'cookie')
					return $this->cookieMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
			);

		$this->sessionMock->method('exists')->with('user')->willReturn(false);

		$userEntityMock = $this->createMock(UserEntity::class);
		$this->authServiceMock->method('loginByCookie')->willReturn($userEntityMock);
		$this->cookieMock->method('hasCookie')->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN)->willReturn(true);
		$userEntityMock->method('getMain')->willReturn(['UID' => 1, 'locale' => 'en']);

		$this->sessionMock->expects($this->exactly(2))->method('set');

		$middleware = new AuthMiddleware($this->authServiceMock);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 */
	private function mockSecurePage(): void
	{
		$this->mockPage('/secure-page');
	}

	/**
	 * @throws Exception
	 */
	private function mockPage(string $page): void
	{
		$uriInterfaceMock = $this->createMock(UriInterface::class);
		$this->requestMock->method('getUri')->willReturn($uriInterfaceMock);
		$uriInterfaceMock->method('getPath')->willReturn($page);
	}

}
