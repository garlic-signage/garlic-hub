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

namespace Tests\Integration\Db\Modules\Playlists\Repositories;

use App\Framework\Config\Config;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Db\RepositoryTestCase;

#[Group('db')]
class ItemsRepositoryTest extends RepositoryTestCase
{
	private ItemsRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new ItemsRepository($this->connection);
		$this->loadFixture('security/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByPlaylistIdIsOrderedAndLimitedToThePlaylist(): void
	{
		$items = $this->repository->findAllByPlaylistId(1);

		static::assertSame([1, 2, 3, 4, 5], $this->ids($items, 'item_id'));
		static::assertSame([6, 7], $this->ids($this->repository->findAllByPlaylistId(2), 'item_id'));
		static::assertSame([], $this->repository->findAllByPlaylistId(3));
	}

	/**
	 * @throws Exception
	 */
	public function testFindMediaInPlaylistIdReturnsOnlyImagesAndVideos(): void
	{
		$items = $this->repository->findMediaInPlaylistId(1);

		static::assertSame([1, 2, 5], $this->ids($items, 'item_id'));
		static::assertSame('bild.jpg', $items[0]['item_name']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByPlaylistIdWithJoinsAddsMediaAndNestedPlaylistData(): void
	{
		$items = $this->repository->findAllByPlaylistIdWithJoins(1, Config::PLATFORM_EDITION_EDGE);

		static::assertSame([1, 2, 3, 4, 5], $this->ids($items, 'item_id'));
		static::assertSame('jpg', $items[0]['extension']);
		static::assertSame('mp4', $items[1]['extension']);
		// nested playlist 2 brings its limits
		static::assertSame(600, (int) $items[2]['time_limit']);
		static::assertSame(120, (int) $items[2]['owner_duration']);
		static::assertNull($items[0]['time_limit']);
	}

	/**
	 * Two uploads have the same checksum. The item must still appear only once.
	 *
	 * @throws Exception
	 */
	public function testFindAllByPlaylistIdWithJoinsDoesNotDuplicateItemsForDoubleFiles(): void
	{
		$items = $this->repository->findAllByPlaylistIdWithJoins(1, Config::PLATFORM_EDITION_EDGE);

		static::assertCount(5, $items);
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllPlaylistItemsByPlaylistIdReturnsOnlyNestedPlaylists(): void
	{
		static::assertSame([3], $this->ids($this->repository->findAllPlaylistItemsByPlaylistId(1), 'item_id'));
		static::assertSame([6], $this->ids($this->repository->findAllPlaylistItemsByPlaylistId(2), 'item_id'));
	}

	/**
	 * @throws Exception
	 */
	public function testSumAndCountMetricsSplitsByOwner(): void
	{
		$metrics = $this->repository->sumAndCountMetricsByPlaylistIdAndOwner(1, 2);

		static::assertSame(5, (int) $metrics['count_items']);
		static::assertSame(4, (int) $metrics['count_owner_items']);
		static::assertSame(1170, (int) $metrics['filesize']);
		static::assertSame(68, (int) $metrics['duration']);
		static::assertSame(63, (int) $metrics['owner_duration']);
	}

	/**
	 * @throws Exception
	 */
	public function testSumAndCountMetricsOfAnEmptyPlaylist(): void
	{
		$metrics = $this->repository->sumAndCountMetricsByPlaylistIdAndOwner(3, 2);

		static::assertSame(0, (int) $metrics['count_items']);
		static::assertSame(0, (int) $metrics['count_owner_items']);
		static::assertSame(0, (int) $metrics['duration']);
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllPlaylistsContainingPlaylist(): void
	{
		$containing = $this->repository->findAllPlaylistsContainingPlaylist(2);

		static::assertSame([1], $this->ids($containing, 'playlist_id'));
		static::assertSame('Annas Lobby', $containing[0]['playlist_name']);
		static::assertSame([2], $this->ids($this->repository->findAllPlaylistsContainingPlaylist(3), 'playlist_id'));
		static::assertSame([], $this->repository->findAllPlaylistsContainingPlaylist(1));
	}

	/**
	 * @throws Exception
	 */
	public function testUpdatePositionsWhenInsertedMakesRoomAtThePosition(): void
	{
		static::assertSame(3, $this->repository->updatePositionsWhenInserted(1, 3));

		static::assertSame([1, 2, 4, 5, 6], $this->orderOf(1));
		static::assertSame([1, 2], $this->orderOf(2));
	}

	/**
	 * @throws Exception
	 */
	public function testUpdatePositionsWhenDeletedClosesTheGap(): void
	{
		static::assertSame(2, $this->repository->updatePositionsWhenDeleted(1, 4));

		static::assertSame([1, 2, 3, 3, 4], $this->orderOf(1));
		static::assertSame([1, 2], $this->orderOf(2));
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateItemOrder(): void
	{
		static::assertSame(1, $this->repository->updateItemOrder(1, 9));

		static::assertSame([2, 3, 4, 5, 9], $this->orderOf(1));
	}

	/**
	 * @throws Exception
	 */
	public function testFindFileResourcesByPlaylistIdFindsUsedPlaylists(): void
	{
		$result = $this->repository->findFileResourcesByPlaylistId([2, 3, 99]);

		static::assertEqualsCanonicalizing([2, 3], array_map('intval', array_column($result, 'playlist_id')));
		static::assertSame([], $this->repository->findFileResourcesByPlaylistId([99]));
		static::assertSame([], $this->repository->findFileResourcesByPlaylistId([]));
	}

	/**
	 * The template 7 is used in two items, numeric file_resource values are not templates.
	 *
	 * @throws Exception
	 */
	public function testCountFileResourcesByTemplateId(): void
	{
		$result = $this->repository->countFileResourcesByTemplateId([7, 8]);

		static::assertCount(1, $result);
		static::assertSame(7, (int) $result[0]['template_id']);
		static::assertSame(2, (int) $result[0]['count']);
		static::assertSame([], $this->repository->countFileResourcesByTemplateId([]));
	}

	/**
	 * Items of an other type with a numeric resource must not count as playlist or template.
	 *
	 * @throws Exception
	 */
	public function testResourceLookupsRespectTheItemType(): void
	{
		// item 3 is a playlist item pointing to 2, not a template with id 2
		static::assertSame([], $this->repository->countFileResourcesByTemplateId([2, 3]));
		// item 5 is a template item pointing to 7, not a playlist with id 7
		static::assertSame([], $this->repository->findFileResourcesByPlaylistId([7]));
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<int>
	 */
	private function ids(array $rows, string $column): array
	{
		return array_map('intval', array_column($rows, $column));
	}

	/**
	 * @return list<int>
	 */
	private function orderOf(int $playlistId): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT item_order FROM playlists_items WHERE playlist_id = ? ORDER BY item_order, item_id', [$playlistId]));
	}
}
