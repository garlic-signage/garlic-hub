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

namespace Tests\Integration\Db\Modules\Player\Repositories;

use App\Modules\Player\Repositories\PlayerIndexRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Db\RepositoryTestCase;

#[Group('db')]
class PlayerIndexRepositoryTest extends RepositoryTestCase
{
	private PlayerIndexRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new PlayerIndexRepository($this->connection);
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlayerByIdReturnsPlayerWithPlaylistAndExpandedFields(): void
	{
		$player = $this->repository->findPlayerById(1);

		static::assertSame('Eingang', $player['player_name']);
		static::assertSame(['reboot', 'update'], $player['commands']);
		static::assertSame(['screenshot'], $player['reports']);
		static::assertSame(['city' => 'Berlin'], $player['location_data']);
		static::assertSame(['width' => 1920, 'height' => 1080], $player['properties']);
		static::assertSame([], $player['categories']);
		static::assertSame('master', $player['playlist_mode']);
		static::assertSame('Lobby', $player['playlist_name']);
		static::assertSame(['zone' => '1'], $player['layout']);
		static::assertSame('2025-03-01 10:00:00', $player['last_update_playlist']);
		static::assertSame('2025-03-01 09:00:00', $player['updated_player']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlayerByIdWithoutPlaylist(): void
	{
		$player = $this->repository->findPlayerById(4);

		static::assertSame('Ohne Liste', $player['player_name']);
		static::assertNull($player['playlist_mode']);
		static::assertNull($player['playlist_name']);
		static::assertSame([], $player['layout']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlayerByIdUnknownReturnsEmptyArray(): void
	{
		static::assertSame([], $this->repository->findPlayerById(999));
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlayerByUuid(): void
	{
		$player = $this->repository->findPlayerByUuid('33333333-3333-4333-8333-333333333333');

		static::assertSame(3, (int) $player['player_id']);
		static::assertSame('Kantine', $player['playlist_name']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlayerByUuidUnknownReturnsEmptyArray(): void
	{
		static::assertSame([], $this->repository->findPlayerByUuid('00000000-0000-4000-8000-000000000000'));
	}

	/**
	 * @throws Exception
	 */
	public function testInsertPlayerCanBeReadBack(): void
	{
		$id = $this->repository->insertPlayer([
			'uuid'                  => '55555555-5555-4555-8555-555555555555',
			'UID'                   => 3,
			'player_name'           => 'Neu',
			'commands'              => ['a', 'b'],
			'reports'               => [],
			'location_data'         => [],
			'properties'            => ['volume' => 5],
			'remote_administration' => [],
			'categories'            => [],
			'screen_times'          => [],
		]);

		static::assertSame(5, $id);

		$player = $this->repository->findPlayerById($id);
		static::assertSame('Neu', $player['player_name']);
		static::assertSame(['a', 'b'], $player['commands']);
		static::assertSame(['volume' => 5], $player['properties']);
		// defaults of the schema
		static::assertSame(1, (int) $player['status']);
		static::assertSame(0, (int) $player['playlist_id']);
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateLastAccessTouchesOnlyThePlayer(): void
	{
		$this->repository->updateLastAccess(2);

		$accessed = $this->connection->fetchOne('SELECT last_access FROM player WHERE player_id = 2');
		$other    = $this->connection->fetchOne('SELECT last_access FROM player WHERE player_id = 1');

		static::assertSame('2000-01-01 00:00:00', $other);
		static::assertEqualsWithDelta(time(), strtotime($accessed . ' UTC'), 5);
	}
}
