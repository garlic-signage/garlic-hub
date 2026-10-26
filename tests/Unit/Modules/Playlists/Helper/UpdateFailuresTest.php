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

namespace Tests\Unit\Modules\Playlists\Helper;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Playlists\Helper\Items\Orchestrator as ItemsOrchestrator;
use App\Modules\Playlists\Helper\Items\ResponseBuilder as ItemsResponseBuilder;
use App\Modules\Playlists\Helper\Playlists\Orchestrator as PlaylistsOrchestrator;
use App\Modules\Playlists\Helper\Playlists\ResponseBuilder as PlaylistsResponseBuilder;
use App\Modules\Playlists\Helper\Playlists\ZonesSchema;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Helper\Settings\FormInputHandler;
use App\Modules\Playlists\Helper\Settings\Orchestrator as SettingsOrchestrator;
use App\Modules\Playlists\Helper\Settings\SettingsFormBuilder;
use App\Modules\Playlists\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

/**
 * What the orchestrators answer when a service reports that no row was changed. The database of the integration tests
 * (SQLite) counts the rows which match, so these answers can only be reached with services which return 0.
 * Everything else of the orchestrators is tested through the controllers (tests/Integration/Modules/Playlists/Controller).
 */
class UpdateFailuresTest extends TestCase
{
	private function items(ItemsService $itemsService): ItemsOrchestrator
	{
		return new ItemsOrchestrator(
			new ItemsResponseBuilder(new JsonResponseHandler(), $this->createStub(Translator::class)),
			$itemsService,
			$this->createStub(InsertItemFactory::class)
		);
	}

	private function playlists(PlaylistsService $playlistsService): PlaylistsOrchestrator
	{
		return new PlaylistsOrchestrator(
			new PlaylistsResponseBuilder(new JsonResponseHandler(), $this->createStub(Translator::class)),
			$playlistsService,
			$this->createStub(PlaylistsDatatableService::class),
			$this->createStub(Parameters::class),
			new ZonesSchema()
		);
	}

	private function assertError(ResponseInterface $response, string $message): void
	{
		static::assertSame(400, $response->getStatusCode());
		static::assertSame(['success' => false, 'error_message' => $message], json_decode((string) $response->getBody(), true));
	}

	#[Group('units')]
	public function testAnItemFieldWhichIsNotChangedIsAnError(): void
	{
		$itemsService = $this->createStub(ItemsService::class);
		$itemsService->method('updateField')->willReturn(0);
		$itemsService->method('updateItemDuration')->willReturn(null);
		$orchestrator = $this->items($itemsService);

		$orchestrator->setInput(['item_id' => '1', 'name' => 'item_name', 'value' => 'x'])->validateEdit(new Response());
		$this->assertError($orchestrator->edit(new Response()), 'Error updating item field: item_name.');

		$orchestrator->setInput(['item_id' => '1', 'name' => 'item_duration', 'value' => '5'])->validateEdit(new Response());
		$this->assertError($orchestrator->edit(new Response()), 'Error updating item field: item_duration.');
	}

	#[Group('units')]
	public function testAPlaylistWhichIsNotChangedIsReportedAsNotFound(): void
	{
		$playlistsService = $this->createStub(PlaylistsService::class);
		$playlistsService->method('delete')->willReturn(0);
		$playlistsService->method('toggleShuffle')->willReturn(['affected' => 0, 'playlist_metrics' => []]);
		$playlistsService->method('shufflePicking')->willReturn(['affected' => 0, 'playlist_metrics' => []]);
		$orchestrator = $this->playlists($playlistsService);

		$orchestrator->setInput(['playlist_id' => '1', 'shuffle_picking' => '2'])->validateShufflePicking(new Response());

		$this->assertError($orchestrator->delete(new Response()), 'Playlist not found.');
		$this->assertError($orchestrator->toggleShuffle(new Response()), 'Playlist not found.');
		$this->assertError($orchestrator->shufflePicking(new Response()), 'Playlist not found.');
	}

	#[Group('units')]
	public function testZonesWhichAreNotStoredAreAnError(): void
	{
		$playlistsService = $this->createStub(PlaylistsService::class);
		$playlistsService->method('saveZones')->willReturn(0);
		$orchestrator = $this->playlists($playlistsService);

		$orchestrator->setInput(['playlist_id' => '1', 'resolution' => '1920x1080'])->validate(new Response());

		$this->assertError($orchestrator->saveZone(new Response()), 'Multizone could not be saved');
	}

	#[Group('units')]
	public function testAPlaylistWhichIsNotCreatedIsAnErrorOfTheForm(): void
	{
		$playlistsService = $this->createStub(PlaylistsService::class);
		$playlistsService->method('createNew')->willReturn(0);

		$result = $this->settings($playlistsService)->storeCreateSettings(['playlist_mode' => 'master', 'playlist_name' => 'Neu']);

		static::assertSame(['success' => false, 'errors' => ['Playlist “Neu“ could not be stored.']], $result);
	}

	#[Group('units')]
	public function testAPlaylistWhichCanNotBeChangedGivesTheMessageOfTheService(): void
	{
		$playlistsService = $this->createStub(PlaylistsService::class);
		$playlistsService->method('loadPlaylistForEdit')->willReturn(['UID' => 2, 'company_id' => 1, 'playlist_mode' => 'master', 'playlist_name' => 'Alt']);
		$playlistsService->method('updateSecure')->willThrowException(new ModuleException('playlists', 'The time limit is exceeded.'));
		$orchestrator = $this->settings($playlistsService);
		$orchestrator->loadPlaylistForEdit(1);

		$result = $orchestrator->storeEditSettings(['playlist_id' => '1', 'playlist_name' => 'Neu']);

		static::assertFalse($result['success']);
		static::assertCount(1, $result['errors'] ?? []);
		static::assertStringContainsString('The time limit is exceeded.', $result['errors'][0] ?? '');
	}

	#[Group('units')]
	public function testTheFormOfAPlaylistNeedsALoadedPlaylist(): void
	{
		$this->expectException(ModuleException::class);
		$this->settings($this->createStub(PlaylistsService::class))->buildEditForm([]);
	}

	private function settings(PlaylistsService $playlistsService): SettingsOrchestrator
	{
		$inputHandler = $this->createStub(FormInputHandler::class);
		$inputHandler->method('validate')->willReturn([]);
		$inputHandler->method('getParsed')->willReturn(['playlist_name' => 'Neu']);

		return new SettingsOrchestrator(
			$playlistsService,
			$this->createStub(SettingsParametersPolicy::class),
			$inputHandler,
			$this->createStub(SettingsFormBuilder::class)
		);
	}
}
