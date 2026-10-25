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

use App\Framework\Config\Config;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The entity holds what the database and the user agent of the player know. The user agent handler is a stub
 * (the user agent is the border), the configuration is the real class.
 */
class PlayerEntityTest extends TestCase
{
	private function userAgent(): UserAgentHandler
	{
		$handler = $this->createStub(UserAgentHandler::class);
		$handler->method('getModel')->willReturn(PlayerModel::GARLIC);
		$handler->method('getUuid')->willReturn('11111111-1111-4111-8111-111111111111');
		$handler->method('getFirmware')->willReturn('2.4.1');
		$handler->method('getName')->willReturn('Eingang');

		return $handler;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function entity(array $data): PlayerEntity
	{
		$config = new Config(new RecordingConfigLoader(['player' => ['report_server_url' => 'https://reports.example.com', 'index_server_url' => 'https://hub.example.com/smil-index']]));

		return new PlayerEntity($config, $this->userAgent(), $data);
	}

	#[Group('units')]
	public function testEveryValueOfARowIsAvailable(): void
	{
		$entity = $this->entity([
			'player_id' => 7, 'playlist_id' => 3, 'UID' => 2, 'last_access' => '2025-05-06 07:08:09', 'last_update' => '2025-04-01 10:00:00',
			'last_update_playlist' => '2025-03-02 11:00:00', 'duration' => 120, 'status' => 2, 'refresh' => 600, 'licence_id' => 9,
			'is_intranet' => true, 'api_endpoint' => 'http://10.0.0.7:8080/api', 'commands' => ['reboot'], 'reports' => ['played'],
			'playlist_name' => 'Lobby', 'playlist_mode' => 'master', 'layout' => ['zone' => '1'], 'location_data' => ['city' => 'Berlin'],
			'location_longitude' => '13.4', 'location_latitude' => '52.5', 'categories' => ['a'], 'properties' => ['width' => 1920],
			'remote_administration' => ['port' => 22], 'screen_times' => ['mo' => '8-18'],
		]);

		static::assertSame(7, $entity->getPlayerId());
		static::assertSame(3, $entity->getPlaylistId());
		static::assertSame(2, $entity->getUID());
		static::assertSame('2025-05-06 07:08:09', $entity->getLastAccess()->format('Y-m-d H:i:s'));
		static::assertSame('2025-04-01 10:00:00', $entity->getLastUpdate()->format('Y-m-d H:i:s'));
		static::assertSame('2025-03-02 11:00:00', $entity->getLastUpdatePlaylist()->format('Y-m-d H:i:s'));
		static::assertSame(120, $entity->getDuration());
		static::assertSame(2, $entity->getStatus());
		static::assertSame(600, $entity->getRefresh());
		static::assertSame(9, $entity->getLicenceId());
		static::assertTrue($entity->isIntranet());
		static::assertSame('http://10.0.0.7:8080/api', $entity->getApiEndpoint());
		static::assertSame(['reboot'], $entity->getCommands());
		static::assertSame(['played'], $entity->getReports());
		static::assertSame('Lobby', $entity->getPlaylistName());
		static::assertSame('master', $entity->getPlaylistMode());
		static::assertSame(['zone' => '1'], $entity->getLayout());
		static::assertSame(['city' => 'Berlin'], $entity->getLocationData());
		static::assertSame('13.4', $entity->getLocationLongitude());
		static::assertSame('52.5', $entity->getLocationLatitude());
		static::assertSame(['a'], $entity->getCategories());
		static::assertSame(['width' => 1920], $entity->getProperties());
		static::assertSame(['port' => 22], $entity->getRemoteAdministration());
		static::assertSame(['mo' => '8-18'], $entity->getScreenTimes());
	}

	#[Group('units')]
	public function testWhatThePlayerSaysAboutItselfComesFromTheUserAgent(): void
	{
		$entity = $this->entity(['uuid' => 'from-the-database', 'player_name' => 'from the database']);

		static::assertSame(PlayerModel::GARLIC, $entity->getModel());
		static::assertSame('11111111-1111-4111-8111-111111111111', $entity->getUuid());
		static::assertSame('2.4.1', $entity->getFirmwareVersion());
		static::assertSame('Eingang', $entity->getPlayerName());
	}

	#[Group('units')]
	public function testAnEmptyRowHasTheDefaults(): void
	{
		$entity = $this->entity([]);

		static::assertSame([1, 0, 1, 0, PlayerEntity::DEFAULT_PLAYER_REFRESH, 0, 0], [$entity->getPlayerId(), $entity->getPlaylistId(), $entity->getUID(), $entity->getStatus(), $entity->getRefresh(), $entity->getLicenceId(), $entity->getDuration()]);
		static::assertFalse($entity->isIntranet());
		static::assertSame(['', '', '', '', ''], [$entity->getApiEndpoint(), $entity->getPlaylistName(), $entity->getPlaylistMode(), $entity->getLocationLongitude(), $entity->getLocationLatitude()]);
		static::assertSame([[], [], [], [], [], [], [], []], [$entity->getCommands(), $entity->getReports(), $entity->getLayout(), $entity->getLocationData(), $entity->getCategories(), $entity->getProperties(), $entity->getRemoteAdministration(), $entity->getScreenTimes()]);
		foreach ([$entity->getLastAccess(), $entity->getLastUpdate(), $entity->getLastUpdatePlaylist()] as $date)
			static::assertSame('2025-01-01 00:00:00', $date->format('Y-m-d H:i:s'));
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function dates(): array
	{
		return [
			'not a date'         => ['yesterday'],
			'empty'              => [''],
			'database default'   => ['0000-00-00 00:00:00x'],
			'other format'       => ['06.05.2025 07:08'],
			'null'               => [null],
			'a number'           => [1746515289],
			'a list'             => [['2025-05-06 07:08:09']],
		];
	}

	/**
	 * A date which can not be read is the default date: the entity is complete and the getters work.
	 */
	#[Group('units')]
	#[DataProvider('dates')]
	public function testADateWhichCanNotBeReadIsTheDefaultDate(mixed $value): void
	{
		$entity = $this->entity(['last_access' => $value, 'last_update' => $value, 'last_update_playlist' => $value]);

		static::assertSame('2025-01-01 00:00:00', $entity->getLastAccess()->format('Y-m-d H:i:s'));
		static::assertSame('2025-01-01 00:00:00', $entity->getLastUpdate()->format('Y-m-d H:i:s'));
		static::assertSame('2025-01-01 00:00:00', $entity->getLastUpdatePlaylist()->format('Y-m-d H:i:s'));
	}

	#[Group('units')]
	public function testTheServersComeFromTheConfiguration(): void
	{
		$entity = $this->entity([]);

		static::assertSame('https://reports.example.com', $entity->getReportServer());
		static::assertSame('https://hub.example.com/smil-index/11111111-1111-4111-8111-111111111111', $entity->getIndexPath());
	}
}
