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

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Controller\ShowDatatableController;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistRelease;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;

/**
 * The list of the playlists /playlists: who sees which playlist, the columns, the filters and the actions.
 * The controller keeps the input of the request, so every test makes one request.
 * Playlists: 1 and 3 belong to anna (3 is nested in 2), 2 to bernd (2 is nested in 1).
 */
#[Group('acl')]
class ShowDatatableControllerTest extends PlaylistsControllerTestCase
{
	/**
	 * @param array<string,string|int> $query
	 */
	private function list(array $query = []): PageResponse
	{
		$response = $this->container->get(ShowDatatableController::class)->show($this->request('GET', '/playlists')->withQueryParams($query), $this->newResponse());
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @param array<string,string|int> $query
	 * @return list<int>
	 */
	private function idsOf(array $query = []): array
	{
		return array_values(array_map(static fn(array $row): int => (int) $row['UNIT_ID'], $this->list($query)->getTemplateData()['elements_results']));
	}

	public function testThePageHasItsTemplateTheColumnsAndTheCreateMenu(): void
	{
		$this->actingAs(2);

		$page = $this->list();
		$data = $page->getTemplateData();

		static::assertSame('playlists/datatable', $page->getTemplate());
		static::assertSame(['playlist_name', 'playlist_mode', 'duration'], array_column($data['elements_result_header'], 'CONTROL_NAME'));
		static::assertSame(['internal', 'master', 'multizone'], array_column($data['create_playlist_contextmenu'], 'CREATE_PLAYLIST_MODE'));
		foreach ($data['create_playlist_contextmenu'] as $entry)
			static::assertNotSame('', $entry['LANG_CREATE_PLAYLIST_MODE']);
		static::assertSame(['/css/playlists/overview.css'], $page->getMainLayoutData()['additional_css']);
		static::assertSame(['/js/playlists/overview/init.js'], $page->getMainLayoutData()['footer_modules']);
	}

	/**
	 * The parameters of the list are configured for the user: while the release is on, the status is a column.
	 */
	public function testWhileTheReleaseIsOnTheListHasTheReleaseStatus(): void
	{
		$this->container->set(PlaylistRelease::class, new PlaylistRelease(
			true,
			$this->container->get(PlaylistsService::class),
			$this->container->get(AclValidator::class),
			$this->container->get(UserSession::class)
		));
		$this->actingAs(2);

		$data = $this->list()->getTemplateData();

		static::assertSame(['playlist_name', 'playlist_mode', 'duration', 'release_status'], array_column($data['elements_result_header'], 'CONTROL_NAME'));
	}

	public function testTheAdminSeesAllPlaylistsAndTheUsersTheirOwn(): void
	{
		$this->actingAs(1);
		static::assertSame([1, 2, 3], $this->idsOf());
	}

	public function testAUserSeesOnlyHisOwnPlaylists(): void
	{
		$this->actingAs(2);
		static::assertSame([1, 3], $this->idsOf());
		static::assertStringContainsString('2', (string) $this->list()->getTemplateData()['LANG_COUNT_SEARCH_RESULTS']);
	}

	public function testAnotherUserSeesOnlyHisOwnPlaylists(): void
	{
		$this->actingAs(3);
		static::assertSame([2], $this->idsOf());
	}

	/**
	 * The management list shows the playlists shared for editing, the ones shared for using are only in the selections.
	 */
	public function testAnEditorSeesHisOwnAndTheSharedPlaylistsButNotTheOnesForUsing(): void
	{
		$grants = $this->container->get(AclGrantService::class);
		$grants->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$grants->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->actingAs(2);
		static::assertSame([1, 3], $this->sortedIds(), 'The role alone shows the own playlists.');

		$grants->setUnitRole(1, 2, 'playlists', 2, AclSections::VIEWER);
		static::assertSame([1, 3], $this->sortedIds());

		$grants->setUnitRole(1, 2, 'playlists', 2, AclSections::EDITOR);
		static::assertSame([1, 2, 3], $this->sortedIds());
		static::assertSame([2], $this->sortedIds(['playlist_name' => 'Bernd']), 'The filters apply to the shared playlists, too.');
	}

	/**
	 * @param array<string,string|int> $query
	 * @return list<int>
	 */
	private function sortedIds(array $query = []): array
	{
		$ids = $this->idsOf($query);
		sort($ids);

		return $ids;
	}

	public function testThePlaylistsAreFilteredByMode(): void
	{
		$this->actingAs(1);
		static::assertSame([2], $this->idsOf(['playlist_mode' => 'internal']));
	}

	public function testThePlaylistsAreFilteredByTheStartOfTheNameWithoutRegardToCase(): void
	{
		$this->actingAs(1);
		static::assertSame([1, 3], $this->idsOf(['playlist_name' => 'annas L']));
	}

	public function testAUserCanNotFilterHimselfIntoThePlaylistsOfOthers(): void
	{
		$this->actingAs(2);
		static::assertSame([], $this->idsOf(['playlist_name' => 'Bernd']));
	}

	public function testThePlaylistsAreSortedByTheChosenColumn(): void
	{
		$this->actingAs(1);
		static::assertSame([2, 1, 3], $this->idsOf(['sort_column' => 'playlist_name', 'sort_order' => 'DESC']));
	}

	public function testAnUnknownSortColumnFallsBackToTheDefaultOrder(): void
	{
		$this->actingAs(1);
		static::assertSame([1, 2, 3], $this->idsOf(['sort_column' => 'bogus; DROP TABLE playlists', 'sort_order' => 'sideways']));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM playlists'));
	}

	public function testThePlaylistsAreOnPages(): void
	{
		$this->actingAs(1);
		static::assertSame([3], $this->idsOf(['elements_per_page' => 1, 'elements_page' => 3]));
	}

	public function testTheDurationIsShownAsTime(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement('UPDATE playlists SET duration = 3725 WHERE playlist_id = 1');

		$cells = $this->list()->getTemplateData()['elements_results'][0]['elements_result_element'];

		static::assertSame('duration', $cells[2]['CONTROL_NAME_BODY']);
		static::assertSame('01:02:05', $cells[2]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
	}

	/**
	 * A playlist which is nested in another one or assigned to a player has no delete button.
	 */
	public function testTheAdminGetsTheContextMenuOfEveryPlaylistAndTheUserOfHisOwn(): void
	{
		$this->actingAs(1);
		foreach ($this->list()->getTemplateData()['elements_results'] as $row)
		{
			static::assertSame(['playlist-contextmenu'], array_column($row['has_action'], 'ACTION_NAME'));
		}

		$this->actingAs(2);
		foreach ($this->list()->getTemplateData()['elements_results'] as $row)
		{
			static::assertArrayHasKey('has_action', $row);
		}
	}

	public function testTheNameOfAPlaylistIsALinkToTheComposer(): void
	{
		$this->actingAs(2);

		$row = $this->list()->getTemplateData()['elements_results'][0];

		static::assertSame('Annas Lobby', $row['elements_result_element'][0]['is_link']['CONTROL_ELEMENT_VALUE_NAME']);
		static::assertSame('/playlists/compose/1', $row['elements_result_element'][0]['is_link']['CONTROL_ELEMENT_VALUE_LINK']);
	}
}
