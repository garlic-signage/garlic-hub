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

use App\Framework\Exceptions\AuthException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Profile\Entities\UserEntity;
use App\Modules\Users\Services\UsersService;
use DateMalformedStringException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class AuthServiceTest extends TestCase
{
	private AuthService $authService;
	private UsersService&Stub $userServiceStub;
	private AutoLoginService&Stub $autoLoginServiceStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userServiceStub       = static::createStub(UsersService::class);
		$this->autoLoginServiceStub  = static::createStub(AutoLoginService::class);
		$loggerStub                  = static::createStub(LoggerInterface::class);

		$this->authService = new AuthService(
			$this->userServiceStub,
			$this->autoLoginServiceStub,
			$loggerStub
		);
	}

	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginSuccess(): void
	{
		$identifier = 'user@example.com';
		$password = 'correct_password';
		$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
		$userData = [
			'UID' => 1,
			'password' => $hashedPassword,
			'status' => UsersService::USER_STATUS_REGULAR,
		];

		$this->userServiceStub->method('findUser')->willReturn($userData);
		$userEntityStub = static::createStub(UserEntity::class);
		$this->userServiceStub->method('getUserById')->willReturn($userEntityStub);

		$userEntity = $this->authService->login($identifier, $password);

		static::assertInstanceOf(UserEntity::class, $userEntity);
		static::assertEmpty($this->authService->getErrorMessage());
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginInvalidCredentials(): void
	{
		$identifier = 'user@example.com';
		$password = 'wrong_password';

		$this->userServiceStub->method('findUser')->willReturn([
			'UID' => 1,
			'password' => password_hash('correct_password', PASSWORD_BCRYPT),
		]);

		$userEntity = $this->authService->login($identifier, $password);

		static::assertNull($userEntity);
		static::assertEquals('Invalid credentials.', $this->authService->getErrorMessage());
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginUserDeleted(): void
	{
		$identifier = 'deleted@example.com';
		$password = 'irrelevant_password';

		$this->userServiceStub->method('findUser')->with($identifier)->willReturn([
			'UID' => 1,
			'password' => password_hash($password, PASSWORD_BCRYPT),
			'status' => UsersService::USER_STATUS_DELETED,
		]);

		$userEntity = $this->authService->login($identifier, $password);

		static::assertNull($userEntity);
		static::assertEquals('login//account_deleted', $this->authService->getErrorMessage());
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginUserNotActive(): void
	{
		$identifier = 'deleted@example.com';
		$password = 'irrelevant_password';

		$this->userServiceStub->method('findUser')->with($identifier)->willReturn([
			'UID' => 1,
			'password' => password_hash($password, PASSWORD_BCRYPT),
			'status' => UsersService::USER_STATUS_REGISTERED,
		]);

		$userEntity = $this->authService->login($identifier, $password);

		static::assertNull($userEntity);
		static::assertEquals('login//account_inactive', $this->authService->getErrorMessage());
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginByCookieSuccess(): void
	{
		$cookieToken    = 'valid_token';
		$UID            = 341;
		$userEntityStub = static::createStub(UserEntity::class);

		$this->autoLoginServiceStub->method('loadTokenFromCookie')->willReturn($cookieToken);
		$this->autoLoginServiceStub->method('loginSilent')->willReturn($UID);

		$this->userServiceStub->method('getUserById')->willReturn($userEntityStub);

		$userEntityStub->method('getMain')->willReturn(['status' => UsersService::USER_STATUS_REGULAR]);

		$userEntity = $this->authService->loginByCookie();

		static::assertInstanceOf(UserEntity::class, $userEntity);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoginByCookieFails(): void
	{
		$this->autoLoginServiceStub->method('loadTokenFromCookie')
			->willThrowException(new AuthException('No cookie found.'));

		static::assertNull($this->authService->loginByCookie());
		static::assertSame('No cookie found.', $this->authService->getErrorMessage());
	}
}
