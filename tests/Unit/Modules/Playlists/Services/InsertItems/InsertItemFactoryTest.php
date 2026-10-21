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
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Auth\UserSession;
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
		$mediaServiceStub = $this->createStub(MediaService::class);
		$itemsRepositoryStub = $this->createStub(ItemsRepository::class);
		$playlistsServiceStub = $this->createStub(PlaylistsService::class);
		$playlistMetricsCalculatorStub = $this->createStub(PlaylistMetricsCalculator::class);
		$templatesServiceStub = $this->createStub(TemplatesService::class);
		$configStub = $this->createStub(Config::class);
		$fileSystemStub = $this->createStub(Filesystem::class);
		$widgetsServiceStub = $this->createStub(WidgetsService::class);
		$loggerStub = $this->createStub(LoggerInterface::class);

		$this->factory = new InsertItemFactory(
			$mediaServiceStub,
			$itemsRepositoryStub,
			$this->createStub(Transactions::class),
			$playlistsServiceStub,
			$templatesServiceStub,
			$configStub,
			$fileSystemStub,
			new MediaUrlSigner('unit-test-secret'),
			$playlistMetricsCalculatorStub,
			$widgetsServiceStub,
			$this->createStub(UserSession::class),
			$loggerStub
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
