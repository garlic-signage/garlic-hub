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

use App\Modules\Player\Repositories\PlayerRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerRepositoryTest extends TestCase
{
	private Connection&MockObject	 $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private PlayerRepository $repository;

	/**
	 * @throws Exception
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock = $this->createMock(Result::class);

		$this->repository = new PlayerRepository($this->connectionMock);

		$this->connectionMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardSqlite(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->resultMock->expects($this->never())->method($this->anything());
		$sqliteplatform = new SQLitePlatform();
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn($sqliteplatform);

		$SqLiteSql = "SELECT
				SUM(CASE WHEN last_access >= DATETIME('now', '-' || (2 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS active,
				SUM(CASE WHEN last_access < DATETIME('now', '-' || (2 * refresh) || ' seconds')
						 AND last_access >= DATETIME('now', '-' || (4 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN last_access < DATETIME('now', '-' || (4 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS inactive
			FROM
				player;";

		$this->connectionMock->expects($this->once())->method('fetchAssociative')
			->with($SqLiteSql)
			->willReturn(['active' => 3, 'pending' => 2, 'inactive' => 5]);

		$result = $this->repository->findAllForDashboard();
		static::assertNotEmpty($result);
		static::assertEquals(3, $result['active']);
		static::assertEquals(2, $result['pending']);
		static::assertEquals(5, $result['inactive']);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardMariaDB(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->resultMock->expects($this->never())->method($this->anything());
		$sqliteplatform = new MariaDBPlatform();
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn($sqliteplatform);

		$mariaDBSql = 'SELECT
            SUM(CASE WHEN last_access >= DATE_SUB(NOW(), INTERVAL (2 * refresh) SECOND) THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN last_access < DATE_SUB(NOW(), INTERVAL (2 * refresh) SECOND)
                      AND last_access >= DATE_SUB(NOW(), INTERVAL (4 * refresh) SECOND) THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN last_access < DATE_SUB(NOW(), INTERVAL (4 * refresh) SECOND) THEN 1 ELSE 0 END) AS inactive
        FROM
            player;';

		$this->connectionMock->expects($this->once())->method('fetchAssociative')
			->with($mariaDBSql)
			->willReturn(['active' => 6, 'pending' => 7, 'inactive' => 8]);

		$result = $this->repository->findAllForDashboard();
		static::assertNotEmpty($result);
		static::assertEquals(6, $result['active']);
		static::assertEquals(7, $result['pending']);
		static::assertEquals(8, $result['inactive']);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardEmpty(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->resultMock->expects($this->never())->method($this->anything());
		$sqliteplatform = new PostgreSQLPlatform();
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn($sqliteplatform);

		$this->connectionMock->expects($this->once())->method('fetchAssociative')
			->willReturn(false);

		static::assertEmpty($this->repository->findAllForDashboard());
	}

	#[Group('units')]
	public function testConstructorInitializesConnection(): void
	{
		$this->expectUntouched($this->connectionMock, $this->queryBuilderMock, $this->resultMock);
		static::assertSame('player', $this->repository->getTable());
		static::assertSame('player_id', $this->repository->getIdField());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindPlaylistIdsByPlaylistIdsWithEmptyArray(): void
	{
		$this->expectUntouched($this->connectionMock, $this->queryBuilderMock, $this->resultMock);
		$result = $this->repository->findPlaylistIdsByPlaylistIds([]);
		static::assertSame([], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindPlaylistIdsByPlaylistIdsWithValidIds(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$playlistIds = [1, 2, 3];
		$expectedSql = 'SELECT playlist_id FROM player WHERE playlist_id IN(?)';
		$expectedResult = [
			['playlist_id' => 1],
			['playlist_id' => 2],
			['playlist_id' => 3],
		];

		$this->connectionMock->expects($this->once())
			->method('executeQuery')
			->with($expectedSql, [$playlistIds], [ArrayParameterType::INTEGER])
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())
			->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findPlaylistIdsByPlaylistIds($playlistIds);
		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindPlaylistIdsByPlaylistIdsWithNonExistentIds(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$playlistIds = [4, 5, 6];
		$expectedSql = 'SELECT playlist_id FROM player WHERE playlist_id IN(?)';
		$expectedResult = [];

		$this->connectionMock->expects($this->once())
			->method('executeQuery')
			->with($expectedSql, [$playlistIds], [ArrayParameterType::INTEGER])
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())
			->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findPlaylistIdsByPlaylistIds($playlistIds);
		static::assertSame($expectedResult, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
