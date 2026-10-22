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

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\ExportItemsLoader;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportItemsLoaderTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private ExportItemsLoader $loader;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock           = $this->createMock(ItemsRepository::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);

		$this->loader = new ExportItemsLoader($this->itemsRepositoryMock, $this->playlistMetricsCalculatorMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadByPlaylistUnserializesItemSettings(): void
	{
		$playlist = ['playlist_id' => 1];
		$edition = Config::PLATFORM_EDITION_EDGE;

		$conditional = serialize(['conditions' => '']);
		$results = [
			['conditional' => $conditional, 'properties' => '', 'item_type' => ItemType::MEDIAPOOL->value, 'categories' => '', 'content_data' => '', 'begin_trigger' => '', 'end_trigger' => ''],
			['conditional' => '', 'properties' => '', 'item_type' => ItemType::MEDIAPOOL->value, 'categories' => '', 'content_data' => '', 'begin_trigger' => '', 'end_trigger' => ''],
		];

		$this->itemsRepositoryMock->expects($this->once())->method('findAllByPlaylistIdWithJoins')
			->with($playlist['playlist_id'], $edition)
			->willReturn($results);

		$metrics = new PlaylistMetrics(2, 1, 300, 70, 30);
		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromItems')
			->with($playlist, $results)
			->willReturn($metrics);

		$result = $this->loader->loadByPlaylist($playlist, $edition);

		static::assertArrayHasKey('playlist_metrics', $result);
		static::assertArrayHasKey('items', $result);

		static::assertSame($metrics, $result['playlist_metrics']);
		static::assertCount(2, $result['items']);
		static::assertSame(['conditions' => ''], $result['items'][0]['conditional']);
		static::assertSame([], $result['items'][1]['conditional']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadByPlaylistWithEmptyResults(): void
	{
		$playlist = ['playlist_id' => 1];
		$edition = Config::PLATFORM_EDITION_EDGE;

		$this->itemsRepositoryMock->expects($this->once())->method('findAllByPlaylistIdWithJoins')
			->with($playlist['playlist_id'], $edition)
			->willReturn([]);

		$metrics = new PlaylistMetrics();
		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateFromItems')
			->with($playlist, [])
			->willReturn($metrics);

		$result = $this->loader->loadByPlaylist($playlist, $edition);

		static::assertArrayHasKey('playlist_metrics', $result);
		static::assertArrayHasKey('items', $result);

		static::assertSame($metrics, $result['playlist_metrics']);
		static::assertCount(0, $result['items']);
	}
}
