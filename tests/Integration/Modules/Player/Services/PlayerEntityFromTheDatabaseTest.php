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

namespace Tests\Integration\Modules\Player\Services;

use App\Framework\Config\Config;
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The entity of a player as the index creation builds it: from the row of the database and the user agent of the player.
 */
#[Group('db')]
class PlayerEntityFromTheDatabaseTest extends RepositoryTestCase
{
	private PlayerIndexRepository $repository;
	private PlayerEntityFactory $factory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
		$this->repository = new PlayerIndexRepository($this->connection);
		$this->factory    = new PlayerEntityFactory(new Config(new RecordingConfigLoader(['player' => ['report_server' => 'r', 'index_server_url' => 'https://hub/smil-index']])));
	}

	private function userAgent(string $uuid): UserAgentHandler
	{
		$handler = $this->createStub(UserAgentHandler::class);
		$handler->method('getModel')->willReturn(PlayerModel::GARLIC);
		$handler->method('getUuid')->willReturn($uuid);
		$handler->method('getFirmware')->willReturn('1.0');
		$handler->method('getName')->willReturn('Eingang');

		return $handler;
	}

	public function testARowBecomesAnEntityWithAllItsData(): void
	{
		$entity = $this->factory->create($this->repository->findPlayerById(1), $this->userAgent('11111111-1111-4111-8111-111111111111'));

		static::assertSame(1, $entity->getPlayerId());
		static::assertSame(1, $entity->getPlaylistId());
		static::assertSame(1, $entity->getStatus());
		static::assertSame(['reboot', 'update'], $entity->getCommands());
		static::assertSame(['screenshot'], $entity->getReports());
		static::assertSame(['city' => 'Berlin'], $entity->getLocationData());
		static::assertSame(['width' => 1920, 'height' => 1080], $entity->getProperties());
		static::assertSame('Lobby', $entity->getPlaylistName());
		static::assertSame('master', $entity->getPlaylistMode());
		static::assertSame(['zone' => '1'], $entity->getLayout());
		static::assertSame('2025-03-01 10:00:00', $entity->getLastUpdatePlaylist()->format('Y-m-d H:i:s'));
		static::assertSame('https://hub/smil-index/11111111-1111-4111-8111-111111111111', $entity->getIndexPath());
	}

	public function testAPlayerWithoutAPlaylistHasNoPlaylistData(): void
	{
		$entity = $this->factory->create($this->repository->findPlayerById(4), $this->userAgent('44444444-4444-4444-8444-444444444444'));

		static::assertSame(0, $entity->getPlaylistId());
		static::assertSame('', $entity->getPlaylistName());
		static::assertSame('', $entity->getPlaylistMode());
		static::assertSame([], $entity->getLayout());
		static::assertSame([], $entity->getCommands());
		static::assertSame([], $entity->getLocationData());
	}
}
