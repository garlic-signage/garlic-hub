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

namespace Tests\Unit\Modules\Playlists\Repositories;

use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaylistsRepositoryTest extends TestCase
{
	private Connection&MockObject	 $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private PlaylistsRepository $repository;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock   = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock       = $this->createMock(Result::class);

		$this->repository = new PlaylistsRepository($this->connectionMock);

		$this->connectionMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteReturnsExpectedResult(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->resultMock->expects($this->never())->method($this->anything());
		$id = 1;
		$expectedResult = 1;
		$sqliteplatform = new SQLitePlatform();
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn($sqliteplatform);

		$this->connectionMock->expects($this->once())->method('executeQuery')
			->with('PRAGMA foreign_keys = ON');

		$this->connectionMock->expects($this->once())->method('delete')
			->with('playlists', ['playlist_id' => $id])
			->willReturn($expectedResult);

		$result = $this->repository->delete($id);

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindFirstWithUserNameReturnsExpectedData(): void
	{
		$this->connectionMock->expects($this->atLeastOnce())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$playlistId = 1;
		$expectedData = [
			'playlist_id' => $playlistId,
			'title' => 'Sample Playlist',
			'username' => 'test_user',
			'company_id' => 123
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('playlists.*, user_main.username, user_main.company_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('playlists');
		$this->queryBuilderMock->expects($this->once())->method('andWhere')->with('playlist_id = :playlist_id');
		$this->queryBuilderMock->expects($this->once())->method('setParameter')->with('playlist_id', $playlistId);
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn([$expectedData]);

		$result = $this->repository->findFirstWithUserName($playlistId);

		static::assertSame($expectedData, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdateExportUpdatesDataSuccessfully(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$this->connectionMock->expects($this->atLeastOnce())->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$playlistId     = 1;
		$saveData       = ['title' => 'Updated Playlist', 'description' => 'Updated Description'];
		$expectedResult = 1;

		$this->queryBuilderMock->expects($this->once())->method('update')->with('playlists')->willReturnSelf();

		$this->queryBuilderMock->expects($this->exactly(3))->method('set')
			->willReturnMap([
				['title', 'Updated Playlist', $this->queryBuilderMock],
				['description', 'Updated Description', $this->queryBuilderMock],
				['export_time', 'CURRENT_TIMESTAMP', $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->once())->method('where')->with('playlist_id = :playlist_id')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('playlist_id', $playlistId, ParameterType::INTEGER)->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn($expectedResult);

		$result = $this->repository->updateExport($playlistId, $saveData);

		static::assertSame($expectedResult, $result);
	}
}
