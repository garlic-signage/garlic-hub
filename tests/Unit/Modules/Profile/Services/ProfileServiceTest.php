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

namespace Tests\Unit\Modules\Profile\Services;

use App\Framework\Core\Crypt;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProfileServiceTest extends TestCase
{
	private UserMainRepository&MockObject $userMainRepositoryMock;
	private UserTokenService&MockObject $userTokenServiceMock;
	private Crypt&MockObject $cryptMock;
	private Transactions&MockObject $transactionsMock;
	private ProfileService $profileService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userMainRepositoryMock = $this->createMock(UserMainRepository::class);
		$this->userTokenServiceMock   = $this->createMock(UserTokenService::class);
		$this->cryptMock              = $this->createMock(Crypt::class);
		$this->transactionsMock       = $this->createMock(Transactions::class);
		$loggerStub = $this->createStub(LoggerInterface::class);

		$this->profileService = new ProfileService(
			$this->userMainRepositoryMock,
			$this->userTokenServiceMock,
			$this->cryptMock,
			$this->transactionsMock,
			$loggerStub
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordSuccess(): void
	{
		$UID = 34;
		$passwordToken = 'valid-token';
		$password = 'new-password';
		$hashedPassword = 'hashed-password';
		$this->cryptMock->expects($this->once())->method('createPasswordHash')
			->with($password)
			->willReturn($hashedPassword);
		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->exactly(2))
			->method('update')
			->willReturnMap([
				[$UID, ['password' => $hashedPassword], 1],
				[$UID, ['status' => UserStatus::REGISTERED->value], 1],
			]);
		$this->userTokenServiceMock->expects($this->once())->method('useToken')
			->with($passwordToken)
			->willReturn(1);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with($UID)
			->willReturn(['status' => UserStatus::NOT_VERIFIED->value]);
		$this->transactionsMock->expects($this->once())->method('commit');

		$result = $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);

		static::assertSame(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordFailsWhenPasswordUpdateFails(): void
	{
		$UID = 1;
		$passwordToken = 'valid-token';
		$password = 'new-password';

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->cryptMock->expects($this->once())->method('createPasswordHash')->with($password)->willReturn('hashed');
		$this->userMainRepositoryMock->expects($this->once())
			->method('update')
			->with($UID, ['password' => 'hashed'])
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->userTokenServiceMock->expects($this->never())->method('useToken');

		$result = $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordFailsWhenTokenUpdateFails(): void
	{
		$UID = 1;
		$passwordToken = 'valid-token';
		$password = 'new-password';
		$hashedPassword = 'hashed-password';
		$this->cryptMock->expects($this->once())->method('createPasswordHash')
			->with($password)
			->willReturn($hashedPassword);

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())
			->method('update')
			->with($UID, ['password' => $hashedPassword])
			->willReturn(1);
		$this->userTokenServiceMock->expects($this->once())->method('useToken')
			->with($passwordToken)
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$result = $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordFailsWhenUserNotFound(): void
	{
		$UID = 1;
		$passwordToken = 'valid-token';
		$password = 'new-password';
		$hashedPassword = 'hashed-password';
		$this->cryptMock->expects($this->once())->method('createPasswordHash')
			->with($password)
			->willReturn($hashedPassword);

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())
			->method('update')
			->with($UID, ['password' => $hashedPassword])
			->willReturn(1);
		$this->userTokenServiceMock->expects($this->once())->method('useToken')->with($passwordToken)->willReturn(1);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')->with($UID)->willReturn([]);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$result = $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordFailsWhenStatusUpdateFails(): void
	{
		$UID = 1;
		$passwordToken = 'valid-token';
		$password = 'new-password';
		$hashedPassword = 'hashed-password';
		$this->cryptMock->expects($this->once())->method('createPasswordHash')
			->with($password)
			->willReturn($hashedPassword);

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->exactly(2))
			->method('update')
			->willReturnMap([
				[$UID, ['password' => $hashedPassword], 1],
				[$UID, ['status' => UserStatus::REGISTERED->value], 0],
			]);
		$this->userTokenServiceMock->expects($this->once())->method('useToken')
			->with($passwordToken)
			->willReturn(1);
		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with($UID)
			->willReturn(['status' => UserStatus::NOT_VERIFIED->value]);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$result = $this->profileService->storeNewForcedPassword($UID, $passwordToken, $password);

		static::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateLocaleSuccess(): void
	{
		$this->expectUntouched($this->userTokenServiceMock, $this->cryptMock, $this->transactionsMock);
		$UID = 42;
		$locale = 'en_US';

		$this->userMainRepositoryMock->expects($this->once())
			->method('update')
			->with($UID, ['locale' => $locale])
			->willReturn(1);

		$result = $this->profileService->updateLocale($UID, $locale);

		static::assertSame(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateLocaleFails(): void
	{
		$this->expectUntouched($this->userTokenServiceMock, $this->cryptMock, $this->transactionsMock);
		$UID = 42;
		$locale = 'en_US';

		$this->userMainRepositoryMock->expects($this->once())
			->method('update')
			->with($UID, ['locale' => $locale])
			->willReturn(0);

		$result = $this->profileService->updateLocale($UID, $locale);

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
