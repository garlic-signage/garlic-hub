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


namespace Tests\Unit\Modules\Users\Repositories\Edge;

use App\Modules\Users\Repositories\Edge\UserTokensRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserTokensRepositoryTest extends TestCase
{
	private Connection&MockObject	 $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private UserTokensRepository $repository;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock   = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock       = $this->createMock(Result::class);

		$this->repository = new UserTokensRepository($this->connectionMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindFirstByTokenReturnsCorrectData(): void
	{
		$token = 'sampleToken';
		$expectedData = [
			'user_tokens' => ['token' => $token, 'UID' => 1],
			'username' => 'test_user',
			'status' => 'active',
			'company_id' => 123,
		];

		$this->queryBuilderMock->expects(self::once())->method('select')
			->with('user_tokens.*, username, status, expires_at, used_at, company_id')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('from')
			->with('user_tokens')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('leftJoin')
			->with('user_tokens', 'user_main', '', 'user_main.UID = user_tokens.UID')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('where')
			->with('token = :token')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('setParameter')
			->with('token', $token)
			->willReturnSelf();

		$this->connectionMock->expects(self::once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->repository = $this->getMockBuilder(UserTokensRepository::class)->setConstructorArgs([$this->connectionMock])
			->onlyMethods(['fetchAssociative'])
			->getMock();

		$this->repository->expects(self::once())->method('fetchAssociative')
			->with($this->queryBuilderMock)
			->willReturn($expectedData);

		$result = $this->repository->findFirstByToken($token);

		static::assertSame($expectedData, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindValidByUIDReturnsCorrectData(): void
	{
		$UID = 42;
		$expectedTokens = [
			['token' => 'token1', 'UID' => $UID, 'purpose' => 'login', 'expires_at' => '2025-12-31 23:59:59', 'used_at' => null],
			['token' => 'token2', 'UID' => $UID, 'purpose' => 'password_reset', 'expires_at' => '2025-12-31 23:59:59', 'used_at' => null],
		];
		$this->queryBuilderMock->expects(self::once())->method('select')
			->with('UID, purpose, expires_at, used_at')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('from')
			->with('user_tokens')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('where')
			->with('UID = :uid')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('andWhere')
			->with('used_at IS NULL')
			->willReturnSelf();

		$this->queryBuilderMock->expects(self::once())->method('setParameter')
			->with('uid', $UID)
			->willReturnSelf();

		$this->connectionMock->expects(self::once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects(self::once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects(self::once())->method('fetchAllAssociative')
			->willReturn($expectedTokens);


		$result = $this->repository->findValidByUID($UID);

		static::assertSame($expectedTokens, $result);
	}
}
