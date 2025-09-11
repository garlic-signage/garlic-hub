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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerService;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlayerServiceTest extends TestCase
{
	private PlayerRepository&MockObject $playerRepositoryMock;
	private PlaylistsService&MockObject $playlistServiceMock;
	private AclValidator&MockObject $playerValidatorMock;
	private LoggerInterface&MockObject $loggerMock;
	private PlayerService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerRepositoryMock = $this->createMock(PlayerRepository::class);
		$this->playlistServiceMock  = $this->createMock(PlaylistsService::class);
		$this->playerValidatorMock  = $this->createMock(AclValidator::class);
		$this->loggerMock           = $this->createMock(LoggerInterface::class);

		$this->service = new PlayerService($this->playerRepositoryMock, $this->playlistServiceMock, $this->playerValidatorMock, $this->loggerMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardReturnsValidData(): void
	{
		$this->playerRepositoryMock->method('findAllForDashboard')
			->willReturn(['active' => 5, 'inactive' => 3, 'pending' => 2]);

		$result = $this->service->findAllForDashboard();

		static::assertSame(['active' => 5, 'inactive' => 3, 'pending' => 2], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardReturnsDefaultValuesOnEmptyData(): void
	{
		$this->playerRepositoryMock->method('findAllForDashboard')
			->willReturn([]);

		$result = $this->service->findAllForDashboard();

		static::assertSame(['active' => 0, 'inactive' => 0, 'pending' => 0], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdatePlayerSuccessfullyUpdatesData(): void
	{
		$playerId = 1;
		$saveData = ['api_endpoint' => 'http://example.com', 'is_intranet' => 1];

		$this->playerRepositoryMock->expects($this->once())->method('update')
			->with($playerId, $saveData)
			->willReturn(1);

		$result = $this->service->updatePlayer($playerId, $saveData);

		static::assertSame(1, $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylist(): void
	{
		$this->service->setUID(1);
		$this->playerRepositoryMock->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->method('isPlayerEditable')
			->willReturn(true);

		$this->playlistServiceMock->method('loadPureById')
			->willReturn(['playlist_mode' => PlaylistMode::MASTER->value, 'playlist_name' => 'Master Playlist']);

		$this->playerRepositoryMock->method('update')
			->willReturn(1);

		$result = $this->service->replaceMasterPlaylist(1, 10);

		static::assertSame(['affected' => 1, 'playlist_name' => 'Master Playlist', 'is_intranet' => 0], $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistWithInvalidMasterPlaylistMode(): void
	{
		$this->service->setUID(1);
		$this->playerRepositoryMock->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->method('isPlayerEditable')
			->willReturn(true);

		$this->playlistServiceMock->method('loadPureById')
			->willReturn(['playlist_mode' => PlaylistMode::CHANNEL->value, 'playlist_name' => 'Channel Playlist']);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Channel Playlist is not a master playlist');

		$result = $this->service->replaceMasterPlaylist(1, 10);

		static::assertSame([], $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistWithoutPlaylistId(): void
	{
		$this->service->setUID(1);
		$this->playerRepositoryMock->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->method('isPlayerEditable')
			->willReturn(true);

		$this->playerRepositoryMock->method('update')
			->willReturn(1);

		$result = $this->service->replaceMasterPlaylist(1, 0);

		static::assertSame(['affected' => 1, 'playlist_name' => '', 'is_intranet' => 0], $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistPlaylistsFails(): void
	{
		$this->service->setUID(1);
		$this->playerRepositoryMock->method('findFirstById')
			->willReturn([]);

		$this->playerValidatorMock->expects($this->never())->method('isPlayerEditable');

		$this->playerRepositoryMock->expects($this->never())->method('update');

		$result = $this->service->replaceMasterPlaylist(1, 0);

		static::assertEmpty($result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchAclCheckedPlayerDataSucceed(): void
	{
		$playerId = 1;
		$playerData = ['UID' => 1, 'company_id' => 101, 'player_id' => $playerId, 'name' => 'Player Name'];

		$this->playerRepositoryMock->method('findFirstById')
			->with($playerId)
			->willReturn($playerData);

		$this->playerValidatorMock->method('isPlayerEditable')
			->with(12, $playerData)
			->willReturn(true);

		$this->service->setUID(12);
		$result = $this->service->fetchAclCheckedPlayerData($playerId);

		static::assertSame($playerData, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchAclCheckedPlayerDataReturnsEmptyIfPlayerNotFound(): void
	{
		$playerId = 2;

		$this->playerRepositoryMock->method('findFirstById')
			->with($playerId)
			->willReturn([]);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Error loading player: ' . $playerId . ' is not found.');

		$result = $this->service->fetchAclCheckedPlayerData($playerId);

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchAclCheckedPlayerDataReturnsEmptyIfPlayerNotEditable(): void
	{
		$playerId = 3;
		$playerData = ['UID' => 2, 'company_id' => 102, 'player_id' => $playerId, 'name' => 'Player Demo'];

		$this->playerRepositoryMock->method('findFirstById')
			->with($playerId)
			->willReturn($playerData);

		$this->playerValidatorMock->method('isPlayerEditable')
			->with(12, $playerData)
			->willReturn(false);

		$this->loggerMock->expects($this->once())
			->method('error')
			->with('Error loading player: ' . $playerId . ' is not editable for User ' . 12 . '.');

		$this->service->setUID(12);
		$result = $this->service->fetchAclCheckedPlayerData($playerId);

		static::assertSame([], $result);
	}
}
