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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaylistsServiceTest extends TestCase
{
	private LoggerInterface&MockObject $loggerMock;
	private PlaylistsRepository&MockObject $playlistsRepositoryMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private AclValidator&MockObject $aclValidatorMock;
	private PlaylistsService $service;


	/**
	 * Set up the test environment.
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->playlistsRepositoryMock = $this->createMock(PlaylistsRepository::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);

		$this->service = new PlaylistsService($this->playlistsRepositoryMock, $this->playlistMetricsCalculatorMock, $this->aclValidatorMock, $this->loggerMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateNewSuccessfullyInsertsData(): void
	{
		$postData = ['UID' => 123, 'playlist_mode' => 'private', 'playlist_name' => 'Test Playlist', 'time_limit' => 60];
		$saveData = ['UID' => 123, 'playlist_mode' => 'private', 'playlist_name' => 'Test Playlist', 'time_limit' => 60];

		$this->playlistsRepositoryMock->expects($this->once())->method('insert')
			->with($saveData)
			->willReturn(1);

		$result = $this->service->createNew($postData);

		static::assertEquals(1, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateNewSuccessfullyInsertsDataWithoutUID(): void
	{
		$this->service->setUID(567);
		$postData = ['playlist_mode' => 'private', 'playlist_name' => 'Test Playlist', 'layout' => []];
		$saveData = ['UID' => 567, 'playlist_mode' => 'private', 'playlist_name' => 'Test Playlist', 'layout' => []];

		$this->playlistsRepositoryMock->expects($this->once())->method('insert')
			->with($saveData)
			->willReturn(1);

		$result = $this->service->createNew($postData);

		static::assertEquals(1, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateNewThrowsExceptionOnRepositoryError(): void
	{
		$postData = ['UID' => 123, 'playlist_mode' => 'private', 'playlist_name' => 'Test Playlist'];
		$saveData = ['UID' => 123, 'playlist_mode' => 'private', 'playlist_name' => 'Test Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('insert')
			->with($saveData)
			->willThrowException(new \Exception('Database error'));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Database error');

		$this->service->createNew($postData);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testToggleShuffleTurnsOnWhenInitiallyOff(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$playlist = ['playlist_id' => $playlistId, 'shuffle' => 0];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with($playlistId, ['shuffle' => 1])
			->willReturn(1);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with(['playlist_id' => $playlistId, 'shuffle' => 1])
			->willReturn($this->playlistMetricsCalculatorMock);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getMetricsForFrontend')
			->willReturn(['metric' => 'value']);

		$result = $this->service->toggleShuffle($playlistId);

		static::assertEquals(['affected' => 1, 'playlist_metrics' => ['metric' => 'value']], $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testToggleShuffleTurnsOffWhenInitiallyOn(): void
	{
		$this->service->setUID(1);
		$playlistId = 456;
		$playlist = ['playlist_id' => $playlistId, 'shuffle' => 1];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with($playlistId, ['shuffle' => 0])
			->willReturn(1);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with(['playlist_id' => $playlistId, 'shuffle' => 0])
			->willReturn($this->playlistMetricsCalculatorMock);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getMetricsForFrontend')
			->willReturn(['metric' => 'value']);

		$result = $this->service->toggleShuffle($playlistId);

		static::assertEquals(['affected' => 1, 'playlist_metrics' => ['metric' => 'value']], $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testToggleShuffleThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(1);
		$playlistId = 789;
		$playlist = ['playlist_id' => $playlistId, 'shuffle' => 0];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error loading playlist: Is not editable');

		$this->service->toggleShuffle($playlistId);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testShufflePickingSuccessfullyUpdatesData(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$shufflePicking = 2;
		$playlist = ['playlist_id' => $playlistId, 'shuffle_picking' => 0];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with($playlistId, ['shuffle_picking' => $shufflePicking])
			->willReturn(1);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromPlaylistData')
			->with(array_merge($playlist, ['shuffle_picking' => $shufflePicking]))
			->willReturn($this->playlistMetricsCalculatorMock);

		$this->playlistMetricsCalculatorMock->expects($this->once())->method('getMetricsForFrontend')
			->willReturn(['metric' => 'value']);

		$result = $this->service->shufflePicking($playlistId, $shufflePicking);

		static::assertEquals(['affected' => 1, 'playlist_metrics' => ['metric' => 'value']], $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testShufflePickingThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$shufflePicking = 2;
		$playlist = ['playlist_id' => $playlistId, 'shuffle_picking' => 0];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error loading playlist: Is not editable');

		$this->service->shufflePicking($playlistId, $shufflePicking);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testShufflePickingThrowsExceptionWhenPlaylistNotFound(): void
	{
		$this->service->setUID(1);
		$playlistId = 999;
		$shufflePicking = 2;

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn([]);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Error loading playlist. Playlist with Id: 999 not found');

		$this->service->shufflePicking($playlistId, $shufflePicking);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testUpdateThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(1);
		$postData = ['playlist_id' => 1, 'playlist_name' => 'Updated Playlist'];
		$playlist = ['playlist_id' => 1, 'playlist_name' => 'Original Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with(1)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(false);

		$this->expectException(ModuleException::class);

		$this->service->updateSecure($postData);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testUpdate(): void
	{
		$this->service->setUID(2);
		$postData = ['UID' => 789, 'playlist_id' => 1, 'playlist_name' => 'Updated Playlist'];
		$playlist = ['playlist_id' => 1, 'playlist_name' => 'Original Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with(1)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(2, $playlist)
			->willReturn(true);

		$saveData = ['UID' => 789, 'playlist_name' => 'Updated Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with(1, $saveData);

		$this->service->updateSecure($postData);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdateExport(): void
	{
		$playlistId = 1;
		$saveData = ['some' => 'save', 'stuff' => 'man'];
		$this->playlistsRepositoryMock->expects($this->once())->method('updateExport')
			->with($playlistId, $saveData);

		$this->service->updateExport($playlistId, $saveData);
	}

	/**
	 */
	#[Group('units')]
	public function testDeleteSucceedsWhenPlaylistIsEditable(): void
	{
		$this->service->setUID(123);
		$playlistId = 1;
		$playlist = ['playlist_id' => $playlistId, 'playlist_name' => 'Deletable Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(123, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('delete')
			->with($playlistId)
			->willReturn(1);

		$result = $this->service->delete($playlistId);

		static::assertEquals(1, $result);
	}

	/**
	 */
	#[Group('units')]
	public function testDeleteThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(456);
		$playlistId = 2;
		$playlist = ['playlist_id' => $playlistId, 'playlist_name' => 'Non-editable Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(456, $playlist)
			->willReturn(false);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error delete playlist: Playlist is not editable.');

		$this->assertEmpty($this->service->delete($playlistId));
	}


	#[Group('units')]
	public function testLoadPlaylistForMultizoneReturnsDeserializedDataWhenEditable(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$playlist = ['playlist_id' => $playlistId, 'layout' => serialize(['zone1', 'zone2'])];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEquals(['zone1', 'zone2'], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForMultizoneThrowsExceptionWhenPlaylistNotFound(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn([]);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('No playlist with Id: 123 found.');

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForMultizoneReturnsEmptyArrayWhenNotEditable(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$playlist = ['playlist_id' => $playlistId];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(false);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Playlist is not editable.');

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForMultizoneReturnsEmptyArrayOnRepositoryException(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willThrowException(new \Exception('Repository error'));

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Repository error');

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForMultizoneForNewPlaylists(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$playlist = ['playlist_id' => $playlistId, 'layout' => ''];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testLoadPlaylistForMultizoneFails(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$playlist = ['playlist_id' => $playlistId, 'playlist_mode' => PlaylistMode::MULTIZONE->value, 'layout' => []];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$result = $this->service->loadPlaylistForMultizone($playlistId);

		static::assertEmpty($result);
	}

	#[Group('units')]
	public function testLoadNameByIdSuccessfullyReturnsPlaylistData(): void
	{
		$playlistId = 456;
		$playlist = ['playlist_id' => $playlistId, 'playlist_name' => 'Test Playlist'];

		$this->service->setUID(1);
		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$result = $this->service->loadNameById($playlistId);

		static::assertEquals(['playlist_id' => $playlistId, 'name' => 'Test Playlist'], $result);
	}

	#[Group('units')]
	public function testLoadNameByIdThrowsExceptionWhenPlaylistNotFound(): void
	{
		$playlistId = 789;

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willReturn([]);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Error loading playlist. Playlist with Id: 789 not found');

		$result = $this->service->loadNameById($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadNameByIdReturnsEmptyArrayOnRepositoryException(): void
	{
		$playlistId = 123;

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstBy')
			->with(['playlist_id' => $playlistId])
			->willThrowException(new \Exception('Repository error'));

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Repository error');

		$result = $this->service->loadNameById($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testSaveZonesSuccessfullyUpdatesData(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$zones = [
			'zone1' => ['zone_playlist_id' => 2, 'name' => 'Schulz'],
			'zone2' => ['zone_playlist_id' => 3],
			'zone3' => []
		];
		$playlist = ['playlist_id' => $playlistId, 'multizone' => serialize($zones)];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with($playlistId, ['layout' => serialize($zones)])
			->willReturn(1);

		$result = $this->service->saveZones($playlistId, $zones);

		static::assertEquals(1, $result);
	}

	#[Group('units')]
	public function testSaveZonesThrowsExceptionWhenPlaylistNotFound(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$zones = [
			'zone1' => ['zone_playlist_id' => 2, 'name' => 'Schulz'],
			'zone2' => ['zone_playlist_id' => 3],
			'zone3' => []
		];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn([]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('No playlist with Id: 123 found.');

		$result = $this->service->saveZones($playlistId, $zones);

		static::assertEquals(0, $result);
	}

	#[Group('units')]
	public function testSaveZonesThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$zones = [
			'zone1' => ['zone_playlist_id' => 2, 'name' => 'Schulz'],
			'zone2' => ['zone_playlist_id' => 3],
			'zone3' => []
		];
		$playlist = ['playlist_id' => $playlistId];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(false);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Playlist is not editable.');
		$this->service->setUID(1);
		$result = $this->service->saveZones($playlistId, $zones);

		static::assertEquals(0, $result);
	}

	#[Group('units')]
	public function testSaveZonesReturnsZeroOnRepositoryException(): void
	{
		$this->service->setUID(1);
		$playlistId = 123;
		$zones = [
			'zone1' => ['zone_playlist_id' => 2, 'name' => 'Schulz'],
			'zone2' => ['zone_playlist_id' => 3],
			'zone3' => []
		];
		$playlist = ['playlist_id' => $playlistId];

		$this->playlistsRepositoryMock->expects($this->once())->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())->method('isPlaylistEditable')
			->with(1, $playlist)
			->willReturn(true);

		$this->playlistsRepositoryMock->expects($this->once())->method('update')
			->with($playlistId, ['layout' => serialize($zones)])
			->willThrowException(new \Exception('Repository error'));

		$this->loggerMock->expects($this->once())->method('error')
			->with('Repository error');

		$result = $this->service->saveZones($playlistId, $zones);

		static::assertEquals(0, $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForEditReturnsPlaylistDataSuccessfully(): void
	{
		$this->service->setUID(1);
		$playlistId = 999;
		$playlist_first = ['playlist_id' => $playlistId, 'playlist_name' => 'Sample Playlist', 'layout' => null];
		$playlist_final = ['playlist_id' => $playlistId, 'playlist_name' => 'Sample Playlist', 'layout' => []];

		$this->playlistsRepositoryMock->expects($this->once())
			->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist_first);

		$this->aclValidatorMock->expects($this->once())
			->method('isPlaylistEditable')
			->with(1, $playlist_first)
			->willReturn(true);

		$result = $this->service->loadPlaylistForEdit($playlistId);

		static::assertEquals($playlist_final, $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForEditThrowsExceptionWhenPlaylistNotFound(): void
	{
		$this->service->setUID(1);
		$playlistId = 888;

		$this->playlistsRepositoryMock->expects($this->once())
			->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn([]);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('No playlist with Id: 888 found.');

		$result = $this->service->loadPlaylistForEdit($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForEditThrowsExceptionWhenPlaylistNotEditable(): void
	{
		$this->service->setUID(2);
		$playlistId = 777;
		$playlist = ['playlist_id' => $playlistId, 'playlist_name' => 'Non-Editable Playlist'];

		$this->playlistsRepositoryMock->expects($this->once())
			->method('findFirstWithUserName')
			->with($playlistId)
			->willReturn($playlist);

		$this->aclValidatorMock->expects($this->once())
			->method('isPlaylistEditable')
			->with(2, $playlist)
			->willReturn(false);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Playlist is not editable.');

		$result = $this->service->loadPlaylistForEdit($playlistId);

		static::assertEquals([], $result);
	}

	#[Group('units')]
	public function testLoadPlaylistForEditReturnsEmptyArrayOnRepositoryException(): void
	{
		$this->service->setUID(3);
		$playlistId = 666;

		$this->playlistsRepositoryMock->expects($this->once())
			->method('findFirstWithUserName')
			->with($playlistId)
			->willThrowException(new \Exception('Repository error'));

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Repository error');

		$result = $this->service->loadPlaylistForEdit($playlistId);

		static::assertEquals([], $result);
	}
}
