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
use App\Framework\Exceptions\TranslatableModuleException;
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

	public function testAPlaylistIdOfTheWrongTypeIsNoPlaylist(): void
	{
		$this->actingAs(2);

		foreach (['delete', 'toggleShuffle'] as $method)
		{
			foreach (['1abc', ['1'], '-1'] as $playlistId)
				$this->assertError($this->controller->$method($this->request('DELETE', body: $this->withToken(['playlist_id' => $playlistId])), $this->newResponse()), 'Playlist ID not valid.');
			static::assertSame(0, (int) $this->databaseValue('SELECT shuffle FROM playlists WHERE playlist_id = 1'));
		}
		$this->assertError($this->controller->loadZone($this->request('GET'), $this->newResponse(), ['playlist_id' => ['1']]), 'Playlist ID not valid.');
		$this->assertError($this->controller->findById($this->request('GET'), $this->newResponse(), ['playlist_id' => '1abc']), 'Playlist ID not valid.');
		static::assertSame(4, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	public function testWithoutABodyEverythingIsMissing(): void
	{
		$this->actingAs(2);
		$request = $this->request('PATCH')->withParsedBody(null);

		$this->assertError($this->controller->delete($request, $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->toggleShuffle($request, $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->shufflePicking($request, $this->newResponse()), 'CSRF token validation error.');
		$this->assertError($this->controller->saveZone($request, $this->newResponse(), ['playlist_id' => '1']), 'CSRF token validation error.');
	}

	public function testATokenOfTheWrongTypeIsAWrongToken(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->delete($this->request('DELETE', body: ['csrf_token' => ['x'], 'playlist_id' => '4']), $this->newResponse()), 'CSRF token validation error.');
		static::assertSame(4, (int) $this->databaseValue('SELECT COUNT(*) FROM playlists'));
	}

	/**
	 * The picking is the number of items a shuffled playlist plays: a negative number or text is no number of items.
	 */
	public function testThePickingMustBeAPositiveNumber(): void
	{
		$this->actingAs(2);
		$this->controller->toggleShuffle($this->request('PATCH', body: $this->withToken(['playlist_id' => '1'])), $this->newResponse());

		foreach (['-5', 'abc', ['2']] as $picking)
			$this->assertError($this->controller->shufflePicking($this->request('PATCH', body: $this->withToken(['playlist_id' => '1', 'shuffle_picking' => $picking])), $this->newResponse()), 'No picking value found.');
		static::assertSame(0, (int) $this->databaseValue('SELECT shuffle_picking FROM playlists WHERE playlist_id = 1'));
	}

	/**
	 * @return array<string,mixed> a zone as the editor sends it
	 */
	private function zone(int $playlistId, string $name = 'Haupt'): array
	{
		return ['zone_name' => $name, 'zone_left' => 0, 'zone_top' => 0, 'zone_width' => 1280, 'zone_height' => 720, 'zone_z-index' => 0, 'zone_bgcolor' => '#ff0000', 'zone_playlist_id' => $playlistId];
	}

	public function testTheZonesAreSavedAndLoaded(): void
	{
		$this->actingAs(2);
		$layout = ['resolution' => '1920x1080', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => [$this->zone(3), $this->zone(0, 'Frei')]];

		$saved = $this->controller->saveZone($this->request(body: $this->withToken($layout)), $this->newResponse(), ['playlist_id' => '1']);
		$loaded = $this->controller->loadZone($this->request('GET'), $this->newResponse(), ['playlist_id' => '1']);

		$this->assertSuccess($saved);
		static::assertSame($layout, $this->json($loaded)['zones']);
	}

	public function testOnlyTheLayoutIsSaved(): void
	{
		$this->actingAs(2);

		$this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1280x720', 'unknown' => 'x', 'zones' => [['unknown' => 'y'] + $this->zone(0)]])), $this->newResponse(), ['playlist_id' => '1']);

		static::assertSame(
			['resolution' => '1280x720', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => [$this->zone(0)]],
			unserialize((string) $this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'))
		);
	}

	public function testAnInvalidLayoutIsRejectedAndNothingIsSaved(): void
	{
		$this->actingAs(2);
		$invalid = [
			'no resolution'            => ['zones' => []],
			'a resolution as text'     => ['resolution' => 'big'],
			'a resolution of zero'     => ['resolution' => '0x1080'],
			'an unknown unit'          => ['resolution' => '1920x1080', 'export_unit' => 'inch'],
			'zones as text'            => ['resolution' => '1920x1080', 'zones' => 'all'],
			'a zone without a color'   => ['resolution' => '1920x1080', 'zones' => [['zone_bgcolor' => null] + $this->zone(0)]],
			'a color with a quote'     => ['resolution' => '1920x1080', 'zones' => [['zone_bgcolor' => 'red" onload="x'] + $this->zone(0)]],
			'a negative size'          => ['resolution' => '1920x1080', 'zones' => [['zone_width' => -1] + $this->zone(0)]],
			'a playlist as array'      => ['resolution' => '1920x1080', 'zones' => [['zone_playlist_id' => [3]] + $this->zone(0)]],
		];

		foreach ($invalid as $label => $layout)
			$this->assertError($this->controller->saveZone($this->request(body: $this->withToken($layout)), $this->newResponse(), ['playlist_id' => '1']), 'Invalid zone data.');
		static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'));
	}

	public function testZonesCanNotBeSavedWithoutAToken(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->saveZone($this->request(body: ['resolution' => '1x1']), $this->newResponse(), ['playlist_id' => '1']), 'CSRF token validation error.');
		$this->assertError($this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1x1'])), $this->newResponse(), []), 'Playlist ID not valid.');
		static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'));
	}

	/**
	 * The playlists of the zones are played on the players of this playlist: only playlists the user may edit.
	 */
	public function testAZoneCanNotPlayAForeignPlaylist(): void
	{
		$this->actingAs(2);
		$layout = ['resolution' => '1920x1080', 'zones' => [$this->zone(3), $this->zone(2)]];

		try
		{
			$this->controller->saveZone($this->request(body: $this->withToken($layout)), $this->newResponse(), ['playlist_id' => '1']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertNull($this->databaseValue('SELECT layout FROM playlists WHERE playlist_id = 1'));
		}
	}

	public function testTheAdminMayPlayEveryPlaylistInAZone(): void
	{
		$this->actingAs(1);

		$this->assertSuccess($this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1920x1080', 'zones' => [$this->zone(2)]])), $this->newResponse(), ['playlist_id' => '1']));
	}

	public function testAZoneCanNotPlayAnUnknownPlaylist(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1920x1080', 'zones' => [$this->zone(999)]])), $this->newResponse(), ['playlist_id' => '1']);
	}

	public function testAZoneCanNotPlayThePlaylistItself(): void
	{
		$this->actingAs(2);

		$this->expectException(TranslatableModuleException::class);
		$this->controller->saveZone($this->request(body: $this->withToken(['resolution' => '1920x1080', 'zones' => [$this->zone(1)]])), $this->newResponse(), ['playlist_id' => '1']);
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
