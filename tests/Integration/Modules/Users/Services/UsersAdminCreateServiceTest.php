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

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\Services\UsersAdminCreateService;
use App\Modules\Users\UserStatus;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\RecordingLogger;

/**
 * The very first admin on a fresh installation. The lock file is a constant of the application, so every test runs in its
 * own process and defines it in the temp dir.
 */
#[Group('acl')]
#[Group('io')]
#[RunTestsInSeparateProcesses]
class UsersAdminCreateServiceTest extends ContainerTestCase
{
	private string $lockFile;
	private RecordingLogger $logger;
	private UsersAdminCreateService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->lockFile = sys_get_temp_dir() . '/garlic-hub-tests/install-' . getmypid() . '/install.lock';
		@mkdir(dirname($this->lockFile), 0777, true);
		@unlink($this->lockFile);
		define('INSTALL_LOCK_FILE', $this->lockFile);

		$this->logger  = new RecordingLogger();
		$this->service = $this->createService($this->container->get(NodesService::class));
	}

	protected function tearDown(): void
	{
		@unlink($this->lockFile);
		@rmdir(dirname($this->lockFile));
		parent::tearDown();
	}

	private function createService(NodesService $nodesService): UsersAdminCreateService
	{
		return new UsersAdminCreateService(
			new UserMainRepository($this->connection),
			new UserAclRepository($this->connection),
			$nodesService,
			$this->container->get(Transactions::class),
			$this->logger
		);
	}

	/**
	 * @return array{username:string, email:string, locale: string, password:string}
	 */
	private static function admin(): array
	{
		return ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'de_DE', 'password' => 'Secret123!'];
	}

	public function testThereIsNoAdminOnAFreshInstallation(): void
	{
		static::assertFalse($this->service->hasAdminUser());
	}

	public function testTheFirstUserIsTheAdmin(): void
	{
		$this->loadFixture('acl/users.sql');

		static::assertTrue($this->service->hasAdminUser());
	}

	public function testTheAdminIsStoredWithAHashedPasswordFolderAndRights(): void
	{
		static::assertSame(1, $this->service->insertNewAdminUser(self::admin(), $this->container->get(Config::class)));

		$user = $this->connection->fetchAssociative('SELECT * FROM user_main WHERE UID = 1');
		static::assertIsArray($user);
		static::assertSame(['root', 'root@example.com', 'de_DE', UserStatus::ADMIN->value], [$user['username'], $user['email'], $user['locale'], (int) $user['status']]);
		static::assertNotSame('Secret123!', $user['password']);
		static::assertTrue(password_verify('Secret123!', (string) $user['password']));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE UID = 1 AND is_user_folder = 1'));
		static::assertGreaterThan(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 1'));
		static::assertFileExists($this->lockFile);
	}

	public function testNothingIsStoredWhenTheLockFileExists(): void
	{
		static::assertTrue($this->service->creatLockfile());

		$this->expectException(TranslatableModuleException::class);
		try
		{
			$this->service->insertNewAdminUser(self::admin(), $this->container->get(Config::class));
		}
		finally
		{
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
		}
	}

	public function testNothingIsStoredWhenAnAdminExists(): void
	{
		$this->loadFixture('acl/users.sql');

		$this->expectException(TranslatableModuleException::class);
		try
		{
			$this->service->insertNewAdminUser(self::admin(), $this->container->get(Config::class));
		}
		finally
		{
			static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
			static::assertFileDoesNotExist($this->lockFile);
		}
	}

	public function testTheAdminIsRolledBackWhenTheFolderCanNotBeCreated(): void
	{
		$nodes = $this->createStub(NodesService::class);
		$nodes->method('addUserDirectory')->willThrowException(new ModuleException('mediapool', 'no folder'));

		try
		{
			$this->createService($nodes)->insertNewAdminUser(self::admin(), $this->container->get(Config::class));
			static::fail('The failure of the folder must reach the caller.');
		}
		catch (ModuleException)
		{
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl'));
			static::assertFileDoesNotExist($this->lockFile);
		}
	}

	public function testNothingIsStoredWhenTheAdminDoesNotGetTheUidOne(): void
	{
		$users = $this->createStub(UserMainRepository::class);
		$users->method('findByIdSecured')->willReturn([]);
		$users->method('insert')->willReturn('2');
		$service = new UsersAdminCreateService($users, new UserAclRepository($this->connection), $this->container->get(NodesService::class), $this->container->get(Transactions::class), $this->logger);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Insert admin user failed.');
		$service->insertNewAdminUser(self::admin(), $this->container->get(Config::class));
	}

	public function testTheAdminIsRolledBackWhenTheLockFileCanNotBeWritten(): void
	{
		// without its directory the file can not be written
		rmdir(dirname($this->lockFile));
		// the application does not hide the warning of file_put_contents
		set_error_handler(static fn(): bool => true);
		try
		{
			$this->service->insertNewAdminUser(self::admin(), $this->container->get(Config::class));
			static::fail('Without a lock file the installation is not finished.');
		}
		catch (ModuleException $e)
		{
			static::assertSame('Lockfile could not created.', $e->getMessage());
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl'));
		}
		finally
		{
			restore_error_handler();
		}
	}

	public function testTheLockFileIsCreated(): void
	{
		static::assertTrue($this->service->creatLockfile());
		static::assertFileExists($this->lockFile);
	}

	public function testTheAlarmIsLogged(): void
	{
		$this->service->logAlarm();

		static::assertSame(['Logfile was removed. Create a new one.'], $this->logger->messages['error']);
	}
}
