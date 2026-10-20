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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private RequestHandlerInterface&MockObject $handlerMock;
	private AuthService&Stub $authServiceStub;
	private Session&MockObject $sessionMock;
	private Cookie&MockObject $cookieMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->authServiceStub = $this->createStub(AuthService::class);
		$this->sessionMock = $this->createMock(Session::class);
		$this->cookieMock = $this->createMock(Cookie::class);
		$this->handlerMock = $this->createMock(RequestHandlerInterface::class);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
	}

	/**
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|Exception
	 */
	#[Group('units')]
	public function testProcessHandlesPublicRoutes(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->cookieMock->expects($this->never())->method($this->anything());
		$uriInterfaceStub = $this->createStub(UriInterface::class);
		$this->requestMock->method('getUri')->willReturn($uriInterfaceStub);
		$uriInterfaceStub->method('getPath')->willReturn('/register');
		$this->requestMock->expects($this->never())->method('getAttribute');

		$this->handlerMock
			->expects($this->once())
			->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceStub);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfSessionNull(): void
	{
		$this->expectUntouched($this->handlerMock, $this->sessionMock, $this->cookieMock);
		$this->mockSecurePage();

		$this->requestMock->method('getAttribute')->with('session')->willReturn(null);

		$middleware = new AuthMiddleware($this->authServiceStub);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotAuth(): void
	{
		$this->mockSecurePage();
		$this->requestMock->expects($this->atLeastOnce())->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'cookie')
					return $this->cookieMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
		);

		$this->sessionMock->expects($this->once())->method('exists')->with('user')->willReturn(false);
		$this->sessionMock->expects($this->never())->method('set');
		// without autologin cookie there is no login attempt
		$this->cookieMock->expects($this->once())->method('hasCookie')->willReturn(false);
		$this->handlerMock->expects($this->never())->method('handle');

		$middleware = new AuthMiddleware($this->authServiceStub);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotAuthBecauseOfCookie(): void
	{
		$this->handlerMock->expects($this->never())->method($this->anything());
		$this->cookieMock->expects($this->never())->method($this->anything());
		$this->mockSecurePage();
		$this->requestMock->expects($this->atLeastOnce())->method('getAttribute')
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

		$middleware = new AuthMiddleware($this->authServiceStub);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(302, $response->getStatusCode());
		static::assertEquals(['/login'], $response->getHeader('Location'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotWithLogin(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->cookieMock->expects($this->never())->method($this->anything());
		$this->mockPage('/login');
		$this->requestMock->expects($this->once())->method('getAttribute')->with('session')->willReturn(null);
		// the login page stays reachable without a session
		$response = $this->createStub(\Psr\Http\Message\ResponseInterface::class);
		$this->handlerMock->expects($this->once())->method('handle')->with($this->requestMock)->willReturn($response);

		$middleware = new AuthMiddleware($this->authServiceStub);
		static::assertSame($response, $middleware->process($this->requestMock, $this->handlerMock));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessRedirectsToLoginIfNotWithApiAccess(): void
	{
		$this->expectUntouched($this->handlerMock, $this->sessionMock, $this->cookieMock);
		$this->mockPage('/async/some-protected-api-call');
		$this->requestMock->method('getAttribute')->with('session')->willReturn(null);


		$middleware = new AuthMiddleware($this->authServiceStub);
		$response = $middleware->process($this->requestMock, $this->handlerMock);

		static::assertEquals(401, $response->getStatusCode());
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessHandlesAuthenticatedUser(): void
	{
		$this->cookieMock->expects($this->never())->method($this->anything());
		$this->mockSecurePage();
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('exists')->with('user')->willReturn(true);

		$this->handlerMock
			->expects($this->once())
			->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceStub);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessAutoLoginWithCookie(): void
	{
		$this->mockSecurePage();
		$this->requestMock->expects($this->atLeastOnce())->method('getAttribute')
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

		$userEntityStub = $this->createStub(UserEntity::class);
		$this->authServiceStub->method('loginByCookie')->willReturn($userEntityStub);
		$this->cookieMock->method('hasCookie')->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN)->willReturn(true);
		$userEntityStub->method('getMain')->willReturn(['UID' => 1, 'locale' => 'en']);

		$this->sessionMock->expects($this->exactly(2))->method('set');

		$this->handlerMock->expects($this->once())->method('handle')
			->with($this->requestMock)
			->willReturn(new Response());

		$middleware = new AuthMiddleware($this->authServiceStub);
		$middleware->process($this->requestMock, $this->handlerMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessAutoLoginWithCookieAndLogin(): void
	{
		$this->handlerMock->expects($this->never())->method($this->anything());
		$this->mockPage('/login');

		$this->requestMock->expects($this->atLeastOnce())->method('getAttribute')
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

		$userEntityStub = $this->createStub(UserEntity::class);
		$this->authServiceStub->method('loginByCookie')->willReturn($userEntityStub);
		$this->cookieMock->method('hasCookie')->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN)->willReturn(true);
		$userEntityStub->method('getMain')->willReturn(['UID' => 1, 'locale' => 'en']);

		$this->sessionMock->expects($this->exactly(2))->method('set');

		$middleware = new AuthMiddleware($this->authServiceStub);
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
		$uriInterfaceStub = $this->createStub(UriInterface::class);
		$this->requestMock->method('getUri')->willReturn($uriInterfaceStub);
		$uriInterfaceStub->method('getPath')->willReturn($page);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
