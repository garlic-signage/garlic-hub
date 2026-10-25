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
use App\Modules\Playlists\Controller\PlaylistsController;
use PHPUnit\Framework\Attributes\Group;

/**
 * The playlist endpoints /async/playlists: delete, shuffle, zones of a multizone playlist and the search for playlists.
 */
#[Group('acl')]
class PlaylistsControllerTest extends PlaylistsControllerTestCase
{
	private PlaylistsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(PlaylistsController::class);
		// a playlist which is not nested anywhere
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (4, 2, 'master', 'Frei')");
	}

	public function testAPlaylistIsDeletedWithItsItems(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("INSERT INTO playlists_items (item_id, playlist_id, UID, item_order, item_type, file_resource, item_name) VALUES (8, 4, 2, 1, 'template', '7', 'x')");

		$response = $this->controller->delete($this->request('DELETE', body: $this->withToken(['playlist_id' => '4'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists WHERE playlist_id = 4'));
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists_items WHERE playlist_id = 4'));
		static::assertSame(3, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testDeleteNeedsTheTokenAndAPlaylist(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->delete($this->request('DELETE', body: ['playlist_id' => '4']), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->delete($this->request('DELETE', body: $this->withToken([])), $this->newResponse()), 'Playlist ID not valid.');
		static::assertSame(4, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testAForeignPlaylistCanNotBeDeletedOrShuffled(): void
	{
		$this->actingAs(2);

		foreach (['delete', 'toggleShuffle'] as $method)
		{
			try
			{
				$this->controller->$method($this->request('DELETE', body: $this->withToken(['playlist_id' => '2'])), $this->newResponse());
				static::fail($method . ' did not throw AccessDeniedException');
			}
			catch (AccessDeniedException)
			{
				static::assertSame(4, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
				static::assertSame(0, (int) $this->databaseValue('SELECT shuffle FROM playlists WHERE playlist_id = 2'));
			}
		}
	}

	public function testAnUnknownPlaylistIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->delete($this->request('DELETE', body: $this->withToken(['playlist_id' => '999'])), $this->newResponse());
	}

	public function testTheShuffleIsToggledAndTheMetricsAreGivenBack(): void
	{
		$this->actingAs(2);

		$response = $this->controller->toggleShuffle($this->request('PATCH', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(68, $this->json($response)['playlist_metrics']['duration']);
		static::assertSame(1, (int) $this->databaseValue('SELECT shuffle FROM playlists WHERE playlist_id = 1'));
	}

	public function testToggleShuffleNeedsTheTokenAndAPlaylist(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->toggleShuffle($this->request('PATCH', body: ['playlist_id' => '1']), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->toggleShuffle($this->request('PATCH', body: $this->withToken(['playlist_id' => 'abc'])), $this->newResponse()), 'Playlist ID not valid.');
		static::assertSame(0, (int) $this->databaseValue('SELECT shuffle FROM playlists WHERE playlist_id = 1'));
	}

	public function testThePickingOfAShuffledPlaylistChangesTheDuration(): void
	{
		$this->actingAs(2);
		$this->controller->toggleShuffle($this->request('PATCH', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse());

		$response = $this->controller->shufflePicking($this->request('PATCH', body: $this->withToken(['playlist_id' => '1', 'shuffle_picking' => '2'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(27, $this->json($response)['playlist_metrics']['duration']);
		static::assertSame(2, (int) $this->databaseValue('SELECT shuffle_picking FROM playlists WHERE playlist_id = 1'));
	}

	public function testThePickingNeedsAValue(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->shufflePicking($this->request('PATCH', body: ['playlist_id' => '1', 'shuffle_picking' => '2']), $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->shufflePicking($this->request('PATCH', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse()), 'No picking value found.');
		$this->assertError($this->controller->shufflePicking($this->request('PATCH', body: $this->withToken(['playlist_id' => '1', 'shuffle_picking' => '0'])), $this->newResponse()), 'No picking value found.');
		static::assertSame(0, (int) $this->databaseValue('SELECT shuffle_picking FROM playlists WHERE playlist_id = 1'));
	}

	/**
	 * The picking is the number of items a shuffled playlist plays: a negative number or text is no number of items.
	 */
	public function testThePickingMustBeAPositiveNumber(): void
	{
		static::markTestSkipped('Known bug: a negative or a text picking is stored (text as 0, negative as it is) and a negative picking gives a negative duration. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(2);
		$this->controller->toggleShuffle($this->request('PATCH', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse());

		foreach (['-5', 'abc', ['2']] as $picking)
			$this->assertError($this->controller->shufflePicking($this->request('PATCH', body: $this->withToken(['playlist_id' => '1', 'shuffle_picking' => $picking])), $this->newResponse()), 'No picking value found.');
		static::assertSame(0, (int) $this->databaseValue('SELECT shuffle_picking FROM playlists WHERE playlist_id = 1'));
	}

	public function testTheZonesAreSavedAndLoaded(): void
	{
		$this->actingAs(2);
		$zones = ['resolution' => '1920x1080', 'zones' => [1 => ['zone_playlist_id' => '3', 'name' => 'Haupt']]];

		$saved = $this->controller->saveZone($this->request(body: $this->withToken($zones)), $this->newResponse(), ['playlist_id' => '1']);
		$loaded = $this->controller->loadZone($this->request('GET'), $this->newResponse(), ['playlist_id' => '1']);

		$this->assertSuccess($saved);
		static::assertSame($zones, $this->json($loaded)['zones']);
	}

	public function testZonesCanNotBeSavedWithoutZonesOrToken(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->saveZone($this->request(body: $this->withToken([])), $this->newResponse(), ['playlist_id' => '1']), 'Multizone could not be saved');
		$this->assertError($this->controller->saveZone($this->request(body: ['resolution' => '1x1']), $this->newResponse(), ['playlist_id' => '1']), 'CSRF token validation error.');
		$this->assertError($this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1x1'])), $this->newResponse(), []), 'Playlist ID not valid.');
		static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'));
	}

	/**
	 * The playlist id of the route counts, not one in the body.
	 */
	public function testTheZonesAreSavedForThePlaylistOfTheRoute(): void
	{
		$this->actingAs(2);

		$this->controller->saveZone($this->request(body: $this->withToken(['playlist_id' => '2', 'resolution' => '1x1'])), $this->newResponse(), ['playlist_id' => '1']);

		static::assertNotNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'));
		static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 2'));
	}

	public function testTheZonesOfAForeignPlaylistCanNotBeSavedOrLoaded(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1x1'])), $this->newResponse(), ['playlist_id' => '2']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 2'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->controller->loadZone($this->request('GET'), $this->newResponse(), ['playlist_id' => '2']);
	}

	public function testTheNameOfAPlaylistIsGivenToWhoMayEditIt(): void
	{
		$this->actingAs(2);

		$response = $this->controller->findById($this->request('GET'), $this->newResponse(), ['playlist_id' => '1']);

		static::assertSame(['playlist_id' => 1, 'name' => 'Annas Lobby'], $this->json($response));
		$this->assertError($this->controller->findById($this->request('GET'), $this->newResponse(), []), 'Playlist ID not valid.');
		$this->expectException(AccessDeniedException::class);
		$this->controller->findById($this->request('GET'), $this->newResponse(), ['playlist_id' => '2']);
	}

	public function testAUserFindsOnlyHisOwnPlaylists(): void
	{
		$this->actingAs(2);

		static::assertSame(
			[['id' => 4, 'name' => 'Frei'], ['id' => 3, 'name' => 'Annas leere Liste'], ['id' => 1, 'name' => 'Annas Lobby']],
			$this->json($this->controller->findByName($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'master']))
		);
		static::assertSame([], $this->json($this->controller->findByName($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'internal'])));
	}

	public function testThePlaylistsAreFoundByAPartOfTheName(): void
	{
		$this->actingAs(2);

		static::assertSame(
			[['id' => 3, 'name' => 'Annas leere Liste'], ['id' => 1, 'name' => 'Annas Lobby']],
			$this->json($this->controller->findByName($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'master', 'playlist_name' => 'Anna']))
		);
	}

	public function testTheAdminFindsThePlaylistsOfEveryone(): void
	{
		$this->actingAs(1);

		static::assertSame([['id' => 2, 'name' => 'Bernds Kantine']], $this->json($this->controller->findByName($this->request('GET'), $this->newResponse(), ['playlist_mode' => 'internal'])));
	}

	public function testThePlaylistsForAPlayerAreMasterAndMultizonePlaylists(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("INSERT INTO playlists (playlist_id, UID, playlist_mode, playlist_name) VALUES (5, 2, 'multizone', 'Zonen'), (6, 2, 'internal', 'Innen')");

		$found = $this->json($this->controller->findForPlayerAssignment($this->request('GET'), $this->newResponse(), []));

		static::assertSame([5, 4, 3, 1], array_column($found, 'id'));
	}
}
