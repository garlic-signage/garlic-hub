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

namespace Tests\Unit\Modules\Player\Services;

use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\PlayerPlaylistUsageFinder;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerPlaylistUsageFinderTest extends TestCase
{
	private PlayerRepository&MockObject $playerRepositoryMock;
	private PlayerPlaylistUsageFinder $finder;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerRepositoryMock = $this->createMock(PlayerRepository::class);
		$this->finder               = new PlayerPlaylistUsageFinder($this->playerRepositoryMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindUsedPlaylistIdsReturnsAssignedIds(): void
	{
		$this->playerRepositoryMock->expects($this->once())->method('findPlaylistIdsByPlaylistIds')
			->with([1, 2, 3])
			->willReturn([['playlist_id' => '1'], ['playlist_id' => 3]]);

		static::assertSame([1, 3], $this->finder->findUsedPlaylistIds([1, 2, 3]));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindUsedPlaylistIdsWithoutAssignments(): void
	{
		$this->playerRepositoryMock->expects($this->once())->method('findPlaylistIdsByPlaylistIds')
			->with([4])
			->willReturn([]);

		static::assertSame([], $this->finder->findUsedPlaylistIds([4]));
	}
}
