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


namespace Tests\Unit\Modules\Player\Services;

use App\Framework\Security\Crypt;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use App\Modules\Player\Services\PlayerTokenService;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PlayerTokenServiceTest extends TestCase
{
	private String $testKey = 'def00000c3cfd8b3bbd0317e9283e4f77afdf78f506c38c5f500a15817ea0ac6588daf39685118a3fec8997e4fe6dc2cd23dc5ba434885a4bd63966ed53ec7a510984595';

	private PlayerTokenRepository&MockObject $playerTokenRepositoryMock;
	private Crypt&MockObject $cryptMock;
	private PlayerTokenService $playerTokenService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerTokenRepositoryMock = $this->createMock(PlayerTokenRepository::class);
		$this->cryptMock = $this->createMock(Crypt::class);
		$this->playerTokenService = new PlayerTokenService(
			$this->playerTokenRepositoryMock,
			$this->cryptMock
		);
	}

	/**
	 * @throws BadFormatException
	 * @throws EnvironmentIsBrokenException
	 */
	#[Group('units')]
	public function testStoreTokenSuccessInsert(): void
	{
		$playerId = 1;
		$accessToken = 'test-token';
		$expiresAt = '2025-12-31 23:59:59';
		$tokenType = 'Bearer';

		// defuse Crypt Key is final
		$key = Key::loadFromAsciiSafeString($this->testKey);

		$this->cryptMock->expects($this->once())->method('getEncryptionKey')
			->willReturn($key);

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn([]);

		$this->playerTokenRepositoryMock->expects($this->once())->method('insert');

		$this->playerTokenService->storeToken(
			$playerId,
			$accessToken,
			$expiresAt,
			$tokenType
		);
	}

	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	#[Group('units')]
	public function testStoreTokenSuccessUpdate(): void
	{
		$playerId = 1;
		$accessToken = 'test-token';
		$expiresAt = '2025-12-31 23:59:59';
		$tokenType = 'Bearer';

		// defuse Crypt Key is final
		$key = Key::loadFromAsciiSafeString($this->testKey);

		$this->cryptMock->expects($this->once())->method('getEncryptionKey')
			->willReturn($key);

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn(['player_id' => 1, 'player_name' => 'hurz', 'token_id' => 'djbfskdjh']);

		$this->playerTokenRepositoryMock->expects($this->once())->method('update');

		$this->playerTokenService->storeToken(
			$playerId,
			$accessToken,
			$expiresAt,
			$tokenType
		);
	}

	#[Group('units')]
	public function testStoreTokenPassesErrorOn(): void
	{
		$playerId = 1;
		$accessToken = 'test-token';
		$expiresAt = '2025-12-31 23:59:59';
		$exception = new RuntimeException('Error occurred');

		$this->cryptMock
			->expects($this->once())
			->method('getEncryptionKey')
			->willThrowException($exception);

		$this->expectExceptionObject($exception);

		$this->playerTokenRepositoryMock
			->expects($this->never())
			->method('insert');

		$this->playerTokenService->storeToken($playerId, $accessToken, $expiresAt);
	}


	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	#[Group('units')]
	public function testGetTokenSuccess(): void
	{
		$playerId = 1;
		$encryptedToken = Crypto::encrypt('test-token', Key::loadFromAsciiSafeString($this->testKey));
		$tokenData = [
			'player_id' => $playerId,
			'access_token' => $encryptedToken,
			'token_type' => 'Bearer',
			'expires_at' => '2035-12-31 23:59:59',
		];

		$this->cryptMock->expects($this->once())->method('getEncryptionKey')
			->willReturn(Key::loadFromAsciiSafeString($this->testKey));

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn($tokenData);

		$result = $this->playerTokenService->getToken($playerId);

		static::assertEquals('test-token', $result['access_token']);
		static::assertEquals('Bearer', $result['token_type']);
		static::assertEquals('2035-12-31 23:59:59', $result['expires_at']);
	}

	#[Group('units')]
	public function testGetTokenExpired(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$playerId = 1;
		$expiredTokenData = [
			'player_id' => $playerId,
			'access_token' => 'expired-token',
			'token_type' => 'Bearer',
			'expires_at' => '2023-12-31 23:59:59',
		];

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn($expiredTokenData);

		$this->playerTokenRepositoryMock->expects($this->once())->method('delete')
			->with($playerId);

		$result = $this->playerTokenService->getToken($playerId);

		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testGetTokenEmptyResponse(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$playerId = 1;

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn([]);

		$result = $this->playerTokenService->getToken($playerId);

		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testGetTokenPassesRepositoryErrorOn(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$playerId = 1;
		$exception = new RuntimeException('Error occurred');

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willThrowException($exception);

		$this->expectExceptionObject($exception);
		$this->playerTokenService->getToken($playerId);
	}

	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	#[Group('units')]
	public function testGetTokenEncryptedWithOtherKeyIsNoToken(): void
	{
		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->willReturn([
				'access_token' => Crypto::encrypt('test-token', Key::createNewRandomKey()),
				'token_type'   => 'Bearer',
				'expires_at'   => '2035-12-31 23:59:59',
			]);
		$this->cryptMock->expects($this->once())->method('getEncryptionKey')
			->willReturn(Key::loadFromAsciiSafeString($this->testKey));

		static::assertSame([], $this->playerTokenService->getToken(1));
	}

	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	#[Group('units')]
	public function testHasValidTokenSuccess(): void
	{
		$playerId = 1;
		$encryptedToken = Crypto::encrypt('test-token', Key::loadFromAsciiSafeString($this->testKey));
		$tokenData = [
			'player_id' => $playerId,
			'access_token' => $encryptedToken,
			'token_type' => 'Bearer',
			'expires_at' => '2035-12-31 23:59:59',
		];

		$this->cryptMock->expects($this->once())->method('getEncryptionKey')
			->willReturn(Key::loadFromAsciiSafeString($this->testKey));

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn($tokenData);

		$result = $this->playerTokenService->hasValidToken($playerId);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testHasValidTokenFailure(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$playerId = 1;

		$this->playerTokenRepositoryMock->expects($this->once())->method('findByPlayerId')
			->with($playerId)
			->willReturn([]);

		$result = $this->playerTokenService->hasValidToken($playerId);

		static::assertFalse($result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCleanupExpiredTokensSuccess(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$deletedCount = 3;

		$this->playerTokenRepositoryMock->expects($this->once())->method('deleteBy')
			->with(['expires_at <=' => date('Y-m-d H:i:s')])
			->willReturn($deletedCount);

		$result = $this->playerTokenService->cleanupExpiredTokens();

		static::assertEquals($deletedCount, $result);
	}

	#[Group('units')]
	public function testRefreshTokenPassesErrorOn(): void
	{
		$playerId = 1;
		$newTokenData = [
			'access_token' => 'new-test-token',
			'expires_at' => '2026-12-31 23:59:59',
			'token_type' => 'Bearer'
		];
		$exception = new RuntimeException('Error occurred');

		$this->cryptMock
			->expects($this->once())
			->method('getEncryptionKey')
			->willThrowException($exception);

		$this->expectExceptionObject($exception);

		$this->playerTokenRepositoryMock->expects($this->never())->method('insert');

		$this->playerTokenService->refreshToken($playerId, $newTokenData);
	}
}
