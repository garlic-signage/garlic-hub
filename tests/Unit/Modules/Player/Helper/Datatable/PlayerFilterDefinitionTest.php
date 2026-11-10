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
use App\Framework\Database\Where;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The conditions of the player list. Which players they find is tested with the database in
 * tests/Integration/Modules/Player/Repositories/PlayerFilterDefinitionTest.
 */
class PlayerFilterDefinitionTest extends TestCase
{
	private PlayerFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->definition = new PlayerFilterDefinition(new FilterConditions(), new SQLitePlatform());
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,Where>
	 */
	private function where(array $values, ?PlayerFilterDefinition $definition = null): array
	{
		return ($definition ?? $this->definition)->whereForFiltering(array_map(static fn(mixed $value): array => ['value' => $value], $values));
	}

	#[Group('units')]
	public function testTableJoinsAndSelects(): void
	{
		$selects = ['player_id', 'player.playlist_id', 'playlist_name', 'is_intranet', 'firmware', 'player.status', 'model', 'commands', 'reports',
			'player.last_access', 'refresh', 'player_name, player.UID'];

		static::assertSame('player', $this->definition->table());
		static::assertSame(['user_main' => 'user_main.UID = player.UID', 'playlists' => 'playlists.playlist_id = player.playlist_id'], $this->definition->joins());
		static::assertSame(['playlists' => 'playlists.playlist_id = player.playlist_id'], $this->definition->userJoins());
		static::assertSame($selects, $this->definition->selects());
		static::assertSame([...$selects, 'user_main.username', 'user_main.company_id'], $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testAllFiltersTogether(): void
	{
		static::assertEquals([
			'player.last_access' => Where::raw("player.last_access >= DATETIME('now', '-' || (2 * player.refresh) || ' seconds')"),
			'player.playlist_id' => Where::equals('player.playlist_id', 5),
			'player.model'       => Where::equals('player.model', 3),
			...new FilterConditions()->forField('player', 'player_name', ['value' => 'Lobby'])
		], $this->where(['activity' => 'active', 'playlist_id' => '5', 'model' => '3', 'player_name' => 'Lobby']));
	}

	#[Group('units')]
	public function testAnInactivePlayerOfMysql(): void
	{
		$definition = new PlayerFilterDefinition(new FilterConditions(), new MySQL80Platform());

		static::assertEquals(
			['player.last_access' => Where::raw('player.last_access < DATE_SUB(NOW(), INTERVAL (2 * player.refresh) SECOND)')],
			$this->where(['activity' => 'inactive'], $definition)
		);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noFilters(): array
	{
		return [
			'no activity'      => [['activity' => 'sometimes']],
			'no playlist'      => [['playlist_id' => '0']],
			'a negative id'    => [['playlist_id' => '-3']],
			'a model as text'  => [['model' => 'abc']],
			'a negative model' => [['model' => '-1']],
			'empty model'      => [['model' => '']],
		];
	}

	/**
	 * @param array<string,mixed> $values
	 */
	#[Group('units')]
	#[DataProvider('noFilters')]
	public function testValuesWhichAreNoFilter(array $values): void
	{
		static::assertSame([], $this->where($values));
	}

	#[Group('units')]
	public function testFieldsWithoutValueAreNoFilter(): void
	{
		static::assertSame([], $this->definition->whereForFiltering(['activity' => [], 'playlist_id' => [], 'model' => []]));
	}

	#[Group('units')]
	public function testTheModelIsANumberAlsoWhenItComesAsNumber(): void
	{
		static::assertEquals(['player.model' => Where::equals('player.model', 0)], $this->where(['model' => 0]));
	}
}
