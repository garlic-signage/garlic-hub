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


namespace Tests\Unit\Modules\Users\Services;

use App\Framework\Core\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Users\Repositories\Edge\UserAclRepository;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use App\Modules\Users\Services\UsersAdminCreateService;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsersAdminCreateServiceTest extends TestCase
{
	use PHPMock;

	private UserMainRepository&MockObject $userMainRepositoryMock;
	private UserAclRepository&MockObject $userAclRepositoryMock;
	private NodesService&MockObject $nodesServiceMock;
	private Transactions&MockObject $transactionsMock;
	private Config&Stub $configStub;
	private LoggerInterface&MockObject $loggerMock;
	private UsersAdminCreateService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userMainRepositoryMock = $this->createMock(UserMainRepository::class);
		$this->userAclRepositoryMock = $this->createMock(UserAclRepository::class);
		$this->nodesServiceMock = $this->createMock(NodesService::class);
		$this->transactionsMock = $this->createMock(Transactions::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->configStub = $this->createStub(Config::class);

		$this->service = new UsersAdminCreateService(
			$this->userMainRepositoryMock, $this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock, $this->loggerMock
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testHasAdminUserReturnsTrueWhenAdminExists(): void
	{
		$this->expectUntouched($this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock, $this->loggerMock);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with(1)
			->willReturn(['id' => 1, 'username' => 'admin']);

		$result = $this->service->hasAdminUser();

		static::assertTrue($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testHasAdminUserReturnsFalseWhenAdminDoesNotExist(): void
	{
		$this->expectUntouched($this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock, $this->loggerMock);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with(1)
			->willReturn([]);

		$result = $this->service->hasAdminUser();

		static::assertFalse($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testCreatLockfileReturnsTrueWhenFileIsCreated(): void
	{
		$this->expectUntouched($this->userMainRepositoryMock, $this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock, $this->loggerMock);
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_put_contents');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock', static::isString())
			->willReturn(10);

		$result = $this->service->creatLockfile();

		static::assertTrue($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testCreatLockfileReturnsFalseWhenFileCreationFails(): void
	{
		$this->expectUntouched($this->userMainRepositoryMock, $this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock, $this->loggerMock);
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_put_contents');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock', static::isString())
			->willReturn(false);

		$result = $this->service->creatLockfile();

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testLogAlarmErrorLogMessage(): void
	{
		$this->expectUntouched($this->userMainRepositoryMock, $this->userAclRepositoryMock, $this->nodesServiceMock, $this->transactionsMock);
		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Logfile was removed. Create a new one.');

		$this->service->logAlarm();
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminSucceed(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_put_contents');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock', static::isString())
			->willReturn(6980);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with(1)
			->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')
			->willReturn(1);
		$this->nodesServiceMock->expects($this->once())->method('addUserDirectory')
			->with(1, 'admin')
			->willReturn(123);
		$this->userAclRepositoryMock->expects($this->once())->method('addAdminRights')
			->with($this->configStub, 1);

		$this->transactionsMock->expects($this->once())->method('commit');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminWithLockfile(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');

		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_exists');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock')
			->willReturn(true);

		$this->userMainRepositoryMock->expects($this->never())->method('findByIdSecured');
		$this->userAclRepositoryMock->expects($this->never())->method('addAdminRights');

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('There is an existing lockfile already.');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminWhenExists(): void
	{
		$this->userAclRepositoryMock->expects($this->never())->method($this->anything());
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_exists');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock')
			->willReturn(false);

		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with(1)->willReturn(['id' => 1, 'username' => 'admin']);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('There is an admin user already.');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminUserInsertFails(): void
	{
		$this->userAclRepositoryMock->expects($this->never())->method($this->anything());
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_exists');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock')
			->willReturn(false);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with(1)->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')->willReturn(0);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('Insert admin user failed.');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminNodeFails(): void
	{
		$this->userAclRepositoryMock->expects($this->never())->method($this->anything());
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_exists');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock')
			->willReturn(false);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with(1)->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')->willReturn(1);
		$this->nodesServiceMock->expects($this->once())->method('addUserDirectory')->with(1, 'admin')->willReturn(0);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('Create mediapool admin user directory failed.');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testInsertNewAdminLockfileFails(): void
	{
		$postData = ['username' => 'admin', 'email' => 'admin@example.com', 'locale' => 'en', 'password' => 'secure-password'];

		$this->transactionsMock->expects($this->once())->method('begin');
		define('INSTALL_LOCK_FILE', '/mock/path/install.lock');
		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_exists');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock')
			->willReturn(false);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with(1)->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')->willReturn(1);
		$this->nodesServiceMock->expects($this->once())->method('addUserDirectory')->with(1, 'admin')->willReturn(123);
		$this->userAclRepositoryMock->expects($this->once())->method('addAdminRights')->with($this->configStub);

		$filePutContentsMock = $this->getFunctionMock('App\Modules\Users\Services', 'file_put_contents');
		$filePutContentsMock->expects($this->once())
			->with('/mock/path/install.lock', static::isString())
			->willReturn(false);
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('Lockfile could not created.');

		$result = $this->service->insertNewAdminUser($postData, $this->configStub);

		static::assertSame(0, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
