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

namespace Tests\Integration\Shared\User;

use App\Framework\Security\Crypt;
use App\Shared\User\Repositories\Edge\UserTokensRepository;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The tokens of the users (password links, the remember-me login) with the real database. Users: see auth/users.sql,
 * 10 regular, 11 deleted, 12 blocked.
 */
#[Group('db')]
class UserTokenServiceTest extends RepositoryTestCase
{
	private UserTokenService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->service = new UserTokenService(new UserTokensRepository($this->connection), new Crypt(Key::createNewRandomKey()), new NullLogger());
	}


	public function testATokenIsRandomAndLongEnough(): void
	{
		$first  = $this->service->generateToken();
		$second = $this->service->generateToken();

		static::assertNotSame($first, $second);
		static::assertSame(32, strlen($first));
	}

	public function testOnlyTheHashOfATokenIsStored(): void
	{
		$token = $this->service->generateToken();

		static::assertNotSame('', $this->service->insertToken(10, $token, TokenPurposes::PASSWORD_RESET));

		$stored = (string) $this->connection->fetchOne('SELECT token FROM user_tokens WHERE UID = 10');
		static::assertNotSame($token, $stored);
		static::assertNotSame(bin2hex($token), $stored);
		static::assertSame(64, strlen($stored));
	}

	public function testAValidTokenFindsTheUser(): void
	{
		$token = $this->service->generateToken();
		$this->service->insertToken(10, $token, TokenPurposes::PASSWORD_RESET);

		$found = $this->service->findByToken($token);

		static::assertIsArray($found);
		static::assertSame([10, 1, 'regular', 3, 'password_reset', null], [$found['UID'], $found['company_id'], $found['username'], $found['status'], $found['purpose'], $found['used_at']]);
	}

	public function testAnUnknownTokenIsNoToken(): void
	{
		static::assertNull($this->service->findByToken('unknown'));
		static::assertNull($this->service->findByTokenForAction('unknown'));
	}

	public function testAnExpiredTokenIsNoTokenForTheUserButTheActionFindsIt(): void
	{
		$token = $this->service->generateToken();
		$this->service->insertToken(10, $token, TokenPurposes::PASSWORD_RESET);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		static::assertNull($this->service->findByToken($token));
		static::assertSame(10, $this->service->findByTokenForAction($token)['UID'] ?? null);
	}

	public function testAUsedTokenIsNoTokenForTheUserButTheActionFindsIt(): void
	{
		$token = $this->service->generateToken();
		$this->service->insertToken(10, $token, TokenPurposes::PASSWORD_RESET);

		static::assertSame(1, $this->service->useToken($token));

		static::assertNull($this->service->findByToken($token));
		static::assertNotNull($this->service->findByTokenForAction($token)['used_at'] ?? null);
		static::assertSame(0, $this->service->useToken('unknown'));
	}

	/**
	 * @return array<string,array{TokenPurposes,string}> the purpose and how long the token is valid
	 */
	public static function lifetimes(): array
	{
		return [
			'initial password' => [TokenPurposes::INITIAL_PASSWORD, UserTokenService::TOKEN_EXP_HOURS_PWD_INIT],
			'password reset'   => [TokenPurposes::PASSWORD_RESET, UserTokenService::TOKEN_EXPIRATION_HOURS],
			'remember me'      => [TokenPurposes::AUTOLOGIN, UserTokenService::AUTOLOGIN_EXPIRE],
		];
	}

	#[DataProvider('lifetimes')]
	public function testATokenIsValidAsLongAsItsPurposeAllows(TokenPurposes $purpose, string $lifetime): void
	{
		$this->service->insertToken(10, $this->service->generateToken(), $purpose);

		$expires = strtotime((string) $this->connection->fetchOne('SELECT expires_at FROM user_tokens WHERE UID = 10'));

		static::assertEqualsWithDelta(strtotime($lifetime), $expires, 5);
	}

	#[DataProvider('lifetimes')]
	public function testARefreshedTokenIsNewAndValidAgain(TokenPurposes $purpose, string $lifetime): void
	{
		$old = $this->service->generateToken();
		$this->service->insertToken(10, $old, $purpose);
		$this->service->useToken($old);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		$new = $this->service->refreshToken(10, $purpose->value);

		static::assertNotSame('', $new);
		static::assertNull($this->service->findByTokenForAction($old));
		static::assertSame($purpose->value, $this->service->findByToken($new)['purpose'] ?? null);
		static::assertEqualsWithDelta(strtotime($lifetime), strtotime((string) $this->connection->fetchOne('SELECT expires_at FROM user_tokens WHERE UID = 10')), 5);
	}

	public function testNothingIsRefreshedForAnUnknownPurposeOrWithoutToken(): void
	{
		$this->service->insertToken(10, $this->service->generateToken(), TokenPurposes::PASSWORD_RESET);

		static::assertSame('', $this->service->refreshToken(10, 'anything'));
		static::assertSame('', $this->service->refreshToken(10, TokenPurposes::INITIAL_PASSWORD->value));
		static::assertSame('', $this->service->refreshToken(14, TokenPurposes::PASSWORD_RESET->value));
	}

	public function testTheTokensOfAUserAreListedWithoutTheUsedOnes(): void
	{
		$used = $this->service->generateToken();
		$this->service->insertToken(10, $used, TokenPurposes::PASSWORD_RESET);
		$this->service->insertToken(10, $this->service->generateToken(), TokenPurposes::AUTOLOGIN);
		$this->service->insertToken(14, $this->service->generateToken(), TokenPurposes::AUTOLOGIN);
		$this->service->useToken($used);

		$tokens = $this->service->findTokensByUID(10);

		static::assertSame(['autologin'], array_column($tokens, 'purpose'));
		static::assertSame(['UID', 'purpose', 'expires_at', 'used_at'], array_keys((array) $tokens[0]));
	}

	public function testTheTokensOfAPurposeOrOfAUserAreDeleted(): void
	{
		$this->service->insertToken(10, $this->service->generateToken(), TokenPurposes::PASSWORD_RESET);
		$this->service->insertToken(10, $this->service->generateToken(), TokenPurposes::AUTOLOGIN);
		$this->service->insertToken(14, $this->service->generateToken(), TokenPurposes::AUTOLOGIN);

		static::assertSame(0, $this->service->deleteByUserPurpose(10, 'anything'));
		static::assertSame(1, $this->service->deleteByUserPurpose(10, TokenPurposes::PASSWORD_RESET->value));
		static::assertSame(1, $this->service->deleteAllUserTokens(10));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens'));
	}

	public function testATokenIsDeletedByItself(): void
	{
		$token = $this->service->generateToken();
		$this->service->insertToken(10, $token, TokenPurposes::PASSWORD_RESET);

		static::assertSame(1, $this->service->deleteByToken($token));
		static::assertNull($this->service->findByTokenForAction($token));
		static::assertSame(0, $this->service->deleteByToken($token));
	}

	public function testOnlyTheExpiredTokensAreDeleted(): void
	{
		$this->service->insertToken(10, $this->service->generateToken(), TokenPurposes::PASSWORD_RESET);
		$this->service->insertToken(14, $this->service->generateToken(), TokenPurposes::PASSWORD_RESET);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00' WHERE UID = 10");

		static::assertSame(1, $this->service->deleteExpiredToken());
		static::assertSame([14], $this->connection->fetchFirstColumn('SELECT UID FROM user_tokens'));
	}
}
