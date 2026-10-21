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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerService;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PlayerServiceTest extends TestCase
{
	private PlayerRepository&MockObject $playerRepositoryMock;
	private PlaylistsService&Stub $playlistServiceStub;
	private AclValidator&MockObject $playerValidatorMock;
	private UserSession&Stub $userSessionStub;
	private PlayerService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerRepositoryMock = $this->createMock(PlayerRepository::class);
		$this->playlistServiceStub  = $this->createStub(PlaylistsService::class);
		$this->playerValidatorMock  = $this->createMock(AclValidator::class);

		$this->userSessionStub = $this->createStub(UserSession::class);
		$this->service = new PlayerService($this->playerRepositoryMock, $this->playlistServiceStub, $this->playerValidatorMock, $this->userSessionStub);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardReturnsValidData(): void
	{
		$this->playerValidatorMock->expects($this->never())->method($this->anything());
		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('findAllForDashboard')
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
		$this->playerValidatorMock->expects($this->never())->method($this->anything());
		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('findAllForDashboard')
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
		$this->playerValidatorMock->expects($this->never())->method($this->anything());
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
		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->expects($this->atLeastOnce())->method('isPlayerEditable')
			->willReturn(true);

		$this->playlistServiceStub->method('loadPureById')
			->willReturn(['playlist_mode' => PlaylistMode::MASTER->value, 'playlist_name' => 'Master Playlist']);

		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('update')
			->willReturn(1);

		$result = $this->service->replaceMasterPlaylist(1, 10);

		static::assertSame(['affected' => 1, 'playlist_name' => 'Master Playlist', 'is_intranet' => 0], $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistThrowsWithoutMasterPlaylist(): void
	{
		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->expects($this->atLeastOnce())->method('isPlayerEditable')
			->willReturn(true);

		$this->playlistServiceStub->method('loadPureById')
			->willReturn(['playlist_mode' => PlaylistMode::CHANNEL->value, 'playlist_name' => 'Channel Playlist']);


		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('playlist_not_master {"playlist_name":"Channel Playlist"}');
		$this->service->replaceMasterPlaylist(1, 10);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistWithoutPlaylistId(): void
	{
		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('findFirstById')
			->willReturn(['id' => 1, 'name' => 'Player 1']);

		$this->playerValidatorMock->expects($this->atLeastOnce())->method('isPlayerEditable')
			->willReturn(true);

		$this->playerRepositoryMock->expects($this->atLeastOnce())->method('update')
			->willReturn(1);

		$result = $this->service->replaceMasterPlaylist(1, 0);

		static::assertSame(['affected' => 1, 'playlist_name' => '', 'is_intranet' => 0], $result);
	}

	#[Group('units')]
	public function testReplaceMasterPlaylistThrowsIfPlayerNotFound(): void
	{
		$this->userSessionStub->method('getUID')->willReturn(1);
		$this->playerRepositoryMock->method('findFirstById')
			->willReturn([]);

		$this->playerValidatorMock->expects($this->never())->method('isPlayerEditable');

		$this->playerRepositoryMock->expects($this->never())->method('update');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('player_not_found');
		$this->service->replaceMasterPlaylist(1, 0);
	}

	/**
	 * @throws CoreException
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

		$this->userSessionStub->method('getUID')->willReturn(12);
		$result = $this->service->fetchAclCheckedPlayerData($playerId);

		static::assertSame($playerData, $result);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchAclCheckedPlayerDataThrowsIfPlayerNotFound(): void
	{
		$this->playerValidatorMock->expects($this->never())->method($this->anything());
		$playerId = 2;

		$this->playerRepositoryMock->method('findFirstById')
			->with($playerId)
			->willReturn([]);


		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('player_not_found {"player_id":2}');
		$this->service->fetchAclCheckedPlayerData($playerId);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchAclCheckedPlayerDataThrowsIfPlayerNotEditable(): void
	{
		$playerId = 3;
		$playerData = ['UID' => 2, 'company_id' => 102, 'player_id' => $playerId, 'name' => 'Player Demo'];

		$this->playerRepositoryMock->method('findFirstById')
			->with($playerId)
			->willReturn($playerData);

		$this->playerValidatorMock->method('isPlayerEditable')
			->with(12, $playerData)
			->willReturn(false);


		$this->userSessionStub->method('getUID')->willReturn(12);
		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('player_not_editable {"player_id":3}');
		$this->service->fetchAclCheckedPlayerData($playerId);
	}
}
