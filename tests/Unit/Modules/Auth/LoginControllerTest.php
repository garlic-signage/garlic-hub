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

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Http\Cookie;
use App\Framework\Security\CsrfToken;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\LoginController;
use App\Shared\User\UserEntity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

class LoginControllerTest extends TestCase
{
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&MockObject $responseMock;
	private Session&MockObject $sessionMock;
	private AuthService&MockObject $authServiceMock;
	private CsrfToken&Stub $csrfTokenStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestStub     = $this->createStub(ServerRequestInterface::class);
		$this->responseMock    = $this->createMock(ResponseInterface::class);
		$this->sessionMock     = $this->createMock(Session::class);
		$this->authServiceMock = $this->createMock(AuthService::class);
		$this->csrfTokenStub   = $this->createStub(CsrfToken::class);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testShowLoginRendersForm(): void
	{
		$translatorMock = $this->createMock(Translator::class);
		$this->requestStub->method('getAttribute')->willReturnMap([['translator', null, $translatorMock]]);
		$this->sessionMock->expects($this->never())->method($this->anything());

		$translatorMock->expects($this->exactly(5))->method('translate');
		$this->authServiceMock->expects($this->never())->method($this->anything());

		$this->responseMock->expects($this->never())->method($this->anything());

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $this->createStub(Messages::class));
		$result = $controller->showLogin($this->requestStub, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('auth/login', $result->getTemplate());
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginSuccessfulLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createStub(UserEntity::class);

		$this->requestStub->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->authServiceMock->expects($this->once())->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$flash->expects($this->never())->method('addMessage');
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

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginCreateAutologin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createStub(UserEntity::class);

		$this->requestStub->method('getParsedBody')->willReturn([
			'username' => 'testuser',
			'password' => 'password',
			'autologin' => '1',
			'csrf_token' => 'token'
		]);
		$this->authServiceMock->expects($this->once())->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$flash->expects($this->never())->method('addMessage');
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

		$this->authServiceMock->expects($this->once())->method('createAutologin');
		$main_data = ['locale' => 'kl_KL', 'UID' => 1];
		$userEntity->method('getMain')->willReturn($main_data);

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToApiOnSuccessfulLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createStub(UserEntity::class);

		$this->requestStub->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->authServiceMock->expects($this->once())->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$flash->expects($this->never())->method('addMessage');

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

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToApiOnFailedLogin(): void
	{
		$flash = $this->createMock(Messages::class);
		$userEntity = $this->createStub(UserEntity::class);

		$main_data = ['locale' => 'kl_KL'];
		$userEntity->method('getMain')->willReturn($main_data);

		$this->requestStub->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'password', 'csrf_token' => 'token']);
		$this->authServiceMock->expects($this->once())->method('login')->with('testuser', 'password')->willReturn($userEntity);
		$flash->expects($this->never())->method('addMessage');

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

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}


	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToLoginOnInvalidCredentials(): void
	{
		$flash      = $this->createMock(Messages::class);

		$this->requestStub->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'wrong_password', 'csrf_token' => 'token']);
		$this->sessionMock->expects($this->once())->method('exists')->with('csrf_token')->willReturn(true);
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');

		$this->authServiceMock->expects($this->once())->method('login')
			->with('testuser', 'wrong_password')
			->willReturn(null);
		$this->authServiceMock->expects($this->once())->method('getErrorMessage')->willReturn('Invalid credentials.');

		$flash->expects($this->once())->method('addMessage')->with('error', 'Invalid credentials.');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception|UserException
	 */
	#[Group('units')]
	public function testLoginRedirectsToLoginOnTokenMismatch(): void
	{
		$flash      = $this->createMock(Messages::class);

		$this->requestStub->method('getParsedBody')->willReturn(['username' => 'testuser', 'password' => 'wrong_password', 'csrf_token' => 'token1']);
		$this->sessionMock->expects($this->once())->method('exists')->with('csrf_token')->willReturn(true);
		$this->sessionMock->expects($this->once())->method('get')->with('csrf_token')->willReturn('token');

		$flash->expects($this->once())->method('addMessage')->with('error', 'Invalid CSRF token');
		$this->authServiceMock->expects($this->never())->method('login');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $flash);
		$result = $controller->login($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}


	/**
	 * @return void
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLogoutRedirectsToLogin(): void
	{
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn(['UID' => 88]);
		$this->sessionMock->expects($this->once())->method('clear');
		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		$this->authServiceMock->expects($this->once())->method('logout');
		$this->sessionMock->expects($this->once())->method('regenerateID');

		$controller = new LoginController($this->authServiceMock, $this->csrfTokenStub, $this->sessionMock, $this->createStub(Messages::class));
		$result = $controller->logout($this->requestStub, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}
}
