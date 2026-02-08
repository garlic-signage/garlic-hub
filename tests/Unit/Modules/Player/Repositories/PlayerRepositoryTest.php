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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
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
		static::assertSame('player', $this->repository->getTable());
		static::assertSame('player_id', $this->repository->getIdField());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllFullFiltered(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'activity'      => ['value' => 'active'],
			'playlist_id'   => ['value' => 12],
			'player_name'   => ['value' => 'name']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('player_id, player.playlist_id, playlist_name, is_intranet, firmware, player.status, model, commands, reports, player.last_access, refresh, player_name, player.UID, user_main.username, user_main.company_id, \'moduleadmin\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('player');

		$this->queryBuilderMock->expects($this->exactly(2))->method('leftJoin')
			->willReturnMap([
				['player', 'user_main', 'user_main', 'user_main.UID = player.UID', $this->queryBuilderMock],
				['player', 'playlists', 'playlists', 'playlists.playlist_id = player.playlist_id', $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->exactly(3))->method('andWhere')
			->willReturnMap([
					['player.last_access = :playerlast_access', $this->queryBuilderMock],
					['playlist_id = :playlist_id', $this->queryBuilderMock],
					['player.player_name LIKE :playerplayer_name', $this->queryBuilderMock],
				]);
		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
					['playerlast_access', '(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(player.last_access)) < refresh * 2', ParameterType::STRING, $this->queryBuilderMock],
					['playlist_id', 12, ParameterType::STRING, $this->queryBuilderMock],
					['playerplayer_name', '%name%', ParameterType::STRING, $this->queryBuilderMock],
				]
			);

		$expected = [['some_result' => 'result']];
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expected);

		$result = $this->repository->findAllFiltered($fields);
		static::assertSame($expected, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllInactive(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'activity'      => ['value' => 'inactive']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('player_id, player.playlist_id, playlist_name, is_intranet, firmware, player.status, model, commands, reports, player.last_access, refresh, player_name, player.UID, user_main.username, user_main.company_id, \'moduleadmin\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('player');

		$this->queryBuilderMock->expects($this->exactly(2))->method('leftJoin')
			->willReturnMap([
				['player', 'user_main', 'user_main', 'user_main.UID = player.UID', $this->queryBuilderMock],
				['player', 'playlists', 'playlists', 'playlists.playlist_id = player.playlist_id', $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('player.last_access = :playerlast_access');

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('playerlast_access', '(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(player.last_access)) > refresh * 2');

		$expected = [['some_result' => 'result']];
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expected);

		$result = $this->repository->findAllFiltered($fields);
		static::assertSame($expected, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAll(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'activity' => ['value' => '']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('player_id, player.playlist_id, playlist_name, is_intranet, firmware, player.status, model, commands, reports, player.last_access, refresh, player_name, player.UID, user_main.username, user_main.company_id, \'moduleadmin\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('player');

		$this->queryBuilderMock->expects($this->exactly(2))->method('leftJoin')
			->willReturnMap([
				['player', 'user_main', 'user_main', 'user_main.UID = player.UID', $this->queryBuilderMock],
				['player', 'playlists', 'playlists', 'playlists.playlist_id = player.playlist_id', $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->never())->method('andWhere');

		$this->queryBuilderMock->expects($this->never())->method('setParameter');

		$expected = [['some_result' => 'result']];
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expected);

		$result = $this->repository->findAllFiltered($fields);
		static::assertSame($expected, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllFilteredByUID(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'activity' => ['value' => '']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('player_id, player.playlist_id, playlist_name, is_intranet, firmware, player.status, model, commands, reports, player.last_access, refresh, player_name, player.UID, \'user\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('player');

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->willReturnMap([
				['player', 'playlists', 'playlists', 'playlists.playlist_id = player.playlist_id', $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->once())->method('andWhere');
		$this->queryBuilderMock->expects($this->once())->method('setParameter');

		$expected = [['some_result' => 'result']];
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expected);

		$result = $this->repository->findAllFilteredByUID($fields, 1);
		static::assertSame($expected, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindPlaylistIdsByPlaylistIdsWithEmptyArray(): void
	{
		$result = $this->repository->findPlaylistIdsByPlaylistIds([]);
		static::assertSame([], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindPlaylistIdsByPlaylistIdsWithValidIds(): void
	{
		$playlistIds = [1, 2, 3];
		$expectedSql = 'SELECT playlist_id FROM player WHERE playlist_id IN(1,2,3)';
		$expectedResult = [
			['playlist_id' => 1],
			['playlist_id' => 2],
			['playlist_id' => 3],
		];

		$this->connectionMock->expects($this->once())
			->method('executeQuery')
			->with($expectedSql)
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
		$playlistIds = [4, 5, 6];
		$expectedSql = 'SELECT playlist_id FROM player WHERE playlist_id IN(4,5,6)';
		$expectedResult = [];

		$this->connectionMock->expects($this->once())
			->method('executeQuery')
			->with($expectedSql)
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())
			->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$result = $this->repository->findPlaylistIdsByPlaylistIds($playlistIds);
		static::assertSame($expectedResult, $result);
	}
}
