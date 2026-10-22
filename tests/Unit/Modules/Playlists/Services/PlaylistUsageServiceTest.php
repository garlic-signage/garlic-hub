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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Modules\Playlists\Contracts\PlaylistUsageFinderInterface;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistUsageService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaylistUsageServiceTest extends TestCase
{
	private PlaylistUsageFinderInterface&MockObject $playlistUsageFinderMock;
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private PlaylistUsageService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playlistUsageFinderMock = $this->createMock(PlaylistUsageFinderInterface::class);
		$this->itemsRepositoryMock  = $this->createMock(ItemsRepository::class);

		$this->service = new PlaylistUsageService($this->playlistUsageFinderMock, $this->itemsRepositoryMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeterminePlaylistsInUseWithValidResults(): void
	{
		$playlistIds = [1, 2, 3];

		$this->playlistUsageFinderMock->method('findUsedPlaylistIds')
			->with($playlistIds)
			->willReturn([1, 2]);

		$this->itemsRepositoryMock->method('findFileResourcesByPlaylistId')
			->with($playlistIds)
			->willReturnMap([
				[$playlistIds, [['playlist_id' => 2], ['playlist_id' => 3]]]
			]);

		$result = $this->service->determinePlaylistsInUse($playlistIds);

		static::assertSame([
			1 => true,
			2 => true,
			3 => true
		], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeterminePlaylistsInUseWithNoResults(): void
	{
		$playlistIds = [1, 2, 3];

		$this->playlistUsageFinderMock->method('findUsedPlaylistIds')
			->with($playlistIds)
			->willReturn([]);

		$this->itemsRepositoryMock->method('findFileResourcesByPlaylistId')
			->with($playlistIds)
			->willReturnMap([
				[$playlistIds, []]
			]);

		$result = $this->service->determinePlaylistsInUse($playlistIds);

		static::assertSame([], $result);
	}

}
