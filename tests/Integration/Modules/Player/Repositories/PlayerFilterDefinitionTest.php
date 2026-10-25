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

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterConditions;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The filters of the player list against the real schema. The filter by activity and by playlist belong to the
 * enterprise edition (the edge list has neither), but the definition has to work for them.
 * Players of player/players.sql: 1 and 2 play the playlist 1, 3 plays the playlist 2, 4 plays none; all have the refresh time 300.
 */
#[Group('db')]
class PlayerFilterDefinitionTest extends RepositoryTestCase
{
	private DatatableQuery $query;
	private PlayerFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
		$conditions      = new FilterConditions();
		$this->definition = new PlayerFilterDefinition($conditions, $this->connection->getDatabasePlatform());
		$this->query      = new DatatableQuery($this->connection, $conditions);
	}

	/**
	 * @param array<string,mixed> $fields
	 * @return list<int>
	 */
	private function idsFor(array $fields): array
	{
		$parameters = [];
		foreach ($fields as $key => $value)
			$parameters[$key] = ['value' => $value];

		return array_map('intval', array_column($this->query->findForModuleAdmin($this->definition, $parameters), 'player_id'));
	}

	private function lastAccessSecondsAgo(int $playerId, int $seconds): void
	{
		$this->connection->executeStatement('UPDATE player SET last_access = ? WHERE player_id = ?', [gmdate('Y-m-d H:i:s', time() - $seconds), $playerId]);
	}

	public function testTheDefinitionIsForThePlayerTable(): void
	{
		static::assertSame('player', $this->definition->table());
		static::assertSame(['user_main', 'playlists'], array_keys($this->definition->joins()));
		static::assertSame(['playlists'], array_keys($this->definition->userJoins()));
	}

	public function testPlayersAreActiveWhenTheyAskedWithinTwoRefreshTimes(): void
	{
		$this->lastAccessSecondsAgo(1, 10);
		$this->lastAccessSecondsAgo(2, 590);
		$this->lastAccessSecondsAgo(3, 610);
		$this->lastAccessSecondsAgo(4, 100_000);

		static::assertEqualsCanonicalizing([1, 2], $this->idsFor(['activity' => 'active']));
		static::assertEqualsCanonicalizing([3, 4], $this->idsFor(['activity' => 'inactive']));
		static::assertEqualsCanonicalizing([1, 2, 3, 4], $this->idsFor(['activity' => 'all']));
		static::assertEqualsCanonicalizing([1, 2, 3, 4], $this->idsFor(['activity' => '']));
	}

	public function testTheRefreshTimeOfThePlayerDecidesAboutItsActivity(): void
	{
		$this->connection->executeStatement('UPDATE player SET refresh = 60 WHERE player_id = 1');
		$this->connection->executeStatement('UPDATE player SET refresh = 3600 WHERE player_id = 2');
		$this->lastAccessSecondsAgo(1, 300);
		$this->lastAccessSecondsAgo(2, 300);

		static::assertEqualsCanonicalizing([2], array_intersect($this->idsFor(['activity' => 'active']), [1, 2]));
		static::assertEqualsCanonicalizing([1], array_intersect($this->idsFor(['activity' => 'inactive']), [1, 2]));
	}

	public function testThePlaylistFilter(): void
	{
		static::assertEqualsCanonicalizing([1, 2], $this->idsFor(['playlist_id' => 1]));
		static::assertEqualsCanonicalizing([3], $this->idsFor(['playlist_id' => '2']));
		static::assertSame([], $this->idsFor(['playlist_id' => 99]));
		static::assertEqualsCanonicalizing([1, 2, 3, 4], $this->idsFor(['playlist_id' => 0]), 'The id 0 is no filter.');
		static::assertEqualsCanonicalizing([1, 2, 3, 4], $this->idsFor(['playlist_id' => 'x']));
	}

	/**
	 * @return array<string,array{string,list<int>}>
	 */
	public static function names(): array
	{
		return [
			'a part'               => ['ing', [1]],
			'the whole name'       => ['Kasse', [2]],
			'a joker of the user'  => ['K*e', [2, 3]],
			'a joker of sql'       => ['K%e', []],
			'a single sign of sql' => ['Kass_', []],
			'a quote'              => ["Kasse'", []],
			'nothing'              => ['', [1, 2, 3, 4]],
		];
	}

	/**
	 * @param list<int> $expected
	 */
	#[DataProvider('names')]
	public function testTheNameFilter(string $name, array $expected): void
	{
		static::assertEqualsCanonicalizing($expected, $this->idsFor(['player_name' => $name]));
	}
}
