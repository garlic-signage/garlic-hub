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
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\ExportItemsLoader;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The items of an export come with their settings unserialized and with the metrics of the playlist.
 * Fixtures: the playlist 1 of anna has the items 1 to 5 (68 seconds, 1170 bytes, 63 seconds of anna).
 */
#[Group('acl')]
class ExportItemsLoaderTest extends ContainerTestCase
{
	private ExportItemsLoader $loader;
	/** @var array<string,mixed> */
	private array $playlist;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->actingAs(2);
		$this->loader   = new ExportItemsLoader($this->container->get(ItemsRepository::class), $this->container->get(PlaylistMetricsCalculator::class));
		$this->playlist = $this->container->get(PlaylistsService::class)->fetchById(1);
	}

	public function testTheSettingsOfTheItemsAreUnserialized(): void
	{
		$this->connection->executeStatement(
			'UPDATE playlists_items SET conditional = ?, properties = ?, categories = ?, content_data = ?, begin_trigger = ?, end_trigger = ? WHERE item_id = 1',
			[serialize(['weekdays' => ['mon']]), serialize(['fit' => 'meet']), serialize(['news']), serialize(['urls' => 'x']), serialize(['touches' => []]), serialize(['stop' => 1])]
		);

		$result = $this->loader->loadByPlaylist($this->playlist, Config::PLATFORM_EDITION_EDGE);

		$item = $result['items'][0];
		static::assertSame(1, (int) $item['item_id']);
		static::assertSame(['weekdays' => ['mon']], $item['conditional']);
		static::assertSame(['fit' => 'meet'], $item['properties']);
		static::assertSame(['news'], $item['categories']);
		static::assertSame(['urls' => 'x'], $item['content_data']);
		static::assertSame(['touches' => []], $item['begin_trigger']);
		static::assertSame(['stop' => 1], $item['end_trigger']);
		static::assertSame('jpg', $item['extension']);
	}

	public function testItemsWithoutSettingsAndBrokenSettingsAreEmpty(): void
	{
		$this->connection->executeStatement("UPDATE playlists_items SET conditional = 'not serialized {' WHERE item_id = 2");

		$result = $this->loader->loadByPlaylist($this->playlist, Config::PLATFORM_EDITION_EDGE);

		static::assertSame([], $result['items'][0]['conditional']);
		static::assertSame([], $result['items'][1]['conditional']);
		static::assertSame([], $result['items'][1]['begin_trigger']);
	}

	/**
	 * The content of a template is a text of the template, not a serialized setting.
	 */
	public function testTheContentOfATemplateStaysAsItIs(): void
	{
		$this->connection->executeStatement("UPDATE playlists_items SET content_data = '{\"objects\":[]}' WHERE item_id = 5");

		$result = $this->loader->loadByPlaylist($this->playlist, Config::PLATFORM_EDITION_EDGE);

		static::assertSame('{"objects":[]}', $result['items'][4]['content_data']);
	}

	public function testTheMetricsAreThoseOfTheItems(): void
	{
		$result = $this->loader->loadByPlaylist($this->playlist, Config::PLATFORM_EDITION_EDGE);

		static::assertSame(
			['count_items' => 5, 'count_owner_items' => 4, 'filesize' => 1170, 'duration' => 68, 'owner_duration' => 63],
			$result['playlist_metrics']->toFrontendArray()
		);
		static::assertSame([1, 2, 3, 4, 5], array_map(intval(...), array_column($result['items'], 'item_id')));
	}

	public function testAnEmptyPlaylistHasNoItemsAndNoMetrics(): void
	{
		$result = $this->loader->loadByPlaylist($this->container->get(PlaylistsService::class)->fetchById(3), Config::PLATFORM_EDITION_EDGE);

		static::assertSame([], $result['items']);
		static::assertSame(0, $result['playlist_metrics']->countEntries);
	}
}
