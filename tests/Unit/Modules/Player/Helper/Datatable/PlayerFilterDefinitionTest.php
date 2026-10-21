<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PlayerFilterDefinitionTest extends TestCase
{
	private FilterConditions $conditions;
	private PlayerFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->conditions = new FilterConditions();
		$this->definition = new PlayerFilterDefinition($this->conditions, new SQLitePlatform());
	}

	#[Group('units')]
	public function testTableAndJoins(): void
	{
		static::assertSame('player', $this->definition->table());
		static::assertSame([
			'user_main' => 'user_main.UID = player.UID',
			'playlists' => 'playlists.playlist_id = player.playlist_id'
		], $this->definition->joins());
		static::assertSame(['playlists' => 'playlists.playlist_id = player.playlist_id'], $this->definition->userJoins());
	}

	#[Group('units')]
	public function testSelects(): void
	{
		$selects = ['player_id', 'player.playlist_id', 'playlist_name', 'is_intranet', 'firmware', 'player.status', 'model',
			'commands', 'reports', 'player.last_access', 'refresh', 'player_name, player.UID'];

		static::assertSame($selects, $this->definition->selects());
		static::assertSame(array_merge($selects, ['user_main.username', 'user_main.company_id']), $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testWhereFullFiltered(): void
	{
		$fields = [
			'elements_page'     => ['value' => 1],
			'elements_per_page' => ['value' => 10],
			'activity'          => ['value' => 'active'],
			'playlist_id'       => ['value' => '12'],
			'player_name'       => ['value' => 'name']
		];

		$expected = [
			'player.last_access' => ['expression' => "player.last_access >= DATETIME('now', '-' || (2 * player.refresh) || ' seconds')"],
			'player.playlist_id' => $this->conditions->whereClause(12),
			'player.player_name' => $this->conditions->whereClause('%name%', 'LIKE')
		];
		static::assertSame($expected, $this->definition->whereForFiltering($fields));
	}

	#[Group('units')]
	public function testWhereInactive(): void
	{
		$where = $this->definition->whereForFiltering(['activity' => ['value' => 'inactive']]);

		static::assertSame(['player.last_access' => ['expression' => "player.last_access < DATETIME('now', '-' || (2 * player.refresh) || ' seconds')"]], $where);
	}

	#[Group('units')]
	public function testWhereActivityForMysql(): void
	{
		$definition = new PlayerFilterDefinition($this->conditions, new MySQLPlatform());
		$where      = $definition->whereForFiltering(['activity' => ['value' => 'active']]);

		static::assertSame(['player.last_access' => ['expression' => 'player.last_access >= DATE_SUB(NOW(), INTERVAL (2 * player.refresh) SECOND)']], $where);
	}

	#[Group('units')]
	public function testWhereIgnoresAllActivityAndZeroPlaylist(): void
	{
		$fields = ['activity' => ['value' => 'all'], 'playlist_id' => ['value' => 0]];
		static::assertSame([], $this->definition->whereForFiltering($fields));
		static::assertSame([], $this->definition->whereForFiltering(['activity' => ['value' => '']]));
	}
}
