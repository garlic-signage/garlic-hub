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

namespace Tests\Integration\Modules\Playlists\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Controller\ItemsController;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * The item endpoints /async/playlists/items of the playlist composer: load, insert, change, order and delete the items.
 */
#[Group('acl')]
class ItemsControllerTest extends PlaylistsControllerTestCase
{
	private ItemsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ItemsController::class);
	}

	public function testTheComposerGetsTheItemsAndTheMetrics(): void
	{
		$this->actingAs(2);

		$response = $this->controller->loadItems($this->request('GET'), $this->newResponse(), ['playlist_id' => '1']);

		$this->assertSuccess($response);
		$data = $this->json($response)['data'];
		static::assertSame([1, 2, 3, 4, 5], array_map(intval(...), array_column($data['items'], 'item_id')));
		static::assertSame(68, $data['playlist_metrics']['duration']);
		static::assertSame('Annas Lobby', $data['playlist']['playlist_name']);
	}

	public function testTheItemsAreNotGivenToAnotherUser(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->controller->loadItems($this->request('GET'), $this->newResponse(), ['playlist_id' => '2']);
	}

	public function testAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->loadItems($this->request('GET'), $this->newResponse(), ['playlist_id' => '999']);
	}

	public function testWithoutAPlaylistIdNothingIsLoaded(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->loadItems($this->request('GET'), $this->newResponse(), []), 'Playlist ID not valid.');
		$this->assertError($this->controller->loadItems($this->request('GET'), $this->newResponse(), ['playlist_id' => 'abc']), 'Playlist ID not valid.');
	}

	public function testAMediaIsInsertedAtThePosition(): void
	{
		$this->actingAs(2);

		$response = $this->controller->insert($this->request(body: ['playlist_id' => '1', 'id' => 'media-id-0000003', 'source' => 'mediapool', 'position' => '2']), $this->newResponse());

		$this->assertSuccess($response);
		$data = $this->json($response)['data'];
		static::assertSame('film.mp4', $data['item']['item_name']);
		static::assertSame(6, $data['playlist_metrics']['count_items']);
		static::assertSame(2, (int) $this->databaseValue('SELECT item_order FROM playlists_items WHERE item_id = ?', $data['item']['item_id']));
	}

	public function testAPlaylistIsInserted(): void
	{
		$this->actingAs(2);

		$response = $this->controller->insert($this->request(body: ['playlist_id' => '1', 'id' => '3', 'source' => 'playlist', 'position' => '1']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame('Annas leere Liste', $this->json($response)['data']['item']['item_name']);
	}

	public function testInsertNeedsAllParameters(): void
	{
		$this->actingAs(2);
		$body = ['playlist_id' => '1', 'id' => 'media-id-0000003', 'source' => 'mediapool', 'position' => '1'];

		$this->assertError($this->controller->insert($this->request(body: ['playlist_id' => '0'] + $body), $this->newResponse()), 'Playlist ID not valid.');
		$this->assertError($this->controller->insert($this->request(body: ['id' => ''] + $body), $this->newResponse()), 'Content ID not valid.');
		$this->assertError($this->controller->insert($this->request(body: ['source' => ''] + $body), $this->newResponse()), 'Source not valid.');
		$this->assertError($this->controller->insert($this->request(body: ['position' => '0'] + $body), $this->newResponse()), 'Position not valid.');
		$this->assertError($this->controller->insert($this->request(body: ['source' => 'unknown'] + $body), $this->newResponse()), 'Error inserting item.');
		static::assertSame(5, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 1'));
	}

	public function testNothingIsInsertedIntoAForeignPlaylist(): void
	{
		$this->actingAs(3);

		try
		{
			$this->controller->insert($this->request(body: ['playlist_id' => '1', 'id' => 'media-id-0000003', 'source' => 'mediapool', 'position' => '1']), $this->newResponse());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(5, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 1'));
		}
	}

	public function testTheNameOfAnItemIsChanged(): void
	{
		$this->actingAs(2);

		$response = $this->controller->edit($this->request(body: ['item_id' => '1', 'name' => 'item_name', 'value' => 'Neu']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame('Neu', $this->databaseValue('SELECT item_name FROM playlists_items WHERE item_id = 1'));
	}

	public function testTheDurationOfAnItemIsRoundedAndGivenBack(): void
	{
		$this->actingAs(2);

		$response = $this->controller->edit($this->request(body: ['item_id' => '1', 'name' => 'item_duration', 'value' => '12.6']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(13, $this->json($response)['data']);
		static::assertSame(13, (int) $this->databaseValue('SELECT item_duration FROM playlists_items WHERE item_id = 1'));
	}

	public function testTheDurationIsCutByTheTimeLimit(): void
	{
		$this->actingAs(3);

		$response = $this->controller->edit($this->request(body: ['item_id' => '6', 'name' => 'item_duration', 'value' => '5000']), $this->newResponse());

		$this->assertSuccess($response);
		static::assertLessThan(600, $this->json($response)['data']);
	}

	public function testEditNeedsTheParametersAndAKnownField(): void
	{
		$this->actingAs(2);
		$body = ['item_id' => '1', 'name' => 'item_name', 'value' => 'Neu'];

		$this->assertError($this->controller->edit($this->request(body: ['item_id' => '0'] + $body), $this->newResponse()), 'Item ID not valid.');
		$this->assertError($this->controller->edit($this->request(body: ['name' => ''] + $body), $this->newResponse()), 'No parameter name.');
		$this->assertError($this->controller->edit($this->request(body: ['value' => ''] + $body), $this->newResponse()), 'No parameter value.');
		$this->assertError($this->controller->edit($this->request(body: ['name' => 'item_type'] + $body), $this->newResponse()), 'No valid parametername.');
		static::assertSame('bild.jpg', $this->databaseValue('SELECT item_name FROM playlists_items WHERE item_id = 1'));
	}

	/**
	 * Only the name and the duration may be changed, never the owner, the type or the playlist of an item.
	 */
	public function testNoOtherFieldOfAnItemCanBeChanged(): void
	{
		$this->actingAs(2);

		foreach (['playlist_id' => '2', 'UID' => '3', 'item_type' => 'playlist', 'file_resource' => 'x', 'content_data' => 'x'] as $field => $value)
			$this->assertError($this->controller->edit($this->request(body: ['item_id' => '1', 'name' => $field, 'value' => $value]), $this->newResponse()), 'No valid parametername.');
		static::assertSame([1, 2, 'mediapool', 'checksum-image'], array_map(fn(mixed $value) => is_numeric($value) ? (int) $value : $value, (array) $this->connection->fetchNumeric('SELECT playlist_id, UID, item_type, file_resource FROM playlists_items WHERE item_id = 1')));
	}

	public function testAnItemOfAForeignPlaylistCanNotBeChanged(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->controller->edit($this->request(body: ['item_id' => '6', 'name' => 'item_name', 'value' => 'x']), $this->newResponse());
	}

	public function testAnItemIsGivenToWhoMayEditItsPlaylist(): void
	{
		$this->actingAs(2);

		$response = $this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '2']);

		$this->assertSuccess($response);
		static::assertSame('film.mp4', $this->json($response)['item']['item_name']);
		static::assertSame(40, $this->json($response)['item']['default_duration']);
	}

	public function testAnItemIsNotGivenToAnotherUserOrWithoutAnId(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->fetch($this->request('GET'), $this->newResponse(), []), 'Item ID not valid.');
		$this->expectException(AccessDeniedException::class);
		$this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '7']);
	}

	public function testTheOrderOfTheItemsIsSaved(): void
	{
		$this->actingAs(2);

		$response = $this->controller->updateItemOrders($this->request(body: ['playlist_id' => '1', 'items_positions' => ['5', '4', '3', '2', '1']]), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame([5, 4, 3, 2, 1], array_map('intval', $this->connection->fetchFirstColumn('SELECT item_id FROM playlists_items WHERE playlist_id = 1 ORDER BY item_order')));
	}

	public function testTheOrderNeedsThePlaylistAndTheItems(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->updateItemOrders($this->request(body: ['items_positions' => ['2', '1']]), $this->newResponse()), 'Playlist ID not valid.');
		$this->assertError($this->controller->updateItemOrders($this->request(body: ['playlist_id' => '1']), $this->newResponse()), 'Items Position array is not valid.');
		$this->assertError($this->controller->updateItemOrders($this->request(body: ['playlist_id' => '1', 'items_positions' => []]), $this->newResponse()), 'Items Position array is not valid.');
	}

	public function testItemsOfAnotherPlaylistCanNotBeOrdered(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->updateItemOrders($this->request(body: ['playlist_id' => '1', 'items_positions' => ['7', '6']]), $this->newResponse());
	}

	public function testAnItemIsDeletedWithTheNewMetrics(): void
	{
		$this->actingAs(2);

		$response = $this->controller->delete($this->request(body: ['playlist_id' => '1', 'item_id' => '1']), $this->newResponse());

		$this->assertSuccess($response);
		$data = $this->json($response)['data'];
		static::assertSame(1, $data['delete_id']);
		static::assertSame(58, $data['playlist_metrics']['duration']);
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items WHERE item_id = 1'));
	}

	public function testDeleteNeedsThePlaylistAndTheItem(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->delete($this->request(body: ['item_id' => '1']), $this->newResponse()), 'Playlist ID not valid.');
		$this->assertError($this->controller->delete($this->request(body: ['playlist_id' => '1']), $this->newResponse()), 'Item ID not valid.');
		static::assertSame(7, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items'));
	}

	public function testAnItemOfAnotherPlaylistCanNotBeDeletedByThePlaylistOfTheUser(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->delete($this->request(body: ['playlist_id' => '1', 'item_id' => '6']), $this->newResponse());
	}

	public function testAnIdMustBeANumberNotOnlyStartWithOne(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->loadItems($this->request('GET'), $this->newResponse(), ['playlist_id' => '1abc']), 'Playlist ID not valid.');
		$this->assertError($this->controller->fetch($this->request('GET'), $this->newResponse(), ['item_id' => '2 or 1']), 'Item ID not valid.');
		$this->assertError($this->controller->delete($this->request(body: ['playlist_id' => '-1', 'item_id' => '1']), $this->newResponse()), 'Playlist ID not valid.');
		$this->assertError($this->controller->delete($this->request(body: ['playlist_id' => '1', 'item_id' => '1.0']), $this->newResponse()), 'Item ID not valid.');
		static::assertSame(7, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items'));
	}

	public function testANumberFromJsonIsAnId(): void
	{
		$this->actingAs(2);

		$this->assertSuccess($this->controller->delete($this->request(body: ['playlist_id' => 1, 'item_id' => 1]), $this->newResponse()));
		$this->assertSuccess($this->controller->insert($this->request(body: ['playlist_id' => 1, 'id' => 3, 'source' => 'playlist', 'position' => 1]), $this->newResponse()));
	}

	public function testWithoutABodyEverythingIsMissing(): void
	{
		$this->actingAs(2);
		$request = $this->request()->withParsedBody(null);

		foreach (['insert', 'edit', 'updateItemOrders', 'delete'] as $method)
			static::assertSame(400, $this->controller->$method($request, $this->newResponse())->getStatusCode(), $method);
		static::assertSame(7, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items'));
	}

	public function testANameOfZeroAndADurationOfZeroAreValues(): void
	{
		$this->actingAs(2);

		$this->assertSuccess($this->controller->edit($this->request(body: ['item_id' => '1', 'name' => 'item_name', 'value' => '0']), $this->newResponse()));
		$this->assertSuccess($this->controller->edit($this->request(body: ['item_id' => '2', 'name' => 'item_duration', 'value' => '0']), $this->newResponse()));

		static::assertSame('0', $this->databaseValue('SELECT item_name FROM playlists_items WHERE item_id = 1'));
		static::assertSame(0, (int) $this->databaseValue('SELECT item_duration FROM playlists_items WHERE item_id = 2'));
	}

	public function testADurationMustBeANumberWhichIsNotNegative(): void
	{
		$this->actingAs(2);

		foreach (['-5', 'abc', '1e', ['1'], ''] as $value)
			$this->assertError($this->controller->edit($this->request(body: ['item_id' => '1', 'name' => 'item_duration', 'value' => $value]), $this->newResponse()), 'No parameter value.');
		static::assertSame(10, (int) $this->databaseValue('SELECT item_duration FROM playlists_items WHERE item_id = 1'));
	}

	public function testTheOrderNeedsAListOfIds(): void
	{
		$this->actingAs(2);

		foreach (['abc', [['1'], '2'], ['1', 'x'], ['-1'], [1.5]] as $positions)
			$this->assertError($this->controller->updateItemOrders($this->request(body: ['playlist_id' => '1', 'items_positions' => $positions]), $this->newResponse()), 'Items Position array is not valid.');
		static::assertSame([1, 2, 3, 4, 5], array_map('intval', $this->connection->fetchFirstColumn('SELECT item_id FROM playlists_items WHERE playlist_id = 1 ORDER BY item_order')));
	}

	/**
	 * What the browser can not send but a script can: values of the wrong type are no valid values.
	 */
	public function testValuesOfTheWrongTypeAreRejectedLikeMissingValues(): void
	{
		$this->actingAs(2);
		$insert = ['playlist_id' => '1', 'id' => 'media-id-0000003', 'source' => 'mediapool', 'position' => '1'];

		$cases = [
			['insert', ['id' => ['x']] + $insert, 'Content ID not valid.'],
			['insert', ['source' => ['x']] + $insert, 'Source not valid.'],
			['insert', ['position' => ['1']] + $insert, 'Position not valid.'],
			['insert', ['playlist_id' => ['1']] + $insert, 'Playlist ID not valid.'],
			['edit', ['item_id' => '1', 'name' => 'item_name', 'value' => ['x']], 'No parameter value.'],
			['edit', ['item_id' => '1', 'name' => 'item_duration', 'value' => 'abc'], 'No parameter value.'],
			['updateItemOrders', ['playlist_id' => '1', 'items_positions' => 'abc'], 'Items Position array is not valid.'],
			['updateItemOrders', ['playlist_id' => '1', 'items_positions' => [['1'], '2']], 'Items Position array is not valid.'],
		];
		foreach ($cases as [$method, $body, $message])
		{
			try
			{
				$this->assertError($this->controller->$method($this->request(body: $body), $this->newResponse()), $message);
			}
			catch (Throwable $e)
			{
				static::fail($method . ': ' . $e::class . ' ' . $e->getMessage());
			}
		}
		static::assertSame(7, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items'));
		static::assertSame(10, (int) $this->databaseValue('SELECT item_duration FROM playlists_items WHERE item_id = 1'));
	}
}
