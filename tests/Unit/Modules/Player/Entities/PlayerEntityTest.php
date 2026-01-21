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

namespace Tests\Unit\Modules\Player\Entities;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use DateTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerEntityTest extends TestCase
{
	private Config&MockObject $configMock;
	private UserAgentHandler&MockObject $userAgentHandlerMock;
	private PlayerEntity $playerEntity;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock = $this->createMock(Config::class);
		$this->userAgentHandlerMock = $this->createMock(UserAgentHandler::class);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testConstructorWithValidData(): void
	{
		$screenTimes = [
			'screen1' => ['monday' => 'some time'],
			'screen2' => ['tuesday' => 'some time']
		];
		$data = [
			'player_id' => 10,
			'playlist_id' => 20,
			'UID' => 12345,
			'last_access' => '2023-10-10 12:00:00',
			'last_update' => '2023-10-15 12:00:00',
			'last_update_playlist' => '2023-10-18 08:00:00',
			'duration' => 3600,
			'status' => 1,
			'refresh' => 120,
			'licence_id' => 56789,
			'playlist_name' => 'Example Playlist',
			'playlist_mode' => 'loop',
			'multizone' => ['zone1' => [], 'zone2' => []],
			'location_data' => ['city' => 'New York'],
			'location_longitude' => '-74.0060',
			'location_latitude' => '40.7128',
			'categories' => ['category1' => [], 'category2' => []],
			'properties' => ['width' => 1920, 'height' => 1080],
			'remote_administration' => ['enabled' => true],
			'screen_times' => $screenTimes
		];

		$this->userAgentHandlerMock->method('getModel')->willReturn(PlayerModel::COMPATIBLE);
		$this->userAgentHandlerMock->method('getUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
		$this->userAgentHandlerMock->method('getFirmware')->willReturn('1.0.0');
		$this->userAgentHandlerMock->method('getName')->willReturn('Player One');

		$this->playerEntity = new PlayerEntity($this->configMock, $this->userAgentHandlerMock, $data);

		static::assertFalse($this->playerEntity->isIntranet());
		static::assertEmpty($this->playerEntity->getApiEndpoint());
		static::assertSame(10, $this->playerEntity->getPlayerId());
		static::assertSame(20, $this->playerEntity->getPlaylistId());
		static::assertSame(12345, $this->playerEntity->getUID());
		static::assertEquals(new DateTime('2023-10-10 12:00:00'), $this->playerEntity->getLastAccess());
		static::assertEquals(new DateTime('2023-10-15 12:00:00'), $this->playerEntity->getLastUpdate());
		static::assertEquals(new DateTime('2023-10-18 08:00:00'), $this->playerEntity->getLastUpdatePlaylist());
		static::assertSame(3600, $this->playerEntity->getDuration());
		static::assertSame(1, $this->playerEntity->getStatus());
		static::assertSame(120, $this->playerEntity->getRefresh());
		static::assertSame(56789, $this->playerEntity->getLicenceId());
		static::assertSame(PlayerModel::COMPATIBLE, $this->playerEntity->getModel());
		static::assertSame('123e4567-e89b-12d3-a456-426614174000', $this->playerEntity->getUuid());
		static::assertSame('Example Playlist', $this->playerEntity->getPlaylistName());
		static::assertSame('loop', $this->playerEntity->getPlaylistMode());
		static::assertEmpty($this->playerEntity->getCommands());
		static::assertEmpty($this->playerEntity->getReports());
		static::assertSame('1.0.0', $this->playerEntity->getFirmwareVersion());
		static::assertSame('Player One', $this->playerEntity->getPlayerName());
		static::assertSame(['zone1' => [], 'zone2' => []], $this->playerEntity->getZones());
		static::assertSame(['city' => 'New York'], $this->playerEntity->getLocationData());
		static::assertSame('-74.0060', $this->playerEntity->getLocationLongitude());
		static::assertSame('40.7128', $this->playerEntity->getLocationLatitude());
		static::assertSame(['category1' => [], 'category2' => []], $this->playerEntity->getCategories());
		static::assertSame(['width' => 1920, 'height' => 1080], $this->playerEntity->getProperties());
		static::assertSame(['enabled' => true], $this->playerEntity->getRemoteAdministration());
		static::assertSame($screenTimes, $this->playerEntity->getScreenTimes());
		$this->configMock->expects($this->exactly(2))->method('getConfigValue')
			->willReturnMap([
				['report_server', 'player', 'https://reports.lan'],
				['index_server_url', 'player', 'https://index.lan']
			]);
		static::assertSame('https://reports.lan', $this->playerEntity->getReportServer());
		static::assertSame('https://index.lan/123e4567-e89b-12d3-a456-426614174000', $this->playerEntity->getIndexPath());
	}

	#[Group('units')]
	public function testConstructorWithDefaultData(): void
	{
		$this->userAgentHandlerMock->method('getModel')->willReturn(PlayerModel::COMPATIBLE);
		$this->userAgentHandlerMock->method('getUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
		$this->userAgentHandlerMock->method('getFirmware')->willReturn('1.0.0');
		$this->userAgentHandlerMock->method('getName')->willReturn('Player One');

		$this->playerEntity = new PlayerEntity($this->configMock, $this->userAgentHandlerMock, []);

		static::assertSame(1, $this->playerEntity->getPlayerId());
		static::assertSame(0, $this->playerEntity->getPlaylistId());
		static::assertSame(1, $this->playerEntity->getUID());
		static::assertEquals(new DateTime('2025-01-01 00:00:00'), $this->playerEntity->getLastAccess());
		static::assertEquals(new DateTime('2025-01-01 00:00:00'), $this->playerEntity->getLastUpdate());
		static::assertEquals(new DateTime('2025-01-01 00:00:00'), $this->playerEntity->getLastUpdatePlaylist());
		static::assertSame(0, $this->playerEntity->getDuration());
		static::assertSame(0, $this->playerEntity->getStatus());
		static::assertSame(PlayerEntity::DEFAULT_PLAYER_REFRESH, $this->playerEntity->getRefresh());
		static::assertSame(0, $this->playerEntity->getLicenceId());
		static::assertSame('', $this->playerEntity->getPlaylistName());
		static::assertSame('', $this->playerEntity->getPlaylistMode());
		static::assertSame([], $this->playerEntity->getZones());
		static::assertSame([], $this->playerEntity->getLocationData());
		static::assertSame('', $this->playerEntity->getLocationLongitude());
		static::assertSame('', $this->playerEntity->getLocationLatitude());
		static::assertSame([], $this->playerEntity->getCategories());
		static::assertSame(['width' => 1920, 'height' => 1080], $this->playerEntity->getProperties());
		static::assertSame([], $this->playerEntity->getRemoteAdministration());
		static::assertSame([], $this->playerEntity->getScreenTimes());
	}
}
