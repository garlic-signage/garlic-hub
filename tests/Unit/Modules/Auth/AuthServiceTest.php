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

use App\Framework\Exceptions\AuthException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use Doctrine\DBAL\Exception\InvalidArgumentException as DbalException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayCache;

/**
 * The service works with the real LoginThrottle. The stubs are the borders: the database (UsersService)
 * and the cookie (AutoLoginService). The whole login with the database is tested in tests/Integration/Modules/Auth.
 */
class AuthServiceTest extends TestCase
{
	private const string PASSWORD = 'correct_password';

	private UsersService&Stub $userService;
	private AutoLoginService&Stub $autoLoginService;
	private TestHandler $logs;
	private AuthService $authService;

	/** @var array<string,array{UID:int,password:string,status:int}> users by identifier */
	private array $users = [];
	/** @var list<array{int,string}> the hashes which were stored */
	private array $storedHashes = [];
	private int $removedCookies = 0;
	private ?string $remoteAddress;

	protected function setUp(): void
	{
		parent::setUp();
		$this->remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$this->users = ['user@example.com' => ['UID' => 1, 'password' => $this->hash(self::PASSWORD), 'status' => UsersService::USER_STATUS_REGULAR]];

		$this->userService = $this->createStub(UsersService::class);
		$this->userService->method('findUser')->willReturnCallback(fn(string $identifier): array => $this->users[$identifier] ?? []);
		$this->userService->method('getUserById')->willReturnCallback(fn(int $UID): UserEntity => $this->entity($UID));
		$this->userService->method('updatePasswordHash')->willReturnCallback(function (int $UID, string $hash): int
		{
			$this->storedHashes[] = [$UID, $hash];

			return 1;
		});

		$this->autoLoginService = $this->createStub(AutoLoginService::class);
		$this->autoLoginService->method('removeAutoLogin')->willReturnCallback(function (): void { $this->removedCookies++; });

		$this->logs        = new TestHandler();
		$this->authService = new AuthService($this->userService, $this->autoLoginService, new Logger('test', [$this->logs]), new LoginThrottle(new ArrayCache()));
	}

	protected function tearDown(): void
	{
		if ($this->remoteAddress === null)
			unset($_SERVER['REMOTE_ADDR']);
		else
			$_SERVER['REMOTE_ADDR'] = $this->remoteAddress;

		parent::tearDown();
	}

	/**
	 * A cheap hash: the tests need no expensive one.
	 */
	private function hash(string $password, int $cost = 4): string
	{
		return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
	}

