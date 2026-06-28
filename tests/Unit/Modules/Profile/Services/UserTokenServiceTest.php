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
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Repositories\Edge\UserTokensRepository;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserTokenServiceTest extends TestCase
{
	private UserTokensRepository&MockObject $userTokensRepositoryMock;
	private Crypt&MockObject $cryptMock;
	private UserTokenService $userTokenService;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userTokensRepositoryMock = $this->createMock(UserTokensRepository::class);
		$this->cryptMock = $this->createMock(Crypt::class);
		$loggerMock = $this->createMock(LoggerInterface::class);

		$this->userTokenService = new UserTokenService($this->userTokensRepositoryMock, $this->cryptMock, $loggerMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindTokenByUIDWithValidUID(): void
	{
		$UID = 1;
		$resultData = [
			['token' => 'validToken1', 'UID' => 1, 'purpose' => 'testPurpose1', 'expires_at' => '2025-07-11 15:00:00', 'used_at' => null],
			['token' => 'validToken2', 'UID' => 1, 'purpose' => 'testPurpose2', 'expires_at' => '2025-07-12 15:00:00', 'used_at' => null],
		];

		$this->userTokensRepositoryMock->expects($this->once())
			->method('findValidByUID')
			->with($UID)
			->willReturn($resultData);

		$result = $this->userTokenService->findTokensByUID($UID);
		self::assertSame($resultData, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenForActionWithInvalidToken(): void
	{
		$token = 'invalidToken';
		$result = $this->userTokenService->findByTokenForAction($token);
		self::assertNull($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenForActionWithEmptyResult(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);
		$this->userTokensRepositoryMock->expects($this->once())->method('findFirstByToken')
			->with($hashedToken)
			->willReturn([]);

		$result = $this->userTokenService->findByTokenForAction($token);
		self::assertNull($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenForAction(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);

		$expected = [
			'UID' => 123,
			'company_id' => 456,
			'username' => 'beispielBenutzer',
			'status' => 1,
			'purpose' => 'password_reset'
		];

		$this->userTokensRepositoryMock->expects($this->once())->method('findFirstByToken')
			->with($hashedToken)
			->willReturn($expected);

		$result = $this->userTokenService->findByTokenForAction($token);
		self::assertSame($expected, $result);
	}


	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenWithValidToken(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);
		$expires = new DateTime('+1 hour')->format('Y-m-d H:i:s');
		$resultData = [
			'UID' => 1,
			'company_id' => 10,
			'username' => 'JohnDoe',
			'status' => 1,
			'purpose' => 'testPurpose',
			'expires_at' => $expires,
			'used_at' => null,
		];

		$this->userTokensRepositoryMock->expects($this->once())
			->method('findFirstByToken')
			->with($hashedToken)
			->willReturn($resultData);

		$result = $this->userTokenService->findByToken($token);

		self::assertSame([
			'UID' => 1,
			'company_id' => 10,
			'username' => 'JohnDoe',
			'status' => 1,
			'expires_at' => $expires,
			'used_at' => null,
			'purpose' => 'testPurpose',
		], $result);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenWithExpiredToken(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);
		$expires = new DateTime('-10 hour')->format('Y-m-d H:i:s');
		$resultData = [
			'UID' => 2,
			'company_id' => 15,
			'username' => 'JaneDoe',
			'status' => 1,
			'purpose' => 'expiredPurpose',
			'expires_at' => $expires,
			'used_at' => null,
		];

		$this->userTokensRepositoryMock->expects($this->once())
			->method('findFirstByToken')
			->with($hashedToken)
			->willReturn($resultData);

		$result = $this->userTokenService->findByToken($token);

		self::assertNull($result);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenWithUsedToken(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);
		$resultData = [
			'UID' => 3,
			'company_id' => 20,
			'username' => 'UserUsed',
			'status' => 1,
			'purpose' => 'usedPurpose',
			'expires_at' => new DateTime('+1 hour')->format('Y-m-d H:i:s'),
			'used_at' => new DateTime('-1 hour')->format('Y-m-d H:i:s'),
		];

		$this->userTokensRepositoryMock->expects($this->once())
			->method('findFirstByToken')
			->with($hashedToken)
			->willReturn($resultData);

		$result = $this->userTokenService->findByToken($token);

		self::assertNull($result);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByTokenWithEmptyResult(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);

		$this->userTokensRepositoryMock->expects($this->once())
			->method('findFirstByToken')
			->with($hashedToken)
			->willReturn([]);

		$result = $this->userTokenService->findByToken($token);

		self::assertNull($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindTokenByUIDWithInvalidUID(): void
	{
		$UID = 42;
		$this->userTokensRepositoryMock->expects($this->once())
			->method('findValidByUID')
			->with($UID)
			->willReturn([]);

		$result = $this->userTokenService->findTokensByUID($UID);
		self::assertSame([], $result);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertTokenWithInitialPasswordPurpose(): void
	{
		$UID = 1;
		$purpose = TokenPurposes::INITIAL_PASSWORD;
		$expectedExpiration = date('Y-m-d H:i:s', strtotime('+24 hour'));
		$generatedToken = 'randomTokenData';
		$token          = 'someToken';
		$insertedId = '123';

		$this->cryptMock->expects($this->once())
			->method('createHmacSha256')
			->willReturn($generatedToken);

		$this->userTokensRepositoryMock->expects($this->once())
			->method('insert')
			->with([
				'UID' => $UID,
				'purpose' => $purpose->value,
				'token' => $generatedToken,
				'expires_at' => $expectedExpiration
			])
			->willReturn($insertedId);

		$result = $this->userTokenService->insertToken($UID, $token, $purpose);

		self::assertSame($insertedId, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsertTokenWithOtherPurpose(): void
	{
		$UID = 2;
		$purpose = TokenPurposes::EMAIL_VERIFICATION;
		$expectedExpiration = date('Y-m-d H:i:s', strtotime('+2 hour'));
		$generatedToken = 'randomVerificationToken';
		$token          = 'someToken';
		$insertedId = '456';

		$this->cryptMock->expects($this->once())
			->method('createHmacSha256')
			->willReturn($generatedToken);

		$this->userTokensRepositoryMock->expects($this->once())
			->method('insert')
			->with([
				'UID' => $UID,
				'purpose' => $purpose->value,
				'token' => $generatedToken,
				'expires_at' => $expectedExpiration
			])
			->willReturn($insertedId);

		$result = $this->userTokenService->insertToken($UID, $token, $purpose);

		self::assertSame($insertedId, $result);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteTokenWithValidToken(): void
	{
		$this->userTokensRepositoryMock->expects($this->once())
			->method('deleteBy')
			->willReturn(1);

		$result = $this->userTokenService->deleteByUserPurpose(1, TokenPurposes::EMAIL_VERIFICATION->value);

		self::assertSame(1, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteTokenWithInvalidHexToken(): void
	{

		$this->userTokensRepositoryMock->expects($this->never())
			->method('deleteBy');

		$result = $this->userTokenService->deleteByUserPurpose(1, 'invalid_token_purpose');

		self::assertSame(0, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRefreshTokenWithValidToken(): void
	{
		$token = 'someToken';
		$this->cryptMock->expects($this->once())->method('generateRandomBytes')->willReturn($token);
		$this->userTokensRepositoryMock->expects($this->once())->method('refresh')
			->willReturn(1);

		$result = $this->userTokenService->refreshToken(1, TokenPurposes::EMAIL_VERIFICATION->value);
		self::assertSame($token, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRefreshTokenWithInvalidPurpose(): void
	{
		$this->cryptMock->expects($this->never())->method('generateRandomBytes');

		$result = $this->userTokenService->refreshToken(1, 'invalid_purpose');

		self::assertSame('', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRefreshTokenFails(): void
	{
		$token = 'someToken';
		$this->cryptMock->expects($this->once())->method('generateRandomBytes')->willReturn($token);

		$this->userTokensRepositoryMock->expects($this->once())
			->method('refresh')
			->willReturn(0);

		$result = $this->userTokenService->refreshToken(1, TokenPurposes::INITIAL_PASSWORD->value);

		self::assertSame('', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUseToken(): void
	{
		$token = 'validToken';
		$hashedToken = 'hashedToken';
		$this->cryptMock->expects($this->once())->method('createHmacSha256')->willReturn($hashedToken);

		$this->userTokensRepositoryMock->expects($this->once())
			->method('update')
			->willReturn(1);

		$result = $this->userTokenService->useToken($token);

		self::assertSame(1, $result);
	}
}