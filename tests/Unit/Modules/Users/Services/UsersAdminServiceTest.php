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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Repositories\Edge\UserMainRepository;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsersAdminServiceTest extends TestCase
{
	private UserMainRepository&MockObject $userMainRepositoryMock;
	private NodesService&MockObject $nodesServiceMock;
	private UserTokenService&MockObject $userTokenServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private LoggerInterface&MockObject $loggerMock;
	private Transactions&MockObject $transactionsMock;
	private UsersAdminService $usersAdminService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userMainRepositoryMock = $this->createMock(UserMainRepository::class);
		$this->nodesServiceMock = $this->createMock(NodesService::class);
		$this->userTokenServiceMock = $this->createMock(UserTokenService::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->transactionsMock = $this->createMock(Transactions::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->usersAdminService = new UsersAdminService(
			$this->userMainRepositoryMock,
			$this->nodesServiceMock,
			$this->userTokenServiceMock,
			$this->aclValidatorMock,
			$this->transactionsMock,
			$this->loggerMock
		);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadForAdminEditReturnsEmptyArrayWhenUserNotFound(): void
	{
		$UID = 123;

		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with($UID)
			->willReturn([]);

		$result = $this->usersAdminService->loadForAdminEdit($UID);

		self::assertSame([], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadForAdminEditReturnsUserWithTokens(): void
	{
		$UID = 123;
		$userData = [
			'UID' => $UID,
			'company_id' => 1,
			'status' => 2,
			'locale' => 'en',
			'email' => 'user@example.com',
			'username' => 'testuser',
		];
		$tokens = [
			['token' => 'token1', 'UID' => $UID, 'purpose' => 'test', 'expires_at' => '2025-12-31', 'used_at' => null],
			['token' => 'token2', 'UID' => $UID, 'purpose' => 'test2', 'expires_at' => '2025-12-31', 'used_at' => null],
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findByIdSecured')
			->with($UID)
			->willReturn($userData);

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn($tokens);

		$result = $this->usersAdminService->loadForAdminEdit($UID);

		$expected = $userData;
		$expected['tokens'] = $tokens;

		self::assertSame($expected, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertNewUserReturnsUserIdWhenSuccessful(): void
	{
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFICATED->value,
		];

		$UID = 123;
		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')
			->with($postData)
			->willReturn($UID);
		$this->userTokenServiceMock->expects($this->once())->method('generateToken')->willReturn('token');
		$this->userTokenServiceMock->expects($this->once())->method('insertToken')
			->with($UID, 'token', TokenPurposes::INITIAL_PASSWORD);
		$this->nodesServiceMock->expects($this->once())->method('addUserDirectory')
			->with($UID, $postData['username'])
			->willReturn(456);
		$this->transactionsMock->expects($this->once())->method('commit');
		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->insertNewUser($postData);
		self::assertSame($UID, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertFailsNotUnique(): void
	{
		$postData = [
			'username' => 'duplicateuser',
			'email' => 'duplicate@example.com',
			'status' => 1,
		];

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->willReturn([
				['UID' => 123, 'username' => 'duplicateuser', 'email' => 'duplicate@example.com'],
			]);
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('User or email already exists.');

		$result = $this->usersAdminService->insertNewUser($postData);
		self::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertFails(): void
	{
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFICATED->value,
		];

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')
			->with($postData)
			->willReturn(0);
		$this->userTokenServiceMock->expects($this->never())->method('insertToken');

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Insert failed.');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->insertNewUser($postData);
		self::assertSame(0, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertFailsNodeCreate(): void
	{
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFICATED->value,
		];

		$UID = 123;
		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')
			->with($postData)
			->willReturn($UID);
		$this->userTokenServiceMock->expects($this->once())->method('generateToken')->willReturn('token');
		$this->userTokenServiceMock->expects($this->once())->method('insertToken')
			->with($UID, 'token', TokenPurposes::INITIAL_PASSWORD);
		$this->nodesServiceMock->expects($this->once())->method('addUserDirectory')
			->with($UID, $postData['username'])
			->willReturn(0);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Create mediapool user directory failed.');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->insertNewUser($postData);
		self::assertSame(0, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserSuccess(): void
	{
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willReturn(1);
		$this->nodesServiceMock->expects($this->once())->method('deleteUserDirectory')
			->with($UID);
		$this->transactionsMock->expects($this->once())->method('commit');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->deleteUser($UID);
		self::assertTrue($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserFailsDueToAcl(): void
	{
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(false);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->loggerMock->expects($this->once())->method('error')
			->with('No rights to delete user');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->deleteUser($UID);
		self::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserFailsDueToDBError(): void
	{
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->loggerMock->expects($this->once())->method('error')
			->with('Remove the user from db-table failed.');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->deleteUser($UID);
		self::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserLogsAndRollbacksOnException(): void
	{
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willThrowException(new \Exception('Unexpected error'));
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Unexpected error');

		$this->usersAdminService->setUID(1);
		$result = $this->usersAdminService->deleteUser($UID);
		self::assertFalse($result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenReturnsEmptyStringWhenTokensExist(): void
	{
		$UID = 123;
		$existingTokens = [['token' => 'existing_token', 'purpose' => 'password_reset']];

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn($existingTokens);

		$result = $this->usersAdminService->createPasswordResetToken($UID);

		self::assertSame('', $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenFailsWhenUpdateFails(): void
	{
		$UID = 123;

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Password reset failed.');

		$result = $this->usersAdminService->createPasswordResetToken($UID);

		self::assertSame('', $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenFailsWhenTokenInsertionFails(): void
	{
		$UID = 123;

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willReturn(1);
		$this->userTokenServiceMock->expects($this->once())->method('generateToken')->willReturn('token');
		$this->userTokenServiceMock->expects($this->once())->method('insertToken')
			->willReturn('');

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Password reset failed.');

		$result = $this->usersAdminService->createPasswordResetToken($UID);

		self::assertSame('', $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenSuccess(): void
	{
		$UID = 123;
		$tokenId = 'new_token_id';

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willReturn(1);

		$this->userTokenServiceMock->expects($this->once())->method('generateToken')->willReturn($tokenId);
		$this->userTokenServiceMock->expects($this->once())->method('insertToken')
			->willReturn($tokenId);

		$this->transactionsMock->expects($this->once())->method('commit');

		$result = $this->usersAdminService->createPasswordResetToken($UID);

		self::assertSame($tokenId, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenLogsAndRollbacksOnException(): void
	{
		$UID = 123;

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willThrowException(new \Exception('Unexpected error'));

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Unexpected error');

		$result = $this->usersAdminService->createPasswordResetToken($UID);

		self::assertSame('', $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserReturnsZeroWhenNotUnique(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'duplicateuser',
			'email' => 'duplicate@example.com',
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn([
				['UID' => 124, 'username' => 'duplicateuser', 'email' => 'duplicate@example.com']
			]);

		$result = $this->usersAdminService->updateUser($UID, $postData);

		self::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSuccess(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser',
			'email' => 'test@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn([]);

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, $postData)
			->willReturn(1);

		$result = $this->usersAdminService->updateUser($UID, $postData);

		self::assertSame(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserFailsOnUpdateFailure(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser',
			'email' => 'test@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn([]);

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, $postData)
			->willReturn(0);

		$result = $this->usersAdminService->updateUser($UID, $postData);

		self::assertSame(0, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserFailsExistsUserName(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser',
			'email' => 'test@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];
		$existingUserData = [
			['UID' => '124', 'username' => 'testuser', 'email' => 'test2@example.com']
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn($existingUserData);

		$this->userMainRepositoryMock->expects($this->never())->method('update');

		$result = $this->usersAdminService->updateUser($UID, $postData);
		self::assertSame(0, $result);
		self::assertSame('username_exists', $this->usersAdminService->getErrorMessages()[0]);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserFailsExistsEmail(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser',
			'email' => 'test@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];
		$existingUserData = [
			['UID' => '124', 'username' => 'testuser2', 'email' => 'test@example.com']
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn($existingUserData);

		$this->userMainRepositoryMock->expects($this->never())->method('update');

		$result = $this->usersAdminService->updateUser($UID, $postData);
		self::assertSame(0, $result);
		self::assertSame('email_exists', $this->usersAdminService->getErrorMessages()[0]);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSucceedSameUserExistsUsername(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser',
			'email' => 'test2@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];
		$existingUserData = [
			['UID' => '123', 'username' => 'testuser', 'email' => 'test@example.com']
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn($existingUserData);

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, $postData)
			->willReturn(1);

		$result = $this->usersAdminService->updateUser($UID, $postData);
		self::assertSame(1, $result);
		self::assertEmpty($this->usersAdminService->getErrorMessages());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSucceedSameUserExistsMail(): void
	{
		$UID = 123;
		$postData = [
			'username' => 'testuser2',
			'email' => 'test@example.com',
			'locale' => 'en_US',
			'status' => 1,
		];
		$existingUserData = [
			['UID' => '123', 'username' => 'testuser', 'email' => 'test@example.com']
		];

		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->with($postData['username'], $postData['email'])
			->willReturn($existingUserData);

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, $postData)
			->willReturn(1);

		$result = $this->usersAdminService->updateUser($UID, $postData);
		self::assertSame(1, $result);
		self::assertEmpty($this->usersAdminService->getErrorMessages());
	}

}