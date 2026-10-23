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

namespace Tests\Integration\Security\Playlists;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Services\ItemsService;
use PHPUnit\Framework\Attributes\Group;
use Throwable;
use Tests\Integration\ContainerTestCase;

/**
 * Who may change which playlist items. Fixtures: items 1 to 5 are in the playlist 1 of anna,
 * items 6 and 7 in the playlist 2 of bernd.
 *
 * The item ids come from the request. Everything must be checked against the playlist the
 * item really belongs to, not against a playlist id the client sends along.
 */
#[Group('security')]
class ItemsAccessTest extends ContainerTestCase
{
	private ItemsService $itemsService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('security/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->itemsService = $this->container->get(ItemsService::class);
	}

	public function testOwnerMayDeleteAnItemOfHisPlaylist(): void
	{
		$this->actingAs(2);

		$result = $this->itemsService->delete(1, 1);

		static::assertSame(1, $result['delete_id']);
		static::assertSame([2, 3, 4, 5, 6, 7], $this->itemIds());
		static::assertSame([1, 2, 3, 4], $this->orderOfPlaylist(1));
	}

	public function testAdminMayDeleteAnItemOfAnyPlaylist(): void
	{
		$this->actingAs(1);

		$this->itemsService->delete(2, 6);

		static::assertSame([1, 2, 3, 4, 5, 7], $this->itemIds());
	}

	public function testUserMayNotDeleteAnItemOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->delete(2, 6));

		static::assertSame([1, 2, 3, 4, 5, 6, 7], $this->itemIds());
	}

	/**
	 * The client sends its own playlist id but the id of a foreign item.
	 */
	public function testUserMayNotDeleteAForeignItemByPassingTheOwnPlaylistId(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->delete(1, 6));

		static::assertSame([1, 2, 3, 4, 5, 6, 7], $this->itemIds());
		static::assertSame([1, 2], $this->orderOfPlaylist(2));
	}

	public function testUserMayNotReorderForeignItemsByPassingTheOwnPlaylistId(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->updateItemOrder(1, ['7', '6']));

		static::assertSame([1, 2], $this->orderOfPlaylist(2));
		static::assertSame(6, $this->itemAtOrder(2, 1));
	}

	public function testOwnerMayReorderTheItemsOfHisPlaylist(): void
	{
		$this->actingAs(2);

		$this->itemsService->updateItemOrder(1, ['5', '4', '3', '2', '1']);

		static::assertSame(5, $this->itemAtOrder(1, 0));
		static::assertSame(1, $this->itemAtOrder(1, 4));
	}

	public function testUserMayNotChangeAnItemOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->update(6, ['item_name' => 'gehackt']));

		static::assertSame('Annas leere Liste', $this->connection->fetchOne('SELECT item_name FROM playlists_items WHERE item_id = 6'));
	}

	public function testUserMayNotChangeTheDurationOfAnItemOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->updateItemDuration(6, 99));

		static::assertSame(3, (int) $this->connection->fetchOne('SELECT item_duration FROM playlists_items WHERE item_id = 6'));
	}

	public function testUserMayNotReadAnItemOfAForeignPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->itemsService->fetchItemById(7));
	}

	public function testOwnerMayChangeAnItemOfHisPlaylist(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->itemsService->update(1, ['item_name' => 'neu']));
		static::assertSame('neu', $this->connection->fetchOne('SELECT item_name FROM playlists_items WHERE item_id = 1'));
	}

	public function testUnknownItemIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->itemsService->update(999, ['item_name' => 'x']);
	}

	/**
	 * Passes only if the call is rejected with an access denied or not found error.
	 *
	 * @param callable():mixed $call
	 */
	private function expectDenied(callable $call): void
	{
		try
		{
			$call();
		}
		catch (AccessDeniedException|NotFoundException)
		{
			static::addToAssertionCount(1);

			return;
		}
		catch (Throwable $e)
		{
			static::fail('Expected a rejection by the ACL but got ' . $e::class . ': ' . $e->getMessage());
		}

		static::fail('The call was not rejected. A user changed data of another user.');
	}

	/**
	 * @return list<int>
	 */
	private function itemIds(): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT item_id FROM playlists_items ORDER BY item_id'));
	}

	/**
	 * @return list<int>
	 */
	private function orderOfPlaylist(int $playlistId): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT item_order FROM playlists_items WHERE playlist_id = ? ORDER BY item_order', [$playlistId]));
	}

	private function itemAtOrder(int $playlistId, int $order): int
	{
		return (int) $this->connection->fetchOne('SELECT item_id FROM playlists_items WHERE playlist_id = ? AND item_order = ?', [$playlistId, $order]);
	}
}
