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

namespace Tests\Integration\Modules\Player\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Player\Controller\ShowDatatableController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The list of the players /player (the database is SQLite, which writes its times in UTC): who sees which player, the columns, the filters and how a player is shown.
 * Players: 1 and 2 belong to anna, 3 to bernd, 4 to the admin.
 */
#[Group('acl')]
class ShowDatatableControllerTest extends PlayerControllerTestCase
{
	/**
	 * @param array<string,string> $query
	 */
	private function list(array $query = []): PageResponse
	{
		$controller = $this->container->get(ShowDatatableController::class);
		$response   = $controller->show($this->request('GET', '/player')->withQueryParams($query), $this->newResponse());
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @param array<string,string> $query
	 * @return list<array<string,mixed>> the cells of every row by the name of the column
	 */
	private function rows(array $query = []): array
	{
		$rows = [];
		foreach ($this->list($query)->getTemplateData()['elements_results'] as $row)
		{
			$cells = ['UNIT_ID' => $row['UNIT_ID']];
			foreach ($row['elements_result_element'] as $element)
				$cells[$element['CONTROL_NAME_BODY']] = $element;
			$rows[] = $cells;
		}

		return $rows;
	}

	/**
	 * @param array<string,string> $query
	 * @return list<int>
	 */
	private function idsOf(array $query = []): array
	{
		return array_map(static fn(array $row): int => (int) $row['UNIT_ID'], $this->rows($query));
	}

	/**
	 * @param array<string,mixed> $cell
	 */
	private function textOf(array $cell): string
	{
		$text = $cell['is_text'];

		return is_array($text) ? (string) $text['CONTROL_ELEMENT_VALUE_TEXT'] : (string) $text;
	}

	private function lastAccessSecondsAgo(int $playerId, int $seconds): void
	{
		$this->connection->executeStatement('UPDATE player SET refresh = 300, last_access = ? WHERE player_id = ?', [gmdate('Y-m-d H:i:s', time() - $seconds), $playerId]);
	}

	public function testThePageHasItsTemplateTheColumnsAndTheContextMenu(): void
	{
		$this->actingAs(1);

		$data = $this->list()->getTemplateData();

		static::assertSame('player/datatable', $this->list()->getTemplate());
		static::assertSame(['last_access', 'player_name', 'model', 'playlist_id'], array_column($data['elements_result_header'], 'CONTROL_NAME'));
		static::assertSame(['LANG_ASSIGN_PLAYLIST', 'LANG_UNASSIGN_PLAYLIST', 'LANG_GOTO_PLAYLIST', 'LANG_PUSH_PLAYLIST', 'LANG_PLAYER_DELETE', 'LANG_PLAYER_DELETE_CONFIRM'], array_keys($data['player_actions_contextmenu']));
		foreach ($data['player_actions_contextmenu'] as $key => $text)
			static::assertNotSame('', $text, $key);
	}

	public function testTheAdminSeesAllPlayersAndTheUsersTheirOwn(): void
	{
		$this->actingAs(1);
		static::assertSame([1, 2, 3, 4], $this->idsOf());

		$this->actingAs(2);
		static::assertSame([1, 2], $this->idsOf());

		$this->actingAs(3);
		static::assertSame([3], $this->idsOf());
	}

	public function testTheNumberOfTheFoundPlayersIsThatOfTheUser(): void
	{
		$this->actingAs(2);

		static::assertStringContainsString('2', (string) $this->list()->getTemplateData()['LANG_COUNT_SEARCH_RESULTS']);
	}

	public function testThePlayerNameIsALinkToTheNetworkSettings(): void
	{
		$this->actingAs(1);

		$cell = $this->rows()[2]['player_name'];

		static::assertSame('Bernds Player', $cell['is_link']['CONTROL_ELEMENT_VALUE_NAME']);
		static::assertSame('/player/connectivity/3', $cell['is_link']['CONTROL_ELEMENT_VALUE_LINK']);
		static::assertSame('player_name_3', $cell['is_link']['CONTROL_ELEMENT_VALUE_ID']);
	}

	/**
	 * @return array<string,array{int,string}> the seconds since the last access (the refresh time is 300) and the class
	 */
	public static function activities(): array
	{
		return [
			'just now'                   => [10, 'player-active'],
			'under two refresh times'    => [590, 'player-active'],
			'over two refresh times'     => [610, 'player-pending'],
			'under four refresh times'   => [1190, 'player-pending'],
			'over four refresh times'    => [1210, 'player-inactive'],
			'long ago'                   => [90000, 'player-inactive'],
		];
	}

	/**
	 * A player which has not asked for the index for two refresh times is pending, for four it is inactive.
	 */
	#[DataProvider('activities')]
	public function testTheLastAccessShowsHowAliveThePlayerIs(int $seconds, string $class): void
	{
		$this->actingAs(1);
		$this->lastAccessSecondsAgo(1, $seconds);

		static::assertStringContainsString($class, json_encode($this->rows()[0]['last_access']['is_span']) ?: '');
	}

	public function testTheModelIsShownAsText(): void
	{
		$this->actingAs(1);
		$this->setPlayer(1, 'model = 6');
		$this->setPlayer(2, 'model = 1');

		$rows = $this->rows();

		static::assertSame('garlic-player', $this->textOf($rows[0]['model']));
		static::assertSame('IAdea Series 1x0', $this->textOf($rows[1]['model']));
	}

	public function testThePlaylistIsShownOnlyForAReleasedPlayer(): void
	{
		$this->actingAs(1);
		$this->setPlayer(1, 'status = 2, playlist_id = 1');
		$this->setPlayer(2, 'status = 1, playlist_id = 1');
		$this->setPlayer(3, 'status = 2, playlist_id = 0');

		$rows = $this->rows();

		static::assertSame('Annas Lobby', $this->textOf($rows[0]['playlist_id']));
		static::assertSame('', $this->textOf($rows[1]['playlist_id']));
		static::assertSame('', $this->textOf($rows[2]['playlist_id']));
	}

	public function testTheFilterByNameFindsPartsOfTheName(): void
	{
		$this->actingAs(1);

		static::assertSame([1, 2], $this->idsOf(['player_name' => 'Annas']));
		static::assertSame([3], $this->idsOf(['player_name' => 'Bernd']));
		static::assertSame([], $this->idsOf(['player_name' => 'Nobody']));
	}

	public function testTheFilterByNameDoesNotTakeWildcardsOfSql(): void
	{
		$this->actingAs(1);

		static::assertSame([], $this->idsOf(['player_name' => '%']));
		static::assertSame([], $this->idsOf(['player_name' => 'A_nas']));
	}

	public function testTheFilterByNameIsLimitedToThePlayersOfTheUser(): void
	{
		$this->actingAs(2);

		static::assertSame([], $this->idsOf(['player_name' => 'Bernd']));
	}

	/**
	 * The edge edition has no filter by activity (and none by the owner): the field is not there, and the address
	 * with it shows the same list.
	 */
	public function testTheEdgeEditionHasNoFilterByActivity(): void
	{
		$this->actingAs(1);
		$this->lastAccessSecondsAgo(1, 10);
		$this->lastAccessSecondsAgo(2, 5000);

		static::assertSame(['player_name', 'model'], array_column($this->list()->getTemplateData()['form_element'], 'LANG_ELEMENT_NAME'));
		static::assertSame([1, 2, 3, 4], $this->idsOf(['activity' => 'active']));
	}

	public function testTheFilterByModel(): void
	{
		$this->actingAs(1);
		$this->setPlayer(1, 'model = 6');
		$this->setPlayer(2, 'model = 6');

		static::assertSame([1, 2], $this->idsOf(['model' => '6']));
		static::assertSame([1, 2, 3, 4], $this->idsOf(['model' => '0']), 'The model 0 is no filter.');
	}

	public function testTheListCanBeSortedByTheNameInBothDirections(): void
	{
		$this->actingAs(1);

		static::assertSame([4, 1, 2, 3], $this->idsOf(['sort_column' => 'player_name', 'sort_order' => 'asc']));
		static::assertSame([3, 2, 1, 4], $this->idsOf(['sort_column' => 'player_name', 'sort_order' => 'desc']));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function strangeQueries(): array
	{
		return [
			'a sort column which is no column' => [['sort_column' => 'password; DROP TABLE player']],
			'a sort order which is no order'   => [['sort_order' => 'sideways']],
			'a name as a list'                 => [['player_name' => ['a']]],
			'a model as text'                  => [['model' => 'garlic']],
			'a page far away'                  => [['elements_page' => '9999']],
			'a page as a list'                 => [['elements_page' => ['2']]],
			'elements per page, a million'     => [['elements_per_page' => '1000000']],
		];
	}

	/**
	 * The address of the list comes from the browser and can be anything. The list is shown, and the table stays.
	 *
	 * @param array<string,mixed> $query
	 */
	#[DataProvider('strangeQueries')]
	public function testAStrangeAddressStillShowsTheList(array $query): void
	{
		$this->actingAs(1);

		$page = $this->list($query);

		static::assertSame('player/datatable', $page->getTemplate());
		static::assertSame(4, $this->countPlayers());
	}
}
