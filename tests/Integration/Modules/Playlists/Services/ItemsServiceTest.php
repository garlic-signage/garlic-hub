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

use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Playlists\Services\ItemsService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What ItemsService does with the items of a playlist. Who may do it is in ItemsAccessTest.
 * Fixtures: playlist 1 of anna with the items 1 (image), 2 (video), 3 (nested playlist 2), 4 (pdf)
 * and 5 (template), playlist 2 of bernd with the items 6 and 7 (11 seconds of bernd).
 */
#[Group('acl')]
class ItemsServiceTest extends ContainerTestCase
{
	private ItemsService $itemsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->itemsService = $this->container->get(ItemsService::class);
	}

	public function testAVideoBringsItsOwnDuration(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"duration\":42}', config_data = '{\"volume\":5}' WHERE checksum = 'checksum-video'");

		$item = $this->itemsService->fetchItemById(2);

		static::assertSame(42, $item['default_duration']);
		static::assertSame('{"volume":5}', $item['config_data']);
		static::assertSame('film.mp4', $item['item_name']);
	}

	public function testAnImageGetsTheDefaultDuration(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"width\":10}' WHERE checksum = 'checksum-image'");

		static::assertSame(15, $this->itemsService->fetchItemById(1)['default_duration']);
	}

	public function testAMediaWhichIsNotInTheMediapoolAnymoreIsNotFound(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE playlists_items SET file_resource = 'checksum-gone' WHERE item_id = 1");

		$this->expectException(NotFoundException::class);
		$this->itemsService->fetchItemById(1);
	}

	public function testANestedPlaylistBringsItsDuration(): void
	{
		$this->actingAs(2);

		static::assertSame(11, $this->itemsService->fetchItemById(3)['default_duration']);
	}

	/**
	 * The items of others may exceed the limit, only the items of the owner are limited.
	 */
	public function testANestedPlaylistIsLimitedByItsTimeLimit(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement('UPDATE playlists SET time_limit = 5 WHERE playlist_id = 2');
		$this->connection->executeStatement('UPDATE playlists_items SET UID = 1 WHERE item_id = 7');

		static::assertSame(5, $this->itemsService->fetchItemById(3)['default_duration']);
	}

	public function testATemplateGetsTheDefaultDurationAndThePlaylistName(): void
	{
		$this->actingAs(2);

		$item = $this->itemsService->fetchItemById(5);

		static::assertSame(15, $item['default_duration']);
		static::assertSame('Annas Lobby', $item['playlist_name']);
	}

	public function testTheComposerGetsTheItemsWithThumbnailsAndTheMetrics(): void
	{
		$this->actingAs(2);

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer(1);

		static::assertSame('Annas Lobby', $result['playlist']['playlist_name']);
		static::assertSame(
			['count_items' => 5, 'count_owner_items' => 4, 'filesize' => 1170, 'duration' => 68, 'owner_duration' => 63],
			$result['playlist_metrics']
		);
		static::assertSame([1, 2, 3, 4, 5], array_map(intval(...), array_column($result['items'], 'item_id')));
		// a nested playlist has the icon of a playlist, a template the rendered thumbnail of the item
		static::assertSame('/images/icons/playlist.svg', $result['items'][2]['urls']['thumbnail']);
		static::assertStringEndsWith('/5.jpg', $result['items'][4]['paths']['thumbnail']);
		static::assertStringContainsString('checksum-image.jpg', $result['items'][0]['paths']['thumbnail']);
	}

	public function testTheComposerSkipsItemsWithoutThumbnail(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE playlists_items SET item_type = 'channel' WHERE item_id = 4");

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer(1);

		static::assertSame([1, 2, 3, 5], array_map(intval(...), array_column($result['items'], 'item_id')));
	}

	public function testTheComposerOfAnEmptyPlaylist(): void
	{
		$this->actingAs(2);

		$result = $this->itemsService->loadItemsByPlaylistIdForComposer(3);

		static::assertSame([], $result['items']);
		static::assertSame(0, $result['playlist_metrics']['count_items']);
	}

	public function testUpdateFieldStoresTheValueAsString(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->itemsService->updateField(1, 'item_name', 'a <b>name</b>'));
		static::assertSame(1, $this->itemsService->updateField(2, 'item_duration', 12));

		static::assertSame('a <b>name</b>', $this->connection->fetchOne('SELECT item_name FROM playlists_items WHERE item_id = 1'));
		static::assertSame(12, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 2'));
	}

	public function testUpdateItemDurationStoresTheWantedDuration(): void
	{
		$this->actingAs(2);

		static::assertSame(20, $this->itemsService->updateItemDuration(1, 20));

		static::assertSame(20, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 1'));
	}

	public function testAUserCanNotExceedTheTimeLimitOfThePlaylist(): void
	{
		$this->actingAs(3);

		$stored = $this->itemsService->updateItemDuration(6, 5000);

		static::assertNotNull($stored);
		static::assertLessThan(600, $stored);
		static::assertSame($stored, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 6'));
		static::assertLessThanOrEqual(600, (int) $this->connection->fetchOne('SELECT SUM(item_duration) FROM playlists_items WHERE playlist_id = 2'));
	}

	public function testTheAdminIsNotLimitedByTheTimeLimit(): void
	{
		$this->actingAs(1);

		static::assertSame(5000, $this->itemsService->updateItemDuration(6, 5000));
	}

	/**
	 * A nested playlist can not be longer than its own time limit, whatever the outer playlist allows.
	 */
	public function testTheDurationOfANestedPlaylistIsLimitedByItsOwnTimeLimit(): void
	{
		$this->actingAs(2);

		$stored = $this->itemsService->updateItemDuration(3, 5000);

		static::assertNotNull($stored);
		static::assertLessThan(600, $stored);
	}

	public function testAnUnknownItemInTheOrderChangesNothing(): void
	{
		$this->actingAs(2);

		try
		{
			$this->itemsService->updateItemOrder(1, ['2', '1', '999']);
			static::fail('Expected NotFoundException');
		}
		catch (NotFoundException)
		{
			static::assertSame([1, 2, 3, 4, 5], $this->orderOfPlaylist(1));
		}
	}

	public function testDeleteOfAnUnknownItemChangesNothing(): void
	{
		$this->actingAs(2);

		try
		{
			$this->itemsService->delete(1, 999);
			static::fail('Expected NotFoundException');
		}
		catch (NotFoundException)
		{
			static::assertSame(7, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items'));
		}
	}

	/**
	 * The metrics are calculated after the delete. When they fail, the item must not be gone.
	 */
	public function testDeleteIsRolledBackWhenTheMetricsFail(): void
	{
		$this->actingAs(3);
		// bernd owns 11 seconds in his playlist, a limit of 1 stays exceeded after the delete
		$this->connection->executeStatement('UPDATE playlists SET time_limit = 1 WHERE playlist_id = 2');

		try
		{
			$this->itemsService->delete(2, 7);
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException)
		{
			static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 2'));
			static::assertSame([1, 2], $this->orderOfPlaylist(2));
		}
	}

	/**
	 * @return list<int>
	 */
	private function orderOfPlaylist(int $playlistId): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT item_order FROM playlists_items WHERE playlist_id = ? ORDER BY item_order', [$playlistId]));
	}
}
