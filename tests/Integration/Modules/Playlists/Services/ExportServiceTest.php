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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Media\DeliveryUrlBuilder;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\ExportItemsLoader;
use App\Modules\Playlists\Services\ExportService;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistMetricsUpdater;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Integration\Support\MediaWorkspace;
use Throwable;

/**
 * The export of a playlist to the SMIL files of the players. The files are written into a temporary
 * directory, everything else is the real application. Fixtures: playlist 1 of anna has the items 1 to 5
 * (68 seconds, 1170 bytes), playlist 2 of bernd is nested in it.
 */
#[Group('acl')]
class ExportServiceTest extends ContainerTestCase
{
	private MediaWorkspace $workspace;
	private ExportService $exportService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->workspace    = new MediaWorkspace();
		$this->exportService = $this->buildExportService($this->workspace);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testTheSmilFilesAreWrittenAndTheMetricsAreStored(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->exportService->exportToSmil(1));

		foreach (['prefetch', 'items', 'exclusive'] as $file)
			static::assertFileExists($this->workspace->absolute('/var/smil/playlists/1/' . $file . '.smil'));
		static::assertSame([68, 1170, 63], array_map(intval(...), (array) $this->connection->fetchNumeric('SELECT duration, filesize, owner_duration FROM playlists WHERE playlist_id = 1')));
		static::assertNotSame('0000-00-00 00:00:00', $this->connection->fetchOne('SELECT export_time FROM playlists WHERE playlist_id = 1'));
	}

	public function testTheExportOfANestedPlaylistUpdatesTheItemsWhichStandForIt(): void
	{
		$this->actingAs(3);

		$this->exportService->exportToSmil(2);

		// item 3 of the playlist 1 stands for 2: the 3 and 8 seconds of the items 6 and 7
		static::assertSame(11, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 3'));
		static::assertSame(74, (int) $this->connection->fetchOne('SELECT duration FROM playlists WHERE playlist_id = 1'));
	}

	public function testAUserMayNotExportAForeignPlaylist(): void
	{
		$this->actingAs(2);

		try
		{
			$this->exportService->exportToSmil(2);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertFileDoesNotExist($this->workspace->absolute('/var/smil/playlists/2/items.smil'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT duration FROM playlists WHERE playlist_id = 2'));
		}
	}

	/**
	 * The files are written after the metrics are stored. When the writing fails nothing is stored.
	 */
	public function testNothingIsStoredWhenTheFilesCanNotBeWritten(): void
	{
		$this->actingAs(3);
		// a file where the directory of the smil files should be
		mkdir($this->workspace->absolute('/var/smil'), 0775, true);
		file_put_contents($this->workspace->absolute('/var/smil/playlists'), 'blocked');

		try
		{
			$this->exportService->exportToSmil(2);
			static::fail('Expected an exception of the file system');
		}
		catch (Throwable)
		{
			static::assertSame(5, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 3'));
			static::assertSame(0, (int) $this->connection->fetchOne('SELECT duration FROM playlists WHERE playlist_id = 1'));
			static::assertSame('0000-00-00 00:00:00', $this->connection->fetchOne('SELECT export_time FROM playlists WHERE playlist_id = 2'));
		}
	}

	public function testTheZonesOfAMultizonePlaylistAreExported(): void
	{
		static::markTestSkipped('Known bug: ExportService reads the zones from the column multizone, which migration 003 renamed to layout, and expects a list of zones. The layout is [resolution, zones => [screen => [zone_playlist_id]]]. A multizone playlist exports no zone. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE playlists SET playlist_mode = 'multizone', layout = ? WHERE playlist_id = 3", [serialize(['resolution' => '1920x1080', 'zones' => ['screen1' => ['zone_playlist_id' => 1]]])]);

		static::assertSame(1, $this->exportService->exportToSmil(3)); // one zone
		static::assertFileExists($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
	}

	/**
	 * Like config/services/playlists.php, but the files go into the workspace.
	 */
	private function buildExportService(MediaWorkspace $workspace): ExportService
	{
		$itemsRepository   = $this->container->get(ItemsRepository::class);
		$playlistsService  = $this->container->get(PlaylistsService::class);
		$calculator        = $this->container->get(PlaylistMetricsCalculator::class);
		$config            = $this->container->get(Config::class);

		return new ExportService(
			$workspace->config,
			$playlistsService,
			new ExportItemsLoader($itemsRepository, $calculator),
			new PlaylistMetricsUpdater($itemsRepository, $playlistsService, $calculator, new NullLogger()),
			$this->container->get(Transactions::class),
			new LocalWriter($workspace->config, $workspace->filesystem),
			new PlaylistContent(
				new ItemsFactory($config),
				new DeliveryUrlBuilder($config->getConfigValue('url', 'mediapool', 'content_server')),
				$this->container->get(MimeTypeExtensionMapper::class)
			),
			new NullLogger()
		);
	}
}
