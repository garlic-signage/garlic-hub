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
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\NodesService;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserMainRepository;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UsersAdminServiceTest extends TestCase
{
	private UserMainRepository&MockObject $userMainRepositoryMock;
	private UserAclRepository&Stub $userAclRepositoryStub;
	private NodesService&MockObject $nodesServiceMock;
	private UserTokenService&MockObject $userTokenServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Transactions&MockObject $transactionsMock;
	private UserSession&Stub $userSessionStub;
	private UsersAdminService $usersAdminService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userMainRepositoryMock = $this->createMock(UserMainRepository::class);
		$this->userAclRepositoryStub = $this->createStub(UserAclRepository::class);
		$this->nodesServiceMock = $this->createMock(NodesService::class);
		$this->userTokenServiceMock = $this->createMock(UserTokenService::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->transactionsMock = $this->createMock(Transactions::class);

		$this->userSessionStub = $this->createStub(UserSession::class);
		$this->usersAdminService = new UsersAdminService(
			$this->userMainRepositoryMock,
			$this->userAclRepositoryStub,
			$this->nodesServiceMock,
			$this->userTokenServiceMock,
			$this->aclValidatorMock,
			$this->transactionsMock,
			$this->userSessionStub
		);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadForAdminEditReturnsEmptyArrayWhenUserNotFound(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
		$this->expectUntouched($this->nodesServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFIED->value,
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
		$this->userSessionStub->method('getUID')->willReturn(1);
		$result = $this->usersAdminService->insertNewUser($postData);
		self::assertSame($UID, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertThrowsNotUnique(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock);
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
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('username_exists');

		$this->usersAdminService->insertNewUser($postData);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertThrowsWhenInsertFails(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFIED->value,
		];

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('findExistingUser')
			->willReturn([]);
		$this->userMainRepositoryMock->expects($this->once())->method('insert')
			->with($postData)
			->willReturn(0);
		$this->userTokenServiceMock->expects($this->never())->method('insertToken');

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Insert failed.');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->usersAdminService->insertNewUser($postData);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertRollsBackWhenNodeCreateFails(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$postData = [
			'username' => 'testuser',
			'email' => 'testuser@example.com',
			'locale' => 'en_US',
			'status' => UserStatus::NOT_VERIFIED->value,
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
			->willThrowException(new FrameworkException('Insert new node failed'));

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Insert new node failed');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->usersAdminService->insertNewUser($postData);
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
			->with(1)
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willReturn(1);
		// tokens of a deleted user must not survive
		$this->userTokenServiceMock->expects($this->once())->method('deleteAllUserTokens')->with($UID);
		$aclRepositoryMock = $this->createMock(UserAclRepository::class);
		$aclRepositoryMock->expects($this->once())->method('delete')->with($UID);
		$this->nodesServiceMock->expects($this->once())->method('deleteUserDirectory')
			->with($UID);
		$this->transactionsMock->expects($this->once())->method('commit');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$service = new UsersAdminService(
			$this->userMainRepositoryMock,
			$aclRepositoryMock,
			$this->nodesServiceMock,
			$this->userTokenServiceMock,
			$this->aclValidatorMock,
			$this->transactionsMock,
			$this->userSessionStub
		);
		$service->deleteUser($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserThrowsWithoutRights(): void
	{
		$this->expectUntouched($this->userMainRepositoryMock, $this->nodesServiceMock, $this->userTokenServiceMock);
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(false);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('user_not_deletable');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->usersAdminService->deleteUser($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserThrowsWhenUserNotFound(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->userTokenServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('user_not_found');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->usersAdminService->deleteUser($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserRollsBackAndPassesErrorOn(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->userTokenServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($this->anything())
			->willReturn(true);
		$this->userMainRepositoryMock->expects($this->once())->method('delete')
			->with($UID)
			->willThrowException(new \Exception('Unexpected error'));
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Unexpected error');

		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->usersAdminService->deleteUser($UID);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenThrowsWhenTokensExist(): void
	{
		$this->expectUntouched($this->userMainRepositoryMock, $this->nodesServiceMock, $this->aclValidatorMock, $this->transactionsMock);
		$UID = 123;
		$existingTokens = [['token' => 'existing_token', 'purpose' => 'password_reset', 'expires_at' => '2999-01-01 00:00:00']];

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn($existingTokens);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('tokens_exists');
		$this->usersAdminService->createPasswordResetToken($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenThrowsWhenUpdateFails(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$UID = 123;

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');
		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willReturn(0);
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('password_reset_failed');

		$this->usersAdminService->createPasswordResetToken($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenThrowsWhenTokenInsertionFails(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
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
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('password_reset_failed');

		$this->usersAdminService->createPasswordResetToken($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenSuccess(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->aclValidatorMock);
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
	public function testCreatePasswordResetTokenRollsBackAndPassesErrorOn(): void
	{
		$this->nodesServiceMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$UID = 123;

		$this->userTokenServiceMock->expects($this->once())->method('findTokensByUID')
			->with($UID)
			->willReturn([]);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->userMainRepositoryMock->expects($this->once())->method('update')
			->with($UID, ['password' => ''])
			->willThrowException(new \Exception('Unexpected error'));

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Unexpected error');

		$this->usersAdminService->createPasswordResetToken($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserThrowsWhenNotUnique(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('username_exists');
		$this->usersAdminService->updateUser($UID, $postData);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSuccess(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
	public function testUpdateUserThrowsExistsUserName(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('username_exists {"username":"testuser"}');
		$this->usersAdminService->updateUser($UID, $postData);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserThrowsExistsEmail(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('email_exists {"email":"test@example.com"}');
		$this->usersAdminService->updateUser($UID, $postData);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSucceedSameUserExistsUsername(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateUserSucceedSameUserExistsMail(): void
	{
		$this->expectUntouched($this->nodesServiceMock, $this->userTokenServiceMock, $this->aclValidatorMock, $this->transactionsMock);
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
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
