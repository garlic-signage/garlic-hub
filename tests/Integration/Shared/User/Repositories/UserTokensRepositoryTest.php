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

namespace Tests\Integration\Shared\User\Repositories;

use App\Shared\User\Repositories\Edge\UserTokensRepository;
use App\Shared\User\TokenPurposes;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class UserTokensRepositoryTest extends RepositoryTestCase
{
	private UserTokensRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new UserTokensRepository($this->connection);
		$this->loadFixture('auth/users.sql');
		$this->loadFixture('auth/tokens.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstByTokenAddsTheUser(): void
	{
		$token = $this->repository->findFirstByToken('tok-valid');

		static::assertSame(10, (int) $token['UID']);
		static::assertSame('regular', $token['username']);
		static::assertSame(3, (int) $token['status']);
		static::assertSame(1, (int) $token['company_id']);
		static::assertSame('password_reset', $token['purpose']);
		static::assertNull($token['used_at']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstByTokenOfAnUnknownToken(): void
	{
		static::assertSame([], $this->repository->findFirstByToken('tok-unknown'));
		static::assertSame([], $this->repository->findFirstByToken(''));
	}

	/**
	 * @throws Exception
	 */
	public function testFindValidByUidReturnsUnusedTokensOfTheUser(): void
	{
		$tokens = $this->repository->findValidByUID(10);

		static::assertCount(1, $tokens);
		static::assertSame(['password_reset'], array_column($tokens, 'purpose'));
		static::assertSame([], $this->repository->findValidByUID(999));
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteExpiredRemovesOnlyExpiredTokens(): void
	{
		static::assertSame(1, $this->repository->deleteExpired());

		static::assertSame([], $this->repository->findFirstByToken('tok-expired'));
		static::assertSame(10, (int) $this->repository->findFirstByToken('tok-valid')['UID']);
		static::assertSame(0, $this->repository->deleteExpired());
	}

	/**
	 * @throws Exception
	 */
	public function testRefreshReplacesTokenAndResetsUsage(): void
	{
		$this->connection->executeStatement("UPDATE user_tokens SET used_at = '2025-01-01 10:00:00' WHERE token = 'tok-valid'");

		$affected = $this->repository->refresh(
			['token' => 'tok-new', 'expires_at' => '2999-01-01 00:00:00', 'used_at' => null],
			10,
			TokenPurposes::PASSWORD_RESET
		);

		static::assertSame(1, $affected);
		static::assertSame([], $this->repository->findFirstByToken('tok-valid'));
		$token = $this->repository->findFirstByToken('tok-new');
		static::assertNull($token['used_at']);
		static::assertSame('2999-01-01 00:00:00', $token['expires_at']);
		// other purposes of the same user stay as they are
		static::assertSame(10, (int) $this->repository->findFirstByToken('tok-used')['UID']);
	}

	/**
	 * @throws Exception
	 */
	public function testRefreshOfAMissingTokenChangesNothing(): void
	{
		$affected = $this->repository->refresh(
			['token' => 'tok-x', 'expires_at' => '2999-01-01 00:00:00', 'used_at' => null],
			14,
			TokenPurposes::EMAIL_VERIFICATION
		);

		static::assertSame(0, $affected);
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteByUserAndPurposeLeavesTheRest(): void
	{
		static::assertSame(1, $this->repository->deleteBy(['UID' => 10, 'purpose' => TokenPurposes::AUTOLOGIN->value]));

		static::assertSame([], $this->repository->findFirstByToken('tok-used'));
		static::assertSame(10, (int) $this->repository->findFirstByToken('tok-valid')['UID']);
	}
}
