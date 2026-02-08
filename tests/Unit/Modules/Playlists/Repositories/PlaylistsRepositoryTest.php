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
use Doctrine\DBAL\ArrayParameterType;
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
	public function testPlaylistModeWithIn(): void
	{
		$fields = [
			'playlist_mode' => ['value' => 'master,multizone']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('playlists')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('playlists', 'user_main', 'user_main', 'user_main.UID=playlists.UID')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->willReturnMap([
					['playlist_mode IN (:playlist_mode)', $this->queryBuilderMock]
				]
			);
		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->willReturnMap([
					['playlist_mode', ['master','multizone'], ArrayParameterType::STRING, $this->queryBuilderMock]
				]
			);

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$expectedCount = 42;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->repository->countAllFiltered($fields);
		static::assertSame($expectedCount, $result);

	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPlaylistMode(): void
	{
		$fields = [
			'playlist_mode' => ['value' => 'master']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('playlists')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('playlists', 'user_main', 'user_main', 'user_main.UID=playlists.UID')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->willReturnMap([
					['playlist_mode = :playlist_mode', $this->queryBuilderMock]
				]
			);
		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->willReturnMap([
					['playlist_mode', 'master', $this->queryBuilderMock]
				]
			);

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$expectedCount = 42;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->repository->countAllFiltered($fields);
		static::assertSame($expectedCount, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPlaylistModeEmpty(): void
	{
		$fields = [
			'playlist_mode' => ['value' => '']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('playlists')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('playlists', 'user_main', 'user_main', 'user_main.UID=playlists.UID')->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$expectedCount = 42;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->repository->countAllFiltered($fields);
		static::assertSame($expectedCount, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindByUIDPlaylistModeEmpty(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'playlist_mode' => ['value' => '']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('playlists.*, \'user\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('playlists')->willReturnSelf();

		$this->queryBuilderMock->expects($this->never())->method('leftJoin');

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);

		$expected = [['some_result' => 'result']];
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expected);

		$result = $this->repository->findAllFilteredByUID($fields, 1);
		static::assertSame($expected, $result);
	}



	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteReturnsExpectedResult(): void
	{
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

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredReturnsCorrectCount(): void
	{
		$fields = [
			'playlist_mode' => ['value' => 'multizone'],
			'playlist_name' => ['value' => 'name']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('playlists');

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('playlists', 'user_main', 'user_main', 'user_main.UID=playlists.UID')->willReturnSelf();


		$this->queryBuilderMock->expects($this->exactly(2))->method('andWhere')
			->willReturnMap([
					['playlist_mode = :playlist_mode', $this->queryBuilderMock],
					['playlists.playlist_name LIKE :playlistsplaylist_name', $this->queryBuilderMock]
				]
			);
		$this->queryBuilderMock->expects($this->exactly(2))->method('setParameter')
			->willReturnMap([
					['playlist_mode', 'multizone', $this->queryBuilderMock],
					['playlistsplaylist_name', '%name%', $this->queryBuilderMock]
				]
			);

		$expectedCount = 67;
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->repository->countAllFiltered($fields);
		static::assertSame($expectedCount, $result);
	}
}
