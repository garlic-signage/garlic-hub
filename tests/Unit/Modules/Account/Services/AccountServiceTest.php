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

namespace Tests\Unit\Modules\Account\Services;

use App\Framework\Security\Crypt;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Account\Services\AccountService;
use App\Shared\User\UserTokenService;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AccountServiceTest extends TestCase
{
	private UserMainRepository&MockObject $userMainRepositoryMock;
	private UserTokenService&MockObject $userTokenServiceMock;
	private Crypt&MockObject $cryptMock;
	private Transactions&MockObject $transactionsMock;
	private AccountService $accountService;

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

		$this->accountService = new AccountService(
			$this->userMainRepositoryMock,
			$this->userTokenServiceMock,
			$this->cryptMock,
			$this->transactionsMock
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
		$this->transactionsMock->expects($this->never())->method('rollBack');

		$this->accountService->storeNewForcedPassword($UID, $passwordToken, $password);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordThrowsWhenPasswordUpdateThrows(): void
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

		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('password_update_failed');
		$this->accountService->storeNewForcedPassword($UID, $passwordToken, $password);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordThrowsWhenTokenUpdateThrows(): void
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

		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('token_update_failed');
		$this->accountService->storeNewForcedPassword($UID, $passwordToken, $password);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordThrowsWhenUserNotFound(): void
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

		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('user_not_found');
		$this->accountService->storeNewForcedPassword($UID, $passwordToken, $password);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNewForcedPasswordThrowsWhenStatusUpdateThrows(): void
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

		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('status_update_failed');
		$this->accountService->storeNewForcedPassword($UID, $passwordToken, $password);
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

		$result = $this->accountService->updateLocale($UID, $locale);

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

		$result = $this->accountService->updateLocale($UID, $locale);

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
