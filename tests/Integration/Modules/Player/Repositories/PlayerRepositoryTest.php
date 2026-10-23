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

namespace Tests\Integration\Modules\Player\Repositories;

use App\Modules\Player\Repositories\PlayerRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class PlayerRepositoryTest extends RepositoryTestCase
{
	private PlayerRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new PlayerRepository($this->connection);
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllForDashboardCountsPlayersByLastAccess(): void
	{
		// refresh is 300s: active < 600s, pending < 1200s, inactive older
		$this->setLastAccess(1, 10);
		$this->setLastAccess(2, 700);
		$this->setLastAccess(3, 5000);
		$this->setLastAccess(4, 5000);

		$result = $this->repository->findAllForDashboard();

		static::assertSame(1, (int) $result['active']);
		static::assertSame(1, (int) $result['pending']);
		static::assertSame(2, (int) $result['inactive']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllForDashboardWithoutPlayers(): void
	{
		$this->connection->executeStatement('DELETE FROM player');

		$result = $this->repository->findAllForDashboard();

		static::assertSame(0, (int) $result['active']);
		static::assertSame(0, (int) $result['pending']);
		static::assertSame(0, (int) $result['inactive']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlaylistIdsByPlaylistIdsReturnsOneRowPerPlayer(): void
	{
		$result = $this->repository->findPlaylistIdsByPlaylistIds([1, 2]);

		static::assertSame([1, 1, 2], array_map(static fn(array $row): int => (int) $row['playlist_id'], $result));
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlaylistIdsByPlaylistIdsIgnoresUnusedPlaylists(): void
	{
		static::assertSame([], $this->repository->findPlaylistIdsByPlaylistIds([99]));
	}

	/**
	 * @throws Exception
	 */
	public function testFindPlaylistIdsByPlaylistIdsWithEmptyList(): void
	{
		static::assertSame([], $this->repository->findPlaylistIdsByPlaylistIds([]));
	}

	/**
	 * @throws Exception
	 */
	private function setLastAccess(int $playerId, int $secondsAgo): void
	{
		$this->connection->update('player', ['last_access' => gmdate('Y-m-d H:i:s', time() - $secondsAgo)], ['player_id' => $playerId]);
	}
}
