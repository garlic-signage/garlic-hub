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

use App\Framework\Database\Where;
use App\Framework\Database\Datatable\FilterConditions;
use App\Modules\Playlists\Helper\Datatable\PlaylistsFilterDefinition;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PlaylistsFilterDefinitionTest extends TestCase
{
	private PlaylistsFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->definition = new PlaylistsFilterDefinition(new FilterConditions());
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

		static::assertEquals(['playlist_mode' => Where::equals('playlist_mode', 'master')], $where);
	}

	#[Group('units')]
	public function testPlaylistModeMultipleValuesUsesIn(): void
	{
		$where = $this->definition->whereForFiltering(['playlist_mode' => ['value' => 'master,multizone']]);

		$expected = ['playlist_mode' => Where::in('playlist_mode', ['master', 'multizone'], ArrayParameterType::STRING)];
		static::assertEquals($expected, $where);
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
			'playlist_mode'           => Where::equals('playlist_mode', 'multizone'),
			'playlists.playlist_name' => Where::contains('playlists.playlist_name', 'name'),
			'user_main.username'      => Where::contains('user_main.username', 'john')
		];
		static::assertEquals($expected, $this->definition->whereForFiltering($fields));
	}
}
