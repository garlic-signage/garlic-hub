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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerDataAssemblerTest extends TestCase
{
	private UserAgentHandler&MockObject $userAgentHandlerMock;
	private PlayerIndexRepository&MockObject $playerRepositoryMock;
	private Config&MockObject $configMock;
	private PlayerEntityFactory&MockObject $playerEntityFactoryMock;
	private PlayerDataAssembler $assembler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userAgentHandlerMock    = $this->createMock(UserAgentHandler::class);
		$this->playerRepositoryMock    = $this->createMock(PlayerIndexRepository::class);
		$this->configMock              = $this->createMock(Config::class);
		$this->playerEntityFactoryMock = $this->createMock(PlayerEntityFactory::class);

		$this->assembler = new PlayerDataAssembler(
			$this->userAgentHandlerMock,
			$this->playerRepositoryMock,
			$this->configMock,
			$this->playerEntityFactoryMock
		);
	}

	#[Group('units')]
	public function testParseUserAgentReturnsFalseForUnknownModel(): void
	{
		$userAgent = 'TestUserAgent';
		$this->userAgentHandlerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent);

		$this->userAgentHandlerMock->expects($this->once())->method('getModel')
			->willReturn(PlayerModel::UNKNOWN);

		$result = $this->assembler->parseUserAgent($userAgent);
		static::assertFalse($result);
	}

	#[Group('units')]
	public function testParseUserAgentReturnsTrueForKnownModel(): void
	{
		$userAgent = 'TestUserAgent';
		$this->userAgentHandlerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent);

		$this->userAgentHandlerMock->expects($this->once())->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP1X0);

		$result = $this->assembler->parseUserAgent($userAgent);
		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testHandleLocalPlayerReturnsExistingPlayer(): void
	{
		$playerData = ['player_id' => 1, 'uuid' => 'valid-uuid', 'status' => PlayerStatus::RELEASED->value, 'is_intranet' => true];

		$this->playerRepositoryMock->expects($this->once())->method('findPlayerById')
			->with(1)
			->willReturn($playerData);
		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')
			->willReturn('valid-uuid');

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($playerData, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));

		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->handleLocalPlayer();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHandleLocalPlayerInsertsNewPlayerLocalhost(): void
	{
		$this->playerRepositoryMock->expects($this->once())->method('findPlayerById')
			->with(1)
			->willReturn([]);

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')
			->willReturn('u-u-i-d');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')
			->willReturn('PlayerName');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')
			->willReturn('firmware');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP1X0);

		$this->configMock->expects($this->once())->method('getEnv')
			->with('APP_CONTAINER')
			->willReturn('');

		$insertData =  [
			'player_id' => 1,
			'uuid'        => 'u-u-i-d',
			'player_name' => 'PlayerName',
			'firmware'    => 'firmware',
			'model'       => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID'         => 1,
			'status'      => PlayerStatus::RELEASED->value,
			'refresh'     => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id'  => 1,
			'commands'    => [],
			'reports'     => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => [],
			'is_intranet' => true,
			'api_endpoint' => 'http://localhost:8080/v2'
		];
		$result   = [
			'player_id'  => 1,
			'status' => PlayerStatus::RELEASED->value,
			'licence_id' => 1,
			'is_intranet' => true,
			'api_endpoint' => 'http://localhost:8080/v2'
			];

		$this->playerRepositoryMock->expects($this->once())->method('insertPlayer')
			->with($insertData)
			->willReturn(1);

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($result, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));

		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->handleLocalPlayer();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testHandleLocalPlayerInsertsNewPlayerDocker(): void
	{
		$this->playerRepositoryMock->expects($this->once())->method('findPlayerById')
			->with(1)
			->willReturn([]);

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')
			->willReturn('u-u-i-d');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')
			->willReturn('PlayerName');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')
			->willReturn('firmware');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP1X0);

		$this->configMock->expects($this->once())->method('getEnv')
			->with('APP_CONTAINER')
			->willReturn(Config::APP_CONTAINER_DOCKER);

		$insertData =  [
			'player_id' => 1,
			'uuid'        => 'u-u-i-d',
			'player_name' => 'PlayerName',
			'firmware'    => 'firmware',
			'model'       => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID'         => 1,
			'status'      => PlayerStatus::RELEASED->value,
			'refresh'     => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id'  => 1,
			'commands'    => [],
			'reports'     => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => [],
			'is_intranet' => true,
			'api_endpoint' => 'http://host.docker.internal:8080/v2'
		];
		$result   = [
			'player_id'  => 1,
			'status' => PlayerStatus::RELEASED->value,
			'licence_id' => 1,
			'is_intranet' => true,
			'api_endpoint' => 'http://host.docker.internal:8080/v2'
		];

		$this->playerRepositoryMock->expects($this->once())->method('insertPlayer')
			->with($insertData)
			->willReturn(1);

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($result, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));

		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->handleLocalPlayer();
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testHandleLocalPlayerThrowsExceptionForFailedInsertion(): void
	{
		$this->playerRepositoryMock->expects($this->once())->method('findPlayerById')
			->with(1)
			->willReturn([]);

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')
			->willReturn('u-u-i-d');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')
			->willReturn('PlayerName');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')
			->willReturn('firmware');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')
			->willReturn(PlayerModel::IADEA_XMP1X0);
		$insertData =  [
			'player_id' => 1,
			'api_endpoint' => 'http://localhost:8080/v2',
			'uuid'        => 'u-u-i-d',
			'player_name' => 'PlayerName',
			'firmware'    => 'firmware',
			'model'       => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID'         => 1,
			'status'      => PlayerStatus::RELEASED->value,
			'refresh'     => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id'  => 1,
			'commands'    => [],
			'reports'     => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => [],
			'is_intranet' => true,
		];

		$this->playerRepositoryMock->expects($this->once())
			->method('insertPlayer')
			->with($insertData)
			->willReturn(0);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Failed to insert local player');

		$this->playerEntityFactoryMock->expects($this->never())->method('create');
		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->handleLocalPlayer();
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testHandleLocalPlayerThrowsExceptionForInvalidUuid(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Wrong Uuid for local player');

		$playerData = ['player_id' => 1, 'uuid' => 'invalid-uuid', 'status' => PlayerStatus::RELEASED->value];

		$this->playerRepositoryMock->expects($this->once())->method('findPlayerById')
			->with(1)
			->willReturn($playerData);

		$this->userAgentHandlerMock->expects($this->exactly(2))->method('getUuid')
			->willReturn('valid-uuid');

		$this->assembler->handleLocalPlayer();
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertNewPlayerInsertsSuccessfully(): void
	{
		$ownerId = 1;
		$saveData = [
			'uuid' => 'test-uuid',
			'player_name' => 'Test Player',
			'firmware' => '1.0.0',
			'model' => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID' => $ownerId,
			'status' => PlayerStatus::UNRELEASED->value,
			'refresh' => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id' => 0,
			'commands' => [],
			'reports' => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => []
		];

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')->willReturn('test-uuid');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')->willReturn('Test Player');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')->willReturn('1.0.0');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')->willReturn(PlayerModel::IADEA_XMP1X0);

		$this->playerRepositoryMock->expects($this->once())->method('insertPlayer')->with($saveData)->willReturn(1);

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($saveData, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));
		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->insertNewPlayer($ownerId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertNewPlayerThrowsExceptionForFailedInsertion(): void
	{
		$ownerId = 1;
		$saveData = [
			'uuid' => 'test-uuid',
			'player_name' => 'Test Player',
			'firmware' => '1.0.0',
			'model' => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID' => $ownerId,
			'status' => PlayerStatus::UNRELEASED->value,
			'refresh' => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id' => 0,
			'commands' => [],
			'reports' => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => []
		];

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')->willReturn('test-uuid');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')->willReturn('Test Player');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')->willReturn('1.0.0');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')->willReturn(PlayerModel::IADEA_XMP1X0);

		$this->playerRepositoryMock->expects($this->once())->method('insertPlayer')->with($saveData)->willReturn(0);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Failed to insert local player');

		$this->playerEntityFactoryMock->expects($this->never())->method('create');
		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->insertNewPlayer($ownerId);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertNewPlayerWithEdgeEdition(): void
	{
		$ownerId = 1;
		$saveData = [
			'uuid' => 'test-uuid',
			'player_name' => 'Test Player',
			'firmware' => '1.0.0',
			'model' => PlayerModel::IADEA_XMP1X0->value,
			'playlist_id' => 0,
			'UID' => $ownerId,
			'status' => PlayerStatus::RELEASED->value,
			'refresh' => PlayerEntity::DEFAULT_PLAYER_REFRESH,
			'licence_id' => 1,
			'commands' => [],
			'reports' => [],
			'location_data' => [],
			'location_longitude' => '',
			'location_latitude' => '',
			'categories' => [],
			'properties' => [],
			'remote_administration' => [],
			'screen_times' => []
		];

		$this->configMock->expects($this->once())->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')->willReturn('test-uuid');
		$this->userAgentHandlerMock->expects($this->once())->method('getName')->willReturn('Test Player');
		$this->userAgentHandlerMock->expects($this->once())->method('getFirmware')->willReturn('1.0.0');
		$this->userAgentHandlerMock->expects($this->once())->method('getModel')->willReturn(PlayerModel::IADEA_XMP1X0);

		$this->playerRepositoryMock->expects($this->once())->method('insertPlayer')->with($saveData)->willReturn(1);

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($saveData, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));

		$this->assembler->insertNewPlayer($ownerId);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchDatabaseReturnsPlayerEntity(): void
	{
		$this->userAgentHandlerMock->expects($this->once())->method('getUuid')->willReturn('test-uuid');
		$playerData = ['player_id' => 1, 'uuid' => 'test-uuid', 'status' => PlayerStatus::RELEASED->value];

		$this->playerRepositoryMock->expects($this->once())->method('findPlayerByUuid')
			->with('test-uuid')
			->willReturn($playerData);

		$this->playerEntityFactoryMock->expects($this->once())->method('create')
			->with($playerData, $this->userAgentHandlerMock)
			->willReturn($this->createMock(PlayerEntity::class));

		$serverData = ['REMOTE_ADDR' => '192.168.10.9'];
		$this->assembler->setServerData($serverData);

		$this->assembler->fetchDatabase();
	}
}
