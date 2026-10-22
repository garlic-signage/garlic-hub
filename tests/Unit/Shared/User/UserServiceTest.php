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

namespace Tests\Unit\Shared\User;

use App\Framework\Exceptions\CoreException;
use App\Shared\User\UserEntity;
use App\Shared\User\UserEntityFactory;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Shared\User\Repositories\Edge\UserTokensRepository;
use App\Shared\User\Repositories\UserRepositoryFactory;
use App\Shared\User\UsersService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserServiceTest extends TestCase
{
	private UserEntityFactory&MockObject $entityFactoryMock;
	private Psr16Adapter&MockObject $cacheMock;
	private UserMainRepository&MockObject $userMainRepositoryMock;
	private UserRepositoryFactory&Stub $repositoryFactoryStub;
	private LoggerInterface&MockObject $loggerMock;
	private UsersService $usersService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->repositoryFactoryStub  = $this->createStub(UserRepositoryFactory::class);
		$this->entityFactoryMock      = $this->createMock(UserEntityFactory::class);
		$this->cacheMock              = $this->createMock(Psr16Adapter::class);
		$this->userMainRepositoryMock = $this->createMock(UserMainRepository::class);
		$this->loggerMock             = $this->createMock(LoggerInterface::class);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserStatsSuccess(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->cacheMock, $this->loggerMock);
		$UID = 123;
		$expectedData = [
			'login_time' => date('Y-m-d H:i:s'),
			'num_logins' => 'num_logins + 1'
		];

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->userMainRepositoryMock
			->expects($this->once())
			->method('update')
			->with($UID, static::equalTo($expectedData))
			->willReturn(1);

		$result = $this->usersService->updateUserStats($UID);

		static::assertEquals(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserStatsFailure(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->cacheMock, $this->loggerMock);
		$UID = 123;

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->userMainRepositoryMock
			->expects($this->once())
			->method('update')
			->with($UID, static::arrayHasKey('login_time'))
			->willReturn(0);

		$result = $this->usersService->updateUserStats($UID);

		static::assertEquals(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindUserSuccess(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->cacheMock, $this->loggerMock);
		$identifier = 'test@example.com';
		$mockUserData = ['UID' => 1, 'username' => 'testuser'];

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->userMainRepositoryMock
			->method('findByIdentifier')
			->with($identifier)
			->willReturn($mockUserData);

		$result = $this->usersService->findUser($identifier);

		static::assertEquals($mockUserData, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindUserNotFound(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->cacheMock, $this->loggerMock);
		$identifier = 'unknown@example.com';

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		// Repository simuliert, dass kein Benutzer gefunden wurde
		$this->userMainRepositoryMock->method('findByIdentifier')
			->with($identifier)
			->willReturn([]);

		static::assertEmpty($this->usersService->findUser($identifier));
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetCurrentUserFromCache(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$UID = 1;
		$cachedData = ['UID' => 1, 'username' => 'testuser'];

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->cacheMock->method('get')->with("user_$UID")
			->willReturn($cachedData);

		$this->userMainRepositoryMock->expects($this->never())->method('findByIdSecured');

		$mockUserEntity = $this->createStub(UserEntity::class);
		$this->entityFactoryMock->method('create')
			->with($cachedData)
			->willReturn($mockUserEntity);

		$result = $this->usersService->getUserById($UID);
		static::assertEquals($mockUserEntity, $result);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetCurrentUserFromDatabase(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$UID = 1;
		$userData = ['UID' => 1, 'username' => 'testuser'];

		$aclRepositoryMock      = $this->createMock(UserAclRepository::class);
		$elseRepositoryMock     = $this->createMock(UserTokensRepository::class);
		$this->repositoryFactoryStub->method('create')
			->willReturn(
				[
					'main' => $this->userMainRepositoryMock,
					'acl'  => $aclRepositoryMock,
					'else' => $elseRepositoryMock
				]
			);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);


		$this->cacheMock->method('get')->with("user_$UID")
			->willReturn(null);


		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with($UID)
			->willReturn($userData);
		$aclRepositoryMock->expects($this->once())->method('findById')->with($UID)
			->willReturn([]);
		$elseRepositoryMock->expects($this->once())->method('findFirstById')->with($UID)
			->willReturn([]);

		$mockUserEntity = $this->createStub(UserEntity::class);
		$this->entityFactoryMock->method('create')
			->with(['main' => $userData, 'acl' => [], 'else' => []])
			->willReturn($mockUserEntity);

		$result = $this->usersService->getUserById($UID);

		static::assertEquals($mockUserEntity, $result);
	}

    /**
     * @throws CoreException
     */
    #[Group('units')]
	public function testInvalidCache(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->userMainRepositoryMock, $this->loggerMock);
		$UID = 14;

		$this->repositoryFactoryStub->method('create')
			->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);


		$this->cacheMock->expects($this->once())->method('delete')
			->with('user_'.$UID)
		;
		$this->usersService->invalidateCache($UID);

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetUserByIdFallsBackToDatabaseWhenCacheFails(): void
	{
		$userData = ['UID' => 1, 'username' => 'testuser'];
		$this->repositoryFactoryStub->method('create')->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->cacheMock->expects($this->once())->method('get')->with('user_1')
			->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$this->cacheMock->expects($this->once())->method('set')
			->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$this->loggerMock->expects($this->exactly(2))->method('warning');
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with(1)->willReturn($userData);
		$userEntity = $this->createStub(UserEntity::class);
		$this->entityFactoryMock->expects($this->once())->method('create')->with(['main' => $userData])->willReturn($userEntity);

		static::assertSame($userEntity, $this->usersService->getUserById(1));
	}

	#[Group('units')]
	public function testInvalidateCacheFailureIsReported(): void
	{
		$this->expectUntouched($this->entityFactoryMock, $this->userMainRepositoryMock, $this->loggerMock);
		$this->repositoryFactoryStub->method('create')->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$this->cacheMock->expects($this->once())->method('delete')->with('user_14')
			->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('User cache for UID 14 could not be invalidated.');

		$this->usersService->invalidateCache(14);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetUserByIdLoadsUserOncePerRequest(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->repositoryFactoryStub->method('create')->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$cachedData = ['main' => ['UID' => 3]];
		$userEntity = $this->createStub(UserEntity::class);
		$this->cacheMock->expects($this->once())->method('get')->with('user_3')->willReturn($cachedData);
		$this->entityFactoryMock->expects($this->once())->method('create')->with($cachedData)->willReturn($userEntity);
		$this->userMainRepositoryMock->expects($this->never())->method('findByIdSecured');

		static::assertSame($userEntity, $this->usersService->getUserById(3));
		static::assertSame($userEntity, $this->usersService->getUserById(3));
		static::assertSame($userEntity, $this->usersService->getUserById(3));
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInvalidateCacheAlsoForgetsLoadedUser(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->repositoryFactoryStub->method('create')->willReturn(['main' => $this->userMainRepositoryMock]);
		$this->usersService = new UsersService($this->repositoryFactoryStub, $this->entityFactoryMock, $this->cacheMock, $this->loggerMock);

		$before = $this->createStub(UserEntity::class);
		$after  = $this->createStub(UserEntity::class);
		$this->cacheMock->expects($this->exactly(2))->method('get')->with('user_3')
			->willReturnOnConsecutiveCalls(['main' => ['UID' => 3, 'status' => 1]], ['main' => ['UID' => 3, 'status' => 2]]);
		$this->cacheMock->expects($this->once())->method('delete')->with('user_3');
		$this->entityFactoryMock->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls($before, $after);
		$this->userMainRepositoryMock->expects($this->never())->method('findByIdSecured');

		static::assertSame($before, $this->usersService->getUserById(3));
		$this->usersService->invalidateCache(3);
		static::assertSame($after, $this->usersService->getUserById(3));
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
