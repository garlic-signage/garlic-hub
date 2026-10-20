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


namespace Tests\Unit\Modules\Player\Repositories;

use App\Modules\Player\Repositories\PlayerTokenRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerTokenRepositoryTest extends TestCase
{
	private Result&MockObject $resultMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private PlayerTokenRepository $repository;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->resultMock = $this->createMock(Result::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$connectionStub = $this->createStub(Connection::class);
		$connectionStub->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->repository = new PlayerTokenRepository($connectionStub);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindByPlayerId(): void
	{
		$playerId = 123;
		$expectedResult = ['token_id' => 1, 'player_id' => 123, 'token' => 'sample_token'];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('*')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('player_tokens')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('player_id = :player_id')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('player_id', $playerId)
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findByPlayerId($playerId);

		static::assertSame($expectedResult, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindValidTokens(): void
	{
		$now = date('Y-m-d H:i:s');
		$expectedResult = [
			['token_id' => 1, 'player_id' => 123, 'expires_at' => '2025-12-31 23:59:59'],
			['token_id' => 2, 'player_id' => 456, 'expires_at' => '2025-11-30 22:00:00'],
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('*')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('player_tokens')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('expires_at > :now')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('now', $now)
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findValidTokens();

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindExpiredTokens(): void
	{
		$now = date('Y-m-d H:i:s');
		$expectedResult = [
			['token_id' => 3, 'player_id' => 789, 'expires_at' => '2025-01-01 00:00:00'],
			['token_id' => 4, 'player_id' => 101, 'expires_at' => '2024-12-31 23:59:59'],
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('*')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('player_tokens')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('expires_at <= :now')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('now', $now)
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findExpiredTokens();

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateForPlayerSuccess(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$playerId = 123;
		$updatedData = ['column_a' => 'new_value'];

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with($this->repository->getTable())
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('set')
			->with('updated_at', "'" . date('Y-m-d H:i:s') . "'")
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('player_id = :player_id')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('player_id', $playerId)
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeStatement')
			->willReturn(1);

		$result = $this->repository->updateForPlayer($playerId, $updatedData);

		static::assertSame(1, $result);
	}

}