	private function entity(int $UID): UserEntity
	{
		$status = UsersService::USER_STATUS_REGULAR;
		foreach ($this->users as $user)
		{
			if ($user['UID'] === $UID)
				$status = $user['status'];
		}

		return new UserEntity(['UID' => $UID, 'status' => $status, 'username' => 'user', 'locale' => 'en_US'], [], [], [], [], []);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testALoginWithTheRightPasswordGivesTheUser(): void
	{
		$entity = $this->authService->login('user@example.com', self::PASSWORD);

		static::assertInstanceOf(UserEntity::class, $entity);
		static::assertSame(1, $entity->getMain()['UID']);
		static::assertSame('', $this->authService->getErrorMessage());
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function wrongCredentialProvider(): array
	{
		return [
			'wrong password'  => ['user@example.com', 'wrong'],
			'empty password'  => ['user@example.com', ''],
			'unknown user'    => ['nobody@example.com', self::PASSWORD],
			'empty user'      => ['', self::PASSWORD],
		];
	}

	/**
	 * Unknown user and wrong password get the same answer, otherwise names can be collected.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('wrongCredentialProvider')]
	public function testWrongCredentialsAreAllTheSame(string $identifier, string $password): void
	{
		static::assertNull($this->authService->login($identifier, $password));
		static::assertSame('Invalid credentials.', $this->authService->getErrorMessage());
	}

	/**
	 * @return array<string,array{int,string}> the status and the error, '' for a user who may log in
	 */
	public static function statusProvider(): array
	{
		return [
			'deleted'        => [UsersService::USER_STATUS_DELETED, 'login//account_deleted'],
			'locked'         => [UsersService::USER_STATUS_LOCKED, 'login//account_locked'],
			'not verified'   => [UsersService::USER_STATUS_REGISTERED, 'login//account_inactive'],
			'regular'        => [UsersService::USER_STATUS_REGULAR, ''],
			'premium'        => [5, ''],
			'technician'     => [8, ''],
			'admin'          => [9, ''],
		];
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('statusProvider')]
	public function testOnlyUsersWithAnAllowedStatusGetIn(int $status, string $error): void
	{
		$this->users['user@example.com']['status'] = $status;

		$entity = $this->authService->login('user@example.com', self::PASSWORD);

		static::assertSame($error === '', $entity !== null);
		static::assertSame($error, $this->authService->getErrorMessage());
	}

	/**
	 * A user who can not log in learns that only after the password was right: the status is no hint for a stranger.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheStatusOfAUserIsNotToldWithAWrongPassword(): void
	{
		$this->users['user@example.com']['status'] = UsersService::USER_STATUS_DELETED;

		static::assertNull($this->authService->login('user@example.com', 'wrong'));
		static::assertSame('Invalid credentials.', $this->authService->getErrorMessage());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTooManyFailuresLockEvenTheRightPassword(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->authService->login('user@example.com', 'wrong');

		static::assertNull($this->authService->login('user@example.com', self::PASSWORD));
		static::assertSame('Too many failed login attempts. Please try again later.', $this->authService->getErrorMessage());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheLockIsForTheAddressAndTheUser(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->authService->login('user@example.com', 'wrong');

		$_SERVER['REMOTE_ADDR'] = '10.0.0.2';

		static::assertNotNull($this->authService->login('user@example.com', self::PASSWORD));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testARightPasswordStartsTheCountAgain(): void
	{
		for ($round = 0; $round < 2; $round++)
		{
			for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP - 1; $i++)
				$this->authService->login('user@example.com', 'wrong');
			static::assertNotNull($this->authService->login('user@example.com', self::PASSWORD));
		}
	}

	/**
	 * The service lives for the whole request. A failed login by cookie before (the AuthMiddleware tries it
	 * for every guest with a cookie) must not stop the login by password.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAFailedLoginByCookieBeforeDoesNotStopTheLoginByPassword(): void
	{
		$this->autoLoginService->method('loadTokenFromCookie')->willThrowException(new AuthException('Autologin expired.'));
		static::assertNull($this->authService->loginByCookie());
		static::assertSame('Autologin expired.', $this->authService->getErrorMessage());

		$entity = $this->authService->login('user@example.com', self::PASSWORD);

		static::assertNotNull($entity);
		static::assertSame('', $this->authService->getErrorMessage());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAFailedLoginByPasswordBeforeDoesNotStopTheLoginByCookie(): void
	{
		$this->autoLoginService->method('loadTokenFromCookie')->willReturn('token');
		$this->autoLoginService->method('loginSilent')->willReturn(1);
		$this->authService->login('user@example.com', 'wrong');

		static::assertNotNull($this->authService->loginByCookie());
		static::assertSame('', $this->authService->getErrorMessage());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheLoginByCookieGivesTheUser(): void
	{
		$this->autoLoginService->method('loadTokenFromCookie')->willReturn('token');
		$this->autoLoginService->method('loginSilent')->willReturn(1);

		$entity = $this->authService->loginByCookie();

		static::assertSame(1, $entity?->getMain()['UID']);
		static::assertSame(0, $this->removedCookies);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('statusProvider')]
	public function testTheLoginByCookieAsksForTheStatusToo(int $status, string $error): void
	{
		$this->users['user@example.com']['status'] = $status;
		$this->autoLoginService->method('loadTokenFromCookie')->willReturn('token');
		$this->autoLoginService->method('loginSilent')->willReturn(1);

		$entity = $this->authService->loginByCookie();

		static::assertSame($error === '', $entity !== null);
		static::assertSame($error, $this->authService->getErrorMessage());
		// the cookie of a user who may not log in is taken away, it would be tried at every request
		static::assertSame($error === '' ? 0 : 1, $this->removedCookies);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAnOutdatedHashIsReplacedAndStillAcceptsThePassword(): void
	{
		// PHP asks for a higher cost today than the 4 of the test hashes
		$this->authService->login('user@example.com', self::PASSWORD);

		static::assertCount(1, $this->storedHashes);
		[$UID, $hash] = $this->storedHashes[0];
		static::assertSame(1, $UID);
		static::assertTrue(password_verify(self::PASSWORD, $hash));
		static::assertFalse(password_needs_rehash($hash, PASSWORD_DEFAULT));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testACurrentHashStaysAsItIs(): void
	{
		$this->users['user@example.com']['password'] = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

		$this->authService->login('user@example.com', self::PASSWORD);

		static::assertSame([], $this->storedHashes);
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function noRenewalProvider(): array
	{
		return [
			'wrong password' => ['user@example.com', 'wrong'],
			'unknown user'   => ['nobody@example.com', self::PASSWORD],
		];
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('noRenewalProvider')]
	public function testNothingIsStoredWithoutASuccessfulLogin(string $identifier, string $password): void
	{
		$this->authService->login($identifier, $password);

		static::assertSame([], $this->storedHashes);
	}

	/**
	 * A database which can not store the new hash must not take the login away.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAHashWhichCanNotBeStoredDoesNotStopTheLogin(): void
	{
		$users = $this->createStub(UsersService::class);
		$users->method('findUser')->willReturn($this->users['user@example.com']);
		$users->method('getUserById')->willReturn($this->entity(1));
		$users->method('updatePasswordHash')->willThrowException(new DbalException('database is read only'));
		$service = new AuthService($users, $this->autoLoginService, new Logger('test', [$this->logs]), new LoginThrottle(new ArrayCache()));

		static::assertNotNull($service->login('user@example.com', self::PASSWORD));
		static::assertTrue($this->logs->hasWarningThatContains('could not be renewed'));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testThePasswordIsNeverLogged(): void
	{
		$this->authService->login('user@example.com', 'Sup3r-Secret-Typo');
		$this->authService->login('user@example.com', self::PASSWORD);

		foreach ($this->logs->getRecords() as $record)
		{
			static::assertStringNotContainsString('Sup3r-Secret-Typo', $record->message . json_encode($record->context));
			static::assertStringNotContainsString(self::PASSWORD, $record->message . json_encode($record->context));
		}
	}

	/**
	 * Both paths do one expensive hash operation (about 50 ms and more), so they stay in the same order of
	 * magnitude. Otherwise the time would tell whether a name exists.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAnUnknownUserTakesAsLongAsAWrongPassword(): void
	{
		$this->users['user@example.com']['password'] = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

		$start = hrtime(true);
		$this->authService->login('unknown', 'wrong');
		$unknown = hrtime(true) - $start;

		$start = hrtime(true);
		$this->authService->login('user@example.com', 'wrong');
		$wrong = hrtime(true) - $start;

		static::assertGreaterThan(10_000_000, $unknown);
		static::assertLessThan(3, $unknown / $wrong);
		static::assertGreaterThan(1 / 3, $unknown / $wrong);
	}
}
