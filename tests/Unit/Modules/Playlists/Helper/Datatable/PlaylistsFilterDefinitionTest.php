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

namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Modules\Playlists\Helper\Datatable\PlaylistsFilterDefinition;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PlaylistsFilterDefinitionTest extends TestCase
{
	private FilterConditions $conditions;
	private PlaylistsFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->conditions = new FilterConditions();
		$this->definition = new PlaylistsFilterDefinition($this->conditions);
	}

	#[Group('units')]
	public function testTableJoinsAndSelects(): void
	{
		static::assertSame('playlists', $this->definition->table());
		static::assertSame(['user_main' => 'user_main.UID=playlists.UID'], $this->definition->joins());
		static::assertSame([], $this->definition->userJoins());
		static::assertSame(['playlists.*'], $this->definition->selects());
		static::assertSame(['playlists.*', 'user_main.username', 'user_main.company_id'], $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testPlaylistModeSingleValue(): void
	{
		$where = $this->definition->whereForFiltering(['playlist_mode' => ['value' => 'master']]);

		static::assertSame(['playlist_mode' => $this->conditions->whereClause('master')], $where);
	}

	#[Group('units')]
	public function testPlaylistModeMultipleValuesUsesIn(): void
	{
		$where = $this->definition->whereForFiltering(['playlist_mode' => ['value' => 'master,multizone']]);

		$expected = ['playlist_mode' => $this->conditions->whereClause('master,multizone', 'IN', 'AND', ArrayParameterType::STRING)];
		static::assertSame($expected, $where);
	}

	#[Group('units')]
	public function testPlaylistModeEmptyIsIgnored(): void
	{
		static::assertSame([], $this->definition->whereForFiltering(['playlist_mode' => ['value' => '']]));
	}

	#[Group('units')]
	public function testOtherFieldsUseDefaultHandling(): void
	{
		$fields = [
			'elements_page'  => ['value' => 1],
			'playlist_mode'  => ['value' => 'multizone'],
			'playlist_name'  => ['value' => 'name'],
			'username'       => ['value' => 'john']
		];

		$expected = [
			'playlist_mode'          => $this->conditions->whereClause('multizone'),
			'playlists.playlist_name' => $this->conditions->likeClause('name'),
			'user_main.username'     => $this->conditions->likeClause('john')
		];
		static::assertSame($expected, $this->definition->whereForFiltering($fields));
	}
}
