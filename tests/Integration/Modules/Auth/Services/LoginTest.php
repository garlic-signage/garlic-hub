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

use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Shared\User\UsersService;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Login with username/email and password. All users of the fixture have the password Test1234!
 */
#[Group('auth')]
class LoginTest extends ContainerTestCase
{
	private const string PASSWORD = 'Test1234!';

	private AuthService $authService;

	protected function setUp(): void
	{
		parent::setUp();
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$this->loadFixture('auth/users.sql');
		$this->authService = $this->newAuthService();
	}

	/**
	 * The AuthService keeps the last error message. The application creates one per request,
	 * so a test which logs in several times needs a fresh one for every try.
	 */
	private function newAuthService(): AuthService
	{
		return new AuthService(
			$this->container->get(UsersService::class),
			$this->container->get(AutoLoginService::class),
			$this->container->get('ModuleLogger'),
			new LoginThrottle($this->container->get(Psr16Adapter::class))
		);
	}

	private function login(string $identifier, string $password): ?\App\Shared\User\UserEntity
	{
		$this->authService = $this->newAuthService();

		return $this->authService->login($identifier, $password);
	}

	public function testLoginWithUsername(): void
	{
		$user = $this->login('regular', self::PASSWORD);

		static::assertNotNull($user);
		static::assertSame(10, $user->getMain()['UID']);
		static::assertSame('', $this->authService->getErrorMessage());
	}

	public function testLoginWithEmailIgnoresCase(): void
	{
		static::assertSame(10, $this->login('REGULAR@example.com', self::PASSWORD)?->getMain()['UID']);
	}

	public function testLoginUsesTheRightsOfTheUser(): void
	{
		$acl = $this->login('regular', self::PASSWORD)?->getAcl();

		static::assertEqualsCanonicalizing(['player' => 2, 'playlists' => 1], array_column($acl ?? [], 'acl', 'module'));
	}

	public function testWrongPasswordIsRejected(): void
	{
		static::assertNull($this->login('regular', 'wrong'));
		static::assertSame('Invalid credentials.', $this->authService->getErrorMessage());
	}

	/**
	 * Unknown user and wrong password must look the same, otherwise usernames can be collected.
	 */
	public function testUnknownUserLooksLikeAWrongPassword(): void
	{
		static::assertNull($this->login('nobody', self::PASSWORD));
		static::assertSame('Invalid credentials.', $this->authService->getErrorMessage());
	}

	public function testEmptyCredentialsAreRejected(): void
	{
		static::assertNull($this->login('', ''));
		static::assertNull($this->login('regular', ''));
	}

	public function testHashOfAnotherUsersPasswordDoesNotWork(): void
	{
		// the stored hash is not the password
		$hash = (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = 10');

		static::assertNull($this->login('regular', $hash));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function notAllowedUsers(): array
	{
		return ['deleted' => ['deleted'], 'blocked' => ['blocked'], 'not verified' => ['unverified']];
	}

	#[DataProvider('notAllowedUsers')]
	public function testDeletedBlockedAndNotVerifiedUsersCanNotLogin(string $username): void
	{
		static::assertNull($this->login($username, self::PASSWORD));
		static::assertStringStartsWith('login//account_', $this->authService->getErrorMessage());
	}

	public function testLockedAfterTooManyFailuresEvenWithTheRightPassword(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->login('regular', 'wrong');

		static::assertNull($this->login('regular', self::PASSWORD));
		static::assertStringContainsString('Too many failed login attempts', $this->authService->getErrorMessage());
	}

	public function testTheLockDoesNotAffectOtherUsersOrOtherAddresses(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->login('regular', 'wrong');

		static::assertNotNull($this->login('other', self::PASSWORD));

		$_SERVER['REMOTE_ADDR'] = '10.0.0.2';
		static::assertNotNull($this->login('regular', self::PASSWORD));
	}

	public function testSuccessfulLoginResetsTheFailures(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP - 1; $i++)
			$this->login('regular', 'wrong');
		static::assertNotNull($this->login('regular', self::PASSWORD));

		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP - 1; $i++)
			$this->login('regular', 'wrong');

		static::assertNotNull($this->login('regular', self::PASSWORD));
	}

	public function testSqlInTheUsernameIsNoLogin(): void
	{
		static::assertNull($this->login("regular' OR '1'='1", self::PASSWORD));
		static::assertNull($this->login("' OR 1=1 --", 'x'));
	}
}
