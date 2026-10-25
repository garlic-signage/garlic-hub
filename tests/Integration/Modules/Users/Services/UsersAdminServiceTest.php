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

namespace Tests\Integration\Modules\Users\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UsersAdminService;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the admin service stores, with the real database. Fixtures: 1 admin, 2 anna, 3 bernd.
 * Who may do it is tested in UserAdminAccessTest and UserAccessTest.
 */
#[Group('acl')]
class UsersAdminServiceTest extends ContainerTestCase
{
	private UsersAdminService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->actingAs(1);
		$this->service = $this->container->get(UsersAdminService::class);
	}

	public function testAnUnknownUserCanNotBeLoadedForEditing(): void
	{
		static::assertSame([], $this->service->loadForAdminEdit(99));
	}

	public function testAUserIsLoadedWithHisTokensButWithoutPassword(): void
	{
		$token = $this->container->get(UserTokenService::class)->generateToken();
		$this->container->get(UserTokenService::class)->insertToken(3, $token, TokenPurposes::INITIAL_PASSWORD);

		$user = $this->service->loadForAdminEdit(3);

		static::assertSame(['bernd', 'bernd@example.com'], [$user['username'] ?? null, $user['email'] ?? null]);
		static::assertArrayNotHasKey('password', $user);
		static::assertSame([TokenPurposes::INITIAL_PASSWORD->value], array_column($this->service->loadUserTokensForAdminEdit(3), 'purpose'));
		static::assertCount(1, $user['tokens'] ?? []);
	}

	public function testANewUserGetsAFolderAndAnInitialPasswordToken(): void
	{
		$UID = $this->service->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com', 'locale' => 'de_DE', 'status' => 2]);

		static::assertGreaterThan(3, $UID);
		$user = $this->connection->fetchAssociative('SELECT username, email, locale, status FROM user_main WHERE UID = ?', [$UID]);
		static::assertSame(['carla', 'carla@example.com', 'de_DE', 2], [$user['username'] ?? null, $user['email'] ?? null, $user['locale'] ?? null, (int) ($user['status'] ?? 0)]);
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE UID = ? AND is_user_folder = 1', [$UID]));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ? AND purpose = ?', [$UID, TokenPurposes::INITIAL_PASSWORD->value]));
		static::assertNotSame('', $this->service->getToken());
	}

	/**
	 * @return array<string,array{array{username:string, email:string},string}> the data of the new user and the error
	 */
	public static function takenNames(): array
	{
		return [
			'username' => [['username' => 'anna', 'email' => 'new@example.com'], 'username_exists'],
			'email'    => [['username' => 'new', 'email' => 'anna@example.com'], 'email_exists'],
		];
	}

	/**
	 * @param array{username:string, email:string} $data
	 */
	#[DataProvider('takenNames')]
	public function testAUserWithATakenNameIsNotCreated(array $data, string $error): void
	{
		try
		{
			$this->service->insertNewUser($data);
			static::fail('The name is taken.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringContainsString($error, $e->getMessage());
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens'));
		}
	}

	public function testANewUserIsRolledBackWhenTheFolderCanNotBeCreated(): void
	{
		$nodes = $this->createStub(NodesService::class);
		$nodes->method('addUserDirectory')->willThrowException(new ModuleException('mediapool', 'no folder'));

		try
		{
			$this->serviceWith($nodes)->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
			static::fail('The failure of the folder must reach the caller.');
		}
		catch (ModuleException)
		{
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens'));
		}
	}

	public function testAUserWhichCanNotBeInsertedIsNotCreated(): void
	{
		$users = $this->createStub(UserMainRepository::class);
		$users->method('findExistingUser')->willReturn([]);
		$users->method('insert')->willReturn('0');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Insert failed.');
		$this->serviceWith(users: $users)->insertNewUser(['username' => 'carla', 'email' => 'carla@example.com']);
	}

	public function testThePasswordOfAnUnknownUserCanNotBeReset(): void
	{
		try
		{
			$this->service->createPasswordResetToken(99);
			static::fail('There is no user to reset.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringContainsString('password_reset_failed', $e->getMessage());
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens'));
		}
	}

	public function testThePasswordIsNotClearedWhenTheTokenCanNotBeStored(): void
	{
		$tokens = $this->createStub(UserTokenService::class);
		$tokens->method('findTokensByUID')->willReturn([]);
		$tokens->method('generateToken')->willReturn('abc');
		$tokens->method('insertToken')->willReturn('');

		try
		{
			$this->serviceWith(tokens: $tokens)->createPasswordResetToken(3);
			static::fail('Without a token the reset is no reset.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringContainsString('password_reset_failed', $e->getMessage());
			static::assertNotSame('', $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = 3'));
		}
	}

	public function testAUserMayKeepHisOwnNameAndEmail(): void
	{
		$this->service->updateUser(3, ['username' => 'bernd', 'email' => 'bernd@example.com', 'locale' => 'de_DE']);

		static::assertSame('de_DE', $this->connection->fetchOne('SELECT locale FROM user_main WHERE UID = 3'));
	}

	/**
	 * @return array<string,array{array{username:string, email:string},string}>
	 */
	public static function namesOfOthers(): array
	{
		return [
			'username' => [['username' => 'anna', 'email' => 'bernd@example.com'], 'username_exists'],
			'email'    => [['username' => 'bernd', 'email' => 'anna@example.com'], 'email_exists'],
		];
	}

	/**
	 * @param array{username:string, email:string} $data
	 */
	#[DataProvider('namesOfOthers')]
	public function testAUserCanNotTakeTheNameOfAnother(array $data, string $error): void
	{
		try
		{
			$this->service->updateUser(3, $data);
			static::fail('The name is taken.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertStringContainsString($error, $e->getMessage());
			static::assertSame(['bernd', 'bernd@example.com'], [$this->connection->fetchOne('SELECT username FROM user_main WHERE UID = 3'), $this->connection->fetchOne('SELECT email FROM user_main WHERE UID = 3')]);
		}
	}

	public function testUnknownFieldsAreNotStored(): void
	{
		// the password is not a field of the admin form
		$this->service->updateUser(3, ['username' => 'bernd', 'email' => 'bernd@example.com', 'password' => 'x', 'UID' => 1]);

		static::assertNotSame('x', $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = 3'));
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_main WHERE username = 'admin'"));
	}

	public function testAChangeOfTheStatusCountsAtOnce(): void
	{
		$users = $this->container->get(UsersService::class);
		static::assertSame(3, (int) $users->getUserById(3)->getMain()['status']);

		$this->service->updateUser(3, ['username' => 'bernd', 'email' => 'bernd@example.com', 'status' => 1]);

		static::assertSame(1, (int) $users->getUserById(3)->getMain()['status']);
	}

	public function testADeletionIsRolledBackWhenTheFolderCanNotBeDeleted(): void
	{
		$nodes = $this->createStub(NodesService::class);
		$nodes->method('deleteUserDirectory')->willThrowException(new ModuleException('mediapool', 'no folder'));

		try
		{
			$this->serviceWith($nodes)->deleteUser(3);
			static::fail('The failure of the folder must reach the caller.');
		}
		catch (ModuleException)
		{
			static::assertSame('bernd@example.com', $this->connection->fetchOne('SELECT email FROM user_main WHERE UID = 3'));
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 3'));
		}
	}

	private function serviceWith(?NodesService $nodes = null, ?UserMainRepository $users = null, ?UserTokenService $tokens = null): UsersAdminService
	{
		return new UsersAdminService(
			$users ?? new UserMainRepository($this->connection),
			new UserAclRepository($this->connection),
			$nodes ?? $this->container->get(NodesService::class),
			$tokens ?? $this->container->get(UserTokenService::class),
			$this->container->get(AclValidator::class),
			$this->container->get(Transactions::class),
			$this->container->get(UserSession::class),
			$this->container->get(UsersService::class)
		);
	}
}
