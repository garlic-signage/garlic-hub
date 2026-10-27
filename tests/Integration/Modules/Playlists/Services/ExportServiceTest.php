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

use App\Framework\Exceptions\AccessDeniedException;
use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\ContentReader;
use App\Modules\Playlists\Collector\ExternalContentReader;
use App\Modules\Playlists\Helper\TemplateItemName;
use GuzzleHttp\Client;
use App\Modules\Playlists\Services\ExportService;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Integration\Modules\Playlists\BuildsExportService;
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
	use BuildsExportService;

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

	public function testTheExportedFilesOfNestedPlaylistsAreCollectedToOne(): void
	{
		$this->actingAs(1);
		foreach ([3, 2, 1] as $playlistId)
			$this->exportService->exportToSmil($playlistId);
		$reader = new ContentReader($this->workspace->config, $this->workspace->filesystem);

		$items = (new BuildHelper($reader, new ExternalContentReader($this->workspace->filesystem, new Client(), 'var/smil/playlists'), new NullLogger()))->collectItems(1);
		static::assertStringNotContainsString('{ITEMS_', $items);
		// the items of the playlist 1 in their order, the playlist 2 and the empty playlist 3 nested at the place of the item 3
		$titles = ['title="bild.jpg"', 'title="film.mp4"', '<seq title="Bernds Kantine"', '<seq title="Annas leere Liste"', 'delivery.php/tpl/7-', 'title="doc.pdf"', 'delivery.php/tpl/5-'];
		$last   = -1;
		foreach ($titles as $title)
		{
			$position = strpos($items, $title);
			static::assertNotFalse($position, $title . ' is missing');
			static::assertGreaterThan($last, $position, $title . ' is in the wrong place');
			$last = $position;
		}
	}

	/**
	 * Item 5 is a template item. After an update its image is still named by the item id: the export gives it
	 * the name of the content, removes the images of former contents and links to it.
	 */
	public function testTheExportLinksToTheImageOfTheExportedContentAndRemovesTheOtherOnes(): void
	{
		$this->actingAs(2);
		$directory = $this->workspace->absolute('/var/storage/playlists/items/originals');
		mkdir($directory, 0775, true);
		mkdir($this->workspace->absolute('/var/storage/playlists/items/thumbs'), 0775, true);
		file_put_contents($directory . '/5.png', 'image');
		file_put_contents($directory . '/5-0123456789abcdef0123456789abcdef.png', 'former content');
		$name = $this->container->get(TemplateItemName::class)->build(5, (string) $this->connection->fetchOne('SELECT content_data FROM playlists_items WHERE item_id = 5'));

		$this->exportService->exportToSmil(1);

		static::assertSame([$name . '.png'], array_values(array_diff((array) scandir($directory), ['.', '..'])));
		static::assertSame('image', file_get_contents($directory . '/' . $name . '.png'));
		static::assertStringContainsString('delivery.php/tpl/' . $name . '.png', (string) file_get_contents($this->workspace->absolute('/var/smil/playlists/1/items.smil')));
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
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE playlists SET playlist_mode = 'multizone', layout = ? WHERE playlist_id = 3", [serialize(['resolution' => '1920x1080', 'zones' => ['screen1' => ['zone_playlist_id' => 1]]])]);

		static::assertSame(1, $this->exportService->exportToSmil(3)); // one zone
		static::assertFileExists($this->workspace->absolute('/var/smil/playlists/1/items.smil'));
	}
}
