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

use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\PlayerPlaylistUsageFinder;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * Which of the playlists a player plays. Players (player/players.sql): 1 and 2 play the playlist 1, 3 plays 2, 4 plays none.
 */
#[Group('db')]
class PlayerPlaylistUsageTest extends RepositoryTestCase
{
	private PlayerPlaylistUsageFinder $finder;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('player/playlists.sql');
		$this->loadFixture('player/players.sql');
		$this->finder = new PlayerPlaylistUsageFinder(new PlayerRepository($this->connection));
	}

	public function testThePlaylistsWhichAPlayerPlaysAreUsed(): void
	{
		$used = $this->finder->findUsedPlaylistIds([1, 2, 3]);

		static::assertSame([1, 2], array_values(array_unique($used)));
		static::assertContainsOnlyInt($used);
	}

	public function testAPlaylistWhichNoPlayerPlaysIsNotUsed(): void
	{
		static::assertSame([], $this->finder->findUsedPlaylistIds([3, 99]));
		static::assertSame([2], $this->finder->findUsedPlaylistIds([2, 3]));
	}

	public function testNoPlaylistsAreNeverUsed(): void
	{
		static::assertSame([], $this->finder->findUsedPlaylistIds([]));
	}
}
