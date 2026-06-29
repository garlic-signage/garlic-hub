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

use App\Framework\Core\Cookie;
use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AutoLoginServiceTest extends TestCase
{
	private  Cookie&Stub $cookieStub;
	private LoggerInterface&Stub $loggerStub;
	private AutoLoginService $autoLoginService;

	protected function setUp(): void
	{
		parent::setUp();
		$userTokenServiceStub = static::createStub(UserTokenService::class);
		$this->cookieStub = static::createStub(Cookie::class);
		$this->loggerStub = static::createStub(LoggerInterface::class);
		$this->autoLoginService = new AutoLoginService(
			$userTokenServiceStub,
			$this->cookieStub,
			$this->loggerStub
		);
	}

	#[Group('units')]
	public function testLoadTokenFromCookie(): void
	{
		$token = 'valid_token';
		$this->cookieStub->method('hasCookie')->willReturn(true);
		$this->cookieStub->method('getCookie')->willReturn($token);

		static::assertSame($token, $this->autoLoginService->loadTokenFromCookie());
	}

	#[Group('units')]
	public function testLoadTokenFromCookieHasNoCookie(): void
	{
		$this->cookieStub->method('hasCookie')->willReturn(false);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('No cookie for autologin was found.');

		$this->autoLoginService->loadTokenFromCookie();
	}

	#[Group('units')]
	public function testLoadTokenFromCookieHasInvalidCookie(): void
	{
		$this->cookieStub->method('hasCookie')->willReturn(true);
		$this->cookieStub->method('getCookie')->willReturn(null);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('No token in cookie cookie found.');

		$this->autoLoginService->loadTokenFromCookie();
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testLoginSilentTokenNotFound(): void
	{
		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->method('findByToken')->willReturn(null);

		$service = $this->buildService($userTokenServiceMock, $this->cookieStub);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('Autologin token not found.');

		$service->loginSilent('some_token');
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoginSilentTokenExpired(): void
	{
		$dbToken = ['UID' => 1, 'expires_at' => '2000-01-01 00:00:00', 'used_at' => null];

		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->method('findByToken')->willReturn($dbToken);
		$userTokenServiceMock->expects($this->once())->method('deleteExpiredToken');

		$service = $this->buildService($userTokenServiceMock, $this->cookieStub);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('Autologin expired.');

		$service->loginSilent('expired_token');
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testLoginSilentTokenAlreadyUsed(): void
	{
		$dbToken = ['UID' => 5, 'expires_at' => '2999-01-01 00:00:00', 'used_at' => '2026-01-01 00:00:00'];

		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->method('findByToken')->willReturn($dbToken);
		$userTokenServiceMock->expects($this->once())
			->method('deleteAllUserTokens')
			->with(5);

		$service = $this->buildService($userTokenServiceMock, $this->cookieStub);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('Cookie manipulation. Contact administrator.');

		$service->loginSilent('used_token');
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testLoginSilentSucceeds(): void
	{
		$dbToken = ['UID' => 42, 'expires_at' => '2999-01-01 00:00:00', 'used_at' => null];

		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->method('findByToken')->willReturn($dbToken);
		$userTokenServiceMock->expects($this->once())
			->method('useToken')
			->with('valid_token');
		$userTokenServiceMock->method('generateToken')->willReturn('rotated_token');
		$userTokenServiceMock->expects($this->once())
			->method('insertToken')
			->with(42, 'rotated_token', TokenPurposes::AUTOLOGIN);

		$cookieMock = $this->createMock(Cookie::class);
		$cookieMock->expects($this->once())
			->method('createCookie')
			->with(
				AutoLoginService::COOKIE_NAME_AUTO_LOGIN,
				'rotated_token',
				static::isInstanceOf(DateTime::class)
			);

		$service = $this->buildService($userTokenServiceMock, $cookieMock);

		static::assertSame(42, $service->loginSilent('valid_token'));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateAutologinCookie(): void
	{
		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->method('generateToken')->willReturn('new_token');
		$userTokenServiceMock->expects($this->once())
			->method('insertToken')
			->with(7, 'new_token', TokenPurposes::AUTOLOGIN);

		$cookieMock = $this->createMock(Cookie::class);
		$cookieMock->expects($this->once())
			->method('createCookie')
			->with(
				AutoLoginService::COOKIE_NAME_AUTO_LOGIN,
				'new_token',
				static::isInstanceOf(DateTime::class)
			);

		$service = $this->buildService($userTokenServiceMock, $cookieMock);

		$service->createAutologinCookie(7);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRemoveAutoLoginWithToken(): void
	{
		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->expects($this->once())
			->method('deleteByToken')
			->with('some_token');

		$cookieMock = $this->createMock(Cookie::class);
		$cookieMock->method('getCookie')->willReturn('some_token');
		$cookieMock->expects($this->once())
			->method('deleteCookie')
			->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);

		$service = $this->buildService($userTokenServiceMock, $cookieMock);

		$service->removeAutoLogin();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRemoveAutoLoginWithoutToken(): void
	{
		$userTokenServiceMock = $this->createMock(UserTokenService::class);
		$userTokenServiceMock->expects($this->never())->method('deleteByToken');

		$cookieMock = $this->createMock(Cookie::class);
		$cookieMock->method('getCookie')->willReturn(null);
		$cookieMock->expects($this->once())
			->method('deleteCookie')
			->with(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);

		$service = $this->buildService($userTokenServiceMock, $cookieMock);

		$service->removeAutoLogin();
	}

	private function buildService(UserTokenService&Stub $userTokenService, Cookie&Stub $cookie): AutoLoginService
	{
		return new AutoLoginService($userTokenService, $cookie, $this->loggerStub);
	}

}
