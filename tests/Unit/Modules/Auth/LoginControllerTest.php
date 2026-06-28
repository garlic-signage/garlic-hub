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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Core\Cookie;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\LoginController;
use App\Modules\Profile\Entities\UserEntity;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Slim\Flash\Messages;

class LoginControllerTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private Session&MockObject $sessionMock;
	private AuthService&MockObject $authServiceMock;
	private CsrfToken&MockObject $csrfTokenMock;
	// @phpstan-ignore-next-line
	private LoggerInterface&MockObject $loggerMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock  = $this->createMock(Translator::class);
		$this->requestMock     = $this->createMock(ServerRequestInterface::class);
		$this->responseMock    = $this->createMock(ResponseInterface::class);
		$this->sessionMock     = $this->createMock(Session::class);
		$this->authServiceMock = $this->createMock(AuthService::class);
		$this->loggerMock      = $this->createMock(LoggerInterface::class);
		$this->csrfTokenMock   = $this->createMock(CsrfToken::class);
	}

	/**
	 * @throws Exception|\Psr\SimpleCache\InvalidArgumentException
	 */
	#[Group('units')]
	public function testShowLoginRendersForm(): void
	{
		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param)
			{
				if ($param === 'translator')
					return $this->translatorMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
		);

		$this->translatorMock->expects($this->exactly(5))->method('translate');

		$body = $this->createMock(StreamInterface::class);
		$this->responseMock->method('getBody')->willReturn($body);

		$body->expects($this->once())->method('write');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->showLogin($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginSuccessfulLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createMock(UserEntity::class);

		$this->requestMock->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->authServiceMock->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$this->sessionMock->expects($this->exactly(2))->method('exists')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return true;
			elseif ($attribute === 'oauth_redirect_params')
				return false;
			return null;
		});
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');
		$this->sessionMock->expects($this->exactly(2))->method('set');

		$main_data = ['locale' => 'kl_KL'];
		$userEntity->method('getMain')->willReturn($main_data);
		$this->authServiceMock->expects($this->never())->method('createAutologin');


		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginCreateAutologin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createMock(UserEntity::class);

		$this->requestMock->method('getParsedBody')->willReturn([
			'username' => 'testuser',
			'password' => 'password',
			'autologin' => '1',
			'csrf_token' => 'token'
		]);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->authServiceMock->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$this->sessionMock->expects($this->exactly(2))->method('exists')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return true;
			elseif ($attribute === 'oauth_redirect_params')
				return false;
			return null;
		});
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');
		$this->sessionMock->expects($this->exactly(2))->method('set');
		$this->sessionMock->expects($this->once())->method('id')->willReturn('1234567890');

		$this->authServiceMock->expects($this->once())->method('createAutologin');
		$main_data = ['locale' => 'kl_KL', 'UID' => 1];
		$userEntity->method('getMain')->willReturn($main_data);

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}



	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginCreateAutologinNoSessionId(): void
	{
		$flashMock  = $this->createMock(Messages::class);
		$userEntity = $this->createMock(UserEntity::class);

		$this->requestMock->method('getParsedBody')->willReturn([
			'username' => 'testuser',
			'password' => 'password',
			'autologin' => '1',
			'csrf_token' => 'token'
		]);
		$main_data = ['locale' => 'kl_KL', 'UID' => 1];
		$userEntity->method('getMain')->willReturn($main_data);

		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flashMock);
		$this->authServiceMock->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$this->sessionMock->expects($this->once())->method('exists')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return true;
			elseif ($attribute === 'oauth_redirect_params')
				return false;
			return null;
		});
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');
		$this->sessionMock->expects($this->exactly(2))->method('set');
		$this->sessionMock->expects($this->once())->method('id')->willReturn(false);

		$this->authServiceMock->expects($this->never())->method('createAutologin');

		$flashMock->expects($this->once())->method('addMessage')->with('error', 'No session id found');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}


	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToApiOnSuccessfulLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createMock(UserEntity::class);

		$this->requestMock->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->authServiceMock->method('login')->with('testuser', 'password')->willReturn($userEntity);

		$this->sessionMock->expects($this->exactly(2))->method('exists')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return true;
			elseif ($attribute === 'oauth_redirect_params')
				return true;
			return null;
		});

		$this->sessionMock->expects($this->exactly(2))->method('get')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return 'token';
			elseif ($attribute === 'oauth_redirect_params')
				return ['some' => 'stuff'];
			return null;
		});
		$this->sessionMock->expects($this->once())->method('delete')->with('oauth_redirect_params');

		$main_data = ['locale' => 'kl_KL'];
		$userEntity->method('getMain')->willReturn($main_data);


		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/api/authorize?some=stuff')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToApiOnFailedLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createMock(UserEntity::class);

		$main_data = ['locale' => 'kl_KL'];
		$userEntity->method('getMain')->willReturn($main_data);

		$this->requestMock->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->authServiceMock->method('login')->with('testuser', 'password')->willReturn($userEntity);

		$this->sessionMock->expects($this->exactly(2))->method('exists')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return true;
			elseif ($attribute === 'oauth_redirect_params')
				return true;
			return null;
		});

		$this->sessionMock->expects($this->exactly(2))->method('get')->willReturnCallback(function ($attribute)
		{
			if ($attribute === 'csrf_token')
				return 'token';
			elseif ($attribute === 'oauth_redirect_params')
				return '';
			return null;
		});
		$this->sessionMock->expects($this->never())->method('delete');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}


	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToLoginOnInvalidCredentials(): void
	{
		$flash      = $this->createMock(Messages::class);

		$this->requestMock->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'wrong_password', 'csrf_token' => 'token']);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->sessionMock->expects($this->once())->method('exists')->with('csrf_token')->willReturn(true);
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');

		$this->authServiceMock->method('login')
			->with('testuser', 'wrong_password')
			->willReturn(null);
		$this->authServiceMock->method('getErrorMessage')->willReturn('Invalid credentials.');

		$flash->expects($this->once())->method('addMessage')->with('error', 'Invalid credentials.');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToLoginOnTokenMismatch(): void
	{
		$flash      = $this->createMock(Messages::class);

		$this->requestMock->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'wrong_password', 'csrf_token' => 'token1']);
		$this->requestMock->method('getAttribute')->willReturnOnConsecutiveCalls($this->sessionMock, $flash);
		$this->sessionMock->expects($this->once())->method('exists')->with('csrf_token')->willReturn(true);
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');

		$flash->expects($this->once())->method('addMessage')->with('error', 'Invalid CSRF token');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->login($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}


	/**
	 * @return void
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLogoutRedirectsToLogin(): void
	{
		$cookieMock = $this->createMock(Cookie::class);

		$this->requestMock->method('getAttribute')
			->willReturnCallback(function ($param) use ($cookieMock)
			{
				if ($param === 'cookie')
					return $cookieMock;
				elseif ($param === 'session')
					return $this->sessionMock;
				return null;
			}
			);
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn(['UID' => 88]);
		$this->sessionMock->expects($this->once())->method('clear');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$cookieMock->method('deleteCookie')->with(AuthService::COOKIE_NAME_AUTO_LOGIN);
		$this->sessionMock->expects($this->once())->method('regenerateID');

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenMock);
		$result = $controller->logout($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}
}
