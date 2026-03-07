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

namespace Tests\Unit\Modules\Playlists\Services\InsertItems;

use App\Framework\Core\Config\Config;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\InsertItems\Media;
use App\Modules\Playlists\Services\InsertItems\Playlist;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\WidgetsService;
use App\Modules\Templates\Services\TemplatesService;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InsertItemFactoryTest extends TestCase
{
	private InsertItemFactory $factory;


	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$mediaServiceMock = $this->createMock(MediaService::class);
		$itemsRepositoryMock = $this->createMock(ItemsRepository::class);
		$playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);
		$templatesServiceMock = $this->createMock(TemplatesService::class);
		$configMock = $this->createMock(Config::class);
		$fileSystemMock = $this->createMock(Filesystem::class);
		$widgetsServiceMock = $this->createMock(WidgetsService::class);
		$loggerMock = $this->createMock(LoggerInterface::class);

		$this->factory = new InsertItemFactory(
			$mediaServiceMock,
			$itemsRepositoryMock,
			$playlistsServiceMock,
			$templatesServiceMock,
			$configMock,
			$fileSystemMock,
			$playlistMetricsCalculatorMock,
			$widgetsServiceMock,
			$loggerMock
		);
	}

	#[Group('units')]
	public function testCreateReturnsMediaInstanceForMediapoolSource(): void
	{
		$result = $this->factory->create('mediapool');

		static::assertInstanceOf(Media::class, $result);
	}

	#[Group('units')]
	public function testCreateReturnsPlaylistInstanceForPlaylistSource(): void
	{
		$result = $this->factory->create('playlist');

		static::assertInstanceOf(Playlist::class, $result);
	}

	#[Group('units')]
	public function testCreateReturnsNullForInvalidSource(): void
	{
		$result = $this->factory->create('invalid_source');

		static::assertNull($result);
	}


}
