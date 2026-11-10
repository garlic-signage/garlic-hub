<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Config\ConfigOverrides;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Controller\PlaylistVersionsController;
use App\Shared\Versioning\UnitVersionsRepository;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\PlaylistVersionsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Modules\Playlists\BuildsExportService;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The versions of the playlists: the exported states. Users: 1 admin, 2 anna, 3 bernd.
 * Playlist 1 of anna: image (item 1), video (2), nested playlist 2 (3), pdf (4), template (5).
 */
#[Group('acl')]
class PlaylistVersionsTest extends ContainerTestCase
{
	use BuildsExportService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
	}

	/**
	 * @param array<string,string> $versioning
	 */
	private function enable(array $versioning = []): void
	{
		// before the first service with the versions is created
		$this->container->get(ConfigOverrides::class)->save('system', [
			'Scheduler'  => ['enabled' => '1'],
			'Audit'      => ['enabled' => '1'],
			'Versioning' => ['enabled' => '1'] + $versioning
		]);
	}

	private function service(): PlaylistVersionsService
	{
		return $this->container->get(PlaylistVersionsService::class);
	}

	private function export(int $playlistId): void
	{
		// what the export records, without the SMIL files
		$this->service()->recordExport($this->container->get(PlaylistsService::class)->loadPureById($playlistId));
	}

	/**
	 * @return list<array{item_id:int, item_order:int, item_name:string}>
	 */
	private function items(int $playlistId): array
	{
		$items = [];
		foreach ($this->connection->fetchAllAssociative('SELECT item_id, item_order, item_name FROM playlists_items WHERE playlist_id = ? ORDER BY item_order', [$playlistId]) as $row)
			$items[] = ['item_id' => (int) $row['item_id'], 'item_order' => (int) $row['item_order'], 'item_name' => (string) $row['item_name']];

		return $items;
	}

	/**
	 * @return list<int>
	 */
	private function versions(int $playlistId): array
	{
		return array_column($this->service()->findVersions($playlistId), 'version');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function json(ResponseInterface $response): array
	{
		$json = json_decode((string) $response->getBody(), true);

		return is_array($json) ? $json : [];
	}

	private function restoreRequest(int $version): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/async/playlists/versions/1')->withParsedBody(['version' => (string) $version]);

		return $this->container->get(PlaylistVersionsController::class)->restore($request, new Response(), ['playlist_id' => '1']);
	}

	/**
	 * Changes the recorded version 1, like an old one or a broken one would be.
	 *
	 * @param callable(array<string,mixed>):array<string,mixed> $change
	 */
	private function changeSnapshot(callable $change): void
	{
		$snapshot = json_decode((string) $this->connection->fetchOne('SELECT snapshot FROM unit_versions WHERE version = 1'), true);
		static::assertIsArray($snapshot);
		$this->connection->update('unit_versions', ['snapshot' => json_encode($change($snapshot))], ['version' => 1]);
	}

	public function testNothingIsRecordedByDefault(): void
	{
		$this->actingAs(2);

		$this->export(1);

		static::assertFalse($this->service()->isEnabled());
		static::assertSame([], $this->versions(1));
	}

	public function testAnExportWithoutChangeIsNoNewVersion(): void
	{
		$this->enable();
		$this->actingAs(2);

		$this->export(1);
		$this->export(1);
		$this->connection->update('playlists', ['shuffle' => 1], ['playlist_id' => 1]);
		$this->export(1);

		static::assertSame([2, 1], $this->versions(1));
	}

	public function testTheItemsComeBackWithTheirIds(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$before = $this->items(1);
		$this->connection->delete('playlists_items', ['item_id' => 2]);
		$this->connection->update('playlists_items', ['item_name' => 'umbenannt'], ['item_id' => 1]);

		$omitted = $this->service()->restoreVersion(1, 1);

		static::assertSame([], $omitted);
		static::assertSame($before, $this->items(1));
	}

	public function testTheSettingsOfThePlaylistComeBackButNotItsName(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->update('playlists', ['shuffle' => 1, 'time_limit' => 300, 'playlist_name' => 'Neu'], ['playlist_id' => 1]);

		$this->service()->restoreVersion(1, 1);

		static::assertSame(['shuffle' => 0, 'time_limit' => 0, 'playlist_name' => 'Neu'],
			$this->connection->fetchAssociative('SELECT CAST(shuffle AS INTEGER) AS shuffle, CAST(time_limit AS INTEGER) AS time_limit, playlist_name FROM playlists WHERE playlist_id = 1'));
	}

	public function testItemsWhoseResourceIsGoneAreLeftOutAndNamed(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		// a media marked as deleted is still there, a nested playlist which is gone is not
		$this->connection->update('mediapool_files', ['deleted' => 1], ['checksum' => 'checksum-video']);
		$this->connection->delete('mediapool_files', ['checksum' => 'checksum-pdf']);
		$this->connection->delete('playlists_items', ['playlist_id' => 2]);
		$this->connection->delete('playlists', ['playlist_id' => 2]);

		$omitted = $this->service()->restoreVersion(1, 1);

		static::assertSame(['Bernds Kantine', 'doc.pdf'], $omitted);
		static::assertSame([[1, 1, 'bild.jpg'], [2, 2, 'film.mp4'], [5, 3, 'Vorlage']], array_map('array_values', $this->items(1)));
	}

	public function testANestedPlaylistWhichContainsThePlaylistByNowIsLeftOut(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->export(1);
		// playlist 2 is taken out of 1, then 1 goes into 2
		$this->connection->delete('playlists_items', ['item_id' => 3]);
		$this->connection->insert('playlists_items', ['playlist_id' => 2, 'UID' => 1, 'item_order' => 3, 'item_type' => 'playlist', 'file_resource' => '1', 'item_name' => 'Annas Lobby']);

		$omitted = $this->service()->restoreVersion(1, 1);

		static::assertSame(['Bernds Kantine'], $omitted);
		static::assertNotContains('Bernds Kantine', array_column($this->items(1), 'item_name'));
	}

	public function testThePositionsStartAtTheFirstOneEvenWhenItIsLeftOut(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->delete('mediapool_files', ['checksum' => 'checksum-image']);

		$this->service()->restoreVersion(1, 1);

		static::assertSame([1, 2, 3, 4], array_column($this->items(1), 'item_order'));
	}

	/**
	 * The zones are exported (and recorded) by their own export only, see docs/multizone-export.md.
	 */
	public function testTheExportOfAMultizonePlaylistRecordsItselfButNotItsZones(): void
	{
		$this->enable();
		$this->actingAs(2);
		$workspace = new MediaWorkspace();
		$this->connection->executeStatement("UPDATE playlists SET playlist_mode = 'multizone', layout = ? WHERE playlist_id = 3", [serialize(['resolution' => '1920x1080', 'zones' => ['screen1' => ['zone_playlist_id' => 1]]])]);

		try
		{
			$exportService = $this->buildExportService($workspace);
			$exportService->exportToSmil(1);
			$exportService->exportToSmil(3);
		}
		finally
		{
			$workspace->remove();
		}

		static::assertSame([1], $this->versions(1));
		static::assertSame([1], $this->versions(3));
	}

	public function testAColumnWhichIsGoneByNowIsLeftOutOfTheRestore(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		// a version from before a change of the table
		$snapshot = json_decode((string) $this->connection->fetchOne('SELECT snapshot FROM unit_versions'), true);
		static::assertIsArray($snapshot);
		$snapshot['items'][0]['gone_column'] = 'old';
		$this->connection->update('unit_versions', ['snapshot' => json_encode($snapshot)], ['version' => 1]);

		$this->service()->restoreVersion(1, 1);

		static::assertSame([1, 2, 3, 4, 5], array_column($this->items(1), 'item_id'));
	}

	public function testANameWithABrokenCharacterDoesNotStopTheExport(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->connection->update('playlists_items', ['item_name' => "bild\xC3.jpg"], ['item_id' => 1]);

		$this->export(1);

		static::assertSame([1], $this->versions(1));
	}

	public function testARestoreCanBeUndone(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->delete('playlists_items', ['item_id' => 5]);
		$withoutTemplate = $this->items(1);

		$this->service()->restoreVersion(1, 1);
		static::assertSame([2, 1], $this->versions(1));
		$this->service()->restoreVersion(1, 2);

		static::assertSame($withoutTemplate, $this->items(1));
	}

	public function testAnUnknownVersionIsNotFound(): void
	{
		$this->enable();
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->service()->restoreVersion(1, 9);
	}

	public function testTheVersionsGoWithThePlaylist(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(3);

		$this->container->get(PlaylistsService::class)->delete(3);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(1) FROM unit_versions'));
	}

	public function testTheRestoreOpensTheComposerAndNamesTheLeftOutItems(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->delete('mediapool_files', ['checksum' => 'checksum-pdf']);
		$_SESSION['slimFlash'] = [];

		$json = $this->json($this->restoreRequest(1));

		static::assertSame(['success' => true, 'redirect' => '/playlists/compose/1', 'omitted' => ['doc.pdf']], $json);
		// for the composer, the next page
		static::assertSame(['Not restored as they no longer exist: doc.pdf'], $_SESSION['slimFlash']['error'] ?? []);
		static::assertCount(1, $_SESSION['slimFlash']['success'] ?? []);
		static::assertSame('restored',$this->connection->fetchOne("SELECT 'restored' FROM audit_log WHERE action = 'version_restored' AND unit_id = '1'"));
	}

	/**
	 * A version recorded before the versioning was switched off is not restored.
	 */
	public function testWhileTheVersioningIsOffNothingIsRestored(): void
	{
		$this->actingAs(2);
		$snapshot = ['playlist' => ['shuffle' => 0], 'items' => $this->connection->fetchAllAssociative('SELECT * FROM playlists_items WHERE playlist_id = 1')];
		new UnitVersionsRepository($this->connection)->insertVersion('playlists', '1', 1, 2, json_encode($snapshot, JSON_THROW_ON_ERROR));
		$this->connection->executeStatement("UPDATE playlists_items SET item_name = 'geändert' WHERE item_id = 1");

		$json = $this->json($this->restoreRequest(1));

		static::assertFalse($json['success']);
		static::assertSame('geändert', $this->connection->fetchOne('SELECT item_name FROM playlists_items WHERE item_id = 1'));
	}

	public function testWhoMayNotEditThePlaylistGetsNoVersions(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->actingAs(3);

		$list = $this->json($this->container->get(PlaylistVersionsController::class)->list(
			new ServerRequestFactory()->createServerRequest('GET', '/async/playlists/versions/1'), new Response(), ['playlist_id' => '1']));

		static::assertFalse($list['success']);
		static::assertFalse($this->json($this->restoreRequest(1))['success']);
	}

	public function testOnlyTheItemsOfThePlaylistAreReplaced(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$other = $this->items(2);

		$this->service()->restoreVersion(1, 1);

		static::assertSame($other, $this->items(2));
		static::assertSame([6, 7], array_column($other, 'item_id'));
	}

	public function testTheRestoreIsCommittedAndInTheAuditLog(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);

		$this->service()->restoreVersion(1, 1);

		static::assertFalse($this->connection->isTransactionActive());
		static::assertSame(['UID' => 2, 'module' => 'playlists', 'action' => 'version_restored', 'unit_id' => '1', 'details' => '{"version":1}'],
			$this->connection->fetchAssociative('SELECT UID, module, action, unit_id, details FROM audit_log'));
	}

	public function testARestoreIsAChangeAndStartsTheReleaseAgain(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->update('playlists', ['release_status' => 'released'], ['playlist_id' => 1]);

		$this->service()->restoreVersion(1, 1);

		static::assertSame('in_progress', $this->connection->fetchOne('SELECT release_status FROM playlists WHERE playlist_id = 1'));
	}

	public function testAVersionHoldsOnlyTheSettingsOfTheComposer(): void
	{
		$this->enable();
		$this->actingAs(2);

		$this->export(1);

		$snapshot = json_decode((string) $this->connection->fetchOne('SELECT snapshot FROM unit_versions'), true);
		static::assertIsArray($snapshot);
		static::assertIsArray($snapshot['playlist']);
		static::assertEqualsCanonicalizing(['time_limit', 'shuffle', 'shuffle_picking', 'layout', 'external_playlist_link'], array_keys($snapshot['playlist']));
	}

	public function testOtherFieldsInAVersionDoNotComeBack(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->changeSnapshot(static function (array $snapshot): array
		{
			$snapshot['playlist']['playlist_name'] = 'Alt';
			$snapshot['playlist']['UID'] = 3;
			return $snapshot;
		});

		$this->service()->restoreVersion(1, 1);

		static::assertSame(['UID' => 2, 'playlist_name' => 'Annas Lobby'],
			$this->connection->fetchAssociative('SELECT CAST(UID AS INTEGER) AS UID, playlist_name FROM playlists WHERE playlist_id = 1'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function brokenParts(): array
	{
		return ['without the playlist' => ['playlist'], 'without the items' => ['items']];
	}

	#[DataProvider('brokenParts')]
	public function testABrokenVersionIsNotFound(string $part): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->changeSnapshot(static function (array $snapshot) use ($part): array
		{
			unset($snapshot[$part]);
			return $snapshot;
		});

		try
		{
			$this->service()->restoreVersion(1, 1);
			static::fail('A broken version must not be restored.');
		}
		catch (NotFoundException $e)
		{
			static::assertSame('version_not_found', $e->getTranslationKey());
		}
		static::assertSame([1, 2, 3, 4, 5], array_column($this->items(1), 'item_id'));
	}

	public function testEntriesOfAVersionWhichAreNoItemsAreSkipped(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$before = $this->items(1);
		$this->changeSnapshot(static function (array $snapshot): array
		{
			array_unshift($snapshot['items'], 'kein Eintrag');
			return $snapshot;
		});

		$this->service()->restoreVersion(1, 1);

		static::assertSame($before, $this->items(1));
	}

	public function testThePositionsCountFromTheFirstOneOfTheVersion(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->connection->executeStatement('UPDATE playlists_items SET item_order = item_order + 2 WHERE playlist_id = 1');
		$this->export(1);

		$this->service()->restoreVersion(1, 1);

		static::assertSame([3, 4, 5, 6, 7], array_column($this->items(1), 'item_order'));
	}

	/**
	 * An item id which another playlist has by now, or none at all, gets a new one: the other playlist keeps its item.
	 */
	public function testAnItemGetsANewIdWhenItsIdIsTakenOrMissing(): void
	{
		$this->enable();
		$this->actingAs(2);
		$this->export(1);
		$this->connection->update('playlists_items', ['playlist_id' => 3], ['item_id' => 1]);
		$this->changeSnapshot(static function (array $snapshot): array
		{
			$snapshot['items'][4]['item_id'] = 0;
			return $snapshot;
		});

		$this->service()->restoreVersion(1, 1);

		$items = $this->items(1);
		static::assertSame(['bild.jpg', 'film.mp4', 'Bernds Kantine', 'doc.pdf', 'Vorlage'], array_column($items, 'item_name'));
		static::assertSame([2, 3, 4], array_slice(array_column($items, 'item_id'), 1, 3));
		static::assertGreaterThan(7, $items[0]['item_id']);
		static::assertGreaterThan(7, $items[4]['item_id']);
		static::assertSame([1], array_column($this->items(3), 'item_id'));
	}
}
