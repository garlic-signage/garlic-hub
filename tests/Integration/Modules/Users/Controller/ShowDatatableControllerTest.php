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

namespace Tests\Integration\Modules\Users\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Users\Controller\ShowDatatableController;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\HtmlFields;

/**
 * The list of the users /users: who sees which user, the columns, the filters and the links. The controller keeps
 * the input of the request, so every test makes one request. Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('acl')]
class ShowDatatableControllerTest extends UsersControllerTestCase
{
	/**
	 * @param array<string,string|int|list<string>> $query
	 */
	private function list(array $query = []): PageResponse
	{
		$response = $this->container->get(ShowDatatableController::class)->show($this->request('GET', '/users')->withQueryParams($query), $this->newResponse());

		return $this->page($response);
	}

	/**
	 * @param array<string,string|int|list<string>> $query
	 * @return list<int>
	 */
	private function idsOf(array $query = []): array
	{
		return array_values(array_map(static fn(array $row): int => (int) $row['UNIT_ID'], $this->list($query)->getTemplateData()['elements_results']));
	}

	public function testTheAdminSeesAllUsersWithTheirColumnsAndTheLinkToTheForm(): void
	{
		$this->actingAs(1);

		$rows = $this->list()->getTemplateData()['elements_results'];

		static::assertSame([1, 2, 3], array_column($rows, 'UNIT_ID'));
		static::assertSame(['username', 'created_at', 'status'], array_column($rows[2]['elements_result_element'], 'CONTROL_NAME_BODY'));
		static::assertSame('bernd', $rows[2]['elements_result_element'][0]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
		static::assertSame('Registered', $rows[2]['elements_result_element'][2]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']);
		static::assertSame('users/edit/3', $rows[2]['has_action_link'][0]['LINK_ACTION']);
	}

	public function testThePageHasItsTemplateAndTheFilters(): void
	{
		$this->actingAs(1);

		$page = $this->list();

		static::assertSame('users/datatable', $page->getTemplate());
		static::assertStringContainsString('3', (string) $page->getTemplateData()['LANG_COUNT_SEARCH_RESULTS']);
		static::assertSame(['username', 'email', 'status'], array_keys(HtmlFields::fromPrepared(['visible' => $page->getTemplateData()['form_element']])));
	}

	public function testAUserSeesOnlyHerself(): void
	{
		$this->actingAs(2);

		static::assertSame([2], $this->idsOf());
		static::assertSame([], $this->idsOf(['username' => 'bernd']));
	}

	public function testTheListNeverContainsPasswordsOrSessions(): void
	{
		$this->actingAs(1);

		$json = json_encode($this->list()->getTemplateData(), JSON_THROW_ON_ERROR);

		static::assertStringNotContainsString('$2y$', $json);
		static::assertStringNotContainsString('session', strtolower($json));
	}

	public function testAStatusWhichIsNotInTheListIsShownAsNumber(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 99 WHERE UID = 3');

		$rows = $this->list()->getTemplateData()['elements_results'];

		static::assertSame(['Registered', '99'], [$rows[1]['elements_result_element'][2]['is_text']['CONTROL_ELEMENT_VALUE_TEXT'], $rows[2]['elements_result_element'][2]['is_text']['CONTROL_ELEMENT_VALUE_TEXT']]);
	}

	public function testTheStatusSelectMarksOnlyTheChosenStatus(): void
	{
		$this->actingAs(1);

		// nothing is marked, the browser shows the first option
		static::assertSame('', $this->selectedStatusOption($this->list()));
		static::assertSame('Deleted', $this->selectedStatusOption($this->list(['status' => '0'])));
		static::assertSame('Blocked', $this->selectedStatusOption($this->list(['status' => '1'])));
	}

	public function testAStatusWhichIsNoStatusFindsNoUsers(): void
	{
		$this->actingAs(1);

		static::assertSame([], $this->idsOf(['status' => 'abc']));
	}

	private function selectedStatusOption(PageResponse $page): string
	{
		foreach ($page->getTemplateData()['form_element'] as $element)
		{
			if (str_contains($element['HTML_ELEMENT'], 'name= "status"') && preg_match('/<option value="[^"]*" selected>([^<]*)</', $element['HTML_ELEMENT'], $match) === 1)
				return $match[1];
		}

		return '';
	}

	public function testTheUsersAreFilteredByTheStartOfTheName(): void
	{
		$this->actingAs(1);

		static::assertSame([2], $this->idsOf(['username' => 'an']));
		static::assertSame([], $this->idsOf(['username' => 'nobody']));
	}

	public function testTheUsersAreFilteredByTheEmail(): void
	{
		$this->actingAs(1);

		static::assertSame([3], $this->idsOf(['email' => 'bernd@']));
	}

	public function testTheUsersAreFilteredByTheStatus(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 1 WHERE UID = 3');

		static::assertSame([3], $this->idsOf(['status' => 1]));
		static::assertSame([1, 2], $this->idsOf(['status' => 3]));
	}

	public function testTheDeletedUsersCanBeFiltered(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 0 WHERE UID = 3');

		static::assertSame([3], $this->idsOf(['status' => '0']));
	}

	public function testTheUsersAreSortedByTheChosenColumn(): void
	{
		$this->actingAs(1);

		static::assertSame([3, 2, 1], $this->idsOf(['sort_column' => 'username', 'sort_order' => 'DESC']));
	}

	public function testAnUnknownSortColumnFallsBackToTheDefaultOrder(): void
	{
		$this->actingAs(1);

		static::assertSame([1, 2, 3], $this->idsOf(['sort_column' => 'bogus; DROP TABLE user_main', 'sort_order' => 'sideways']));
		static::assertSame(3, $this->countUsers());
	}

	public function testTheUsersAreOnPages(): void
	{
		$this->actingAs(1);

		static::assertSame([3], $this->idsOf(['elements_per_page' => 1, 'elements_page' => 3]));
	}

	public function testAFilterWhichIsAListIsNoFilter(): void
	{
		$this->actingAs(1);

		static::assertSame([1, 2, 3], $this->idsOf(['username' => ['an'], 'status' => ['1']]));
	}

	public function testTheAdminGetsALinkToTheFormOfEveryUser(): void
	{
		$this->actingAs(1);

		$links = array_map(static fn(array $row): string => $row['has_action_link'][0]['LINK_ACTION'], $this->list()->getTemplateData()['elements_results']);

		static::assertSame(['users/edit/1', 'users/edit/2', 'users/edit/3'], $links);
	}

	public function testAUserGetsNoLinkToTheFormOfHerself(): void
	{
		$this->actingAs(2);

		static::assertArrayNotHasKey('has_action_link', $this->list()->getTemplateData()['elements_results'][0]);
	}

	public function testTheAdminCanDeleteOtherActiveUsersButNeitherHimselfNorDeletedOnes(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 0 WHERE UID = 3');

		$rows = $this->list()->getTemplateData()['elements_results'];

		static::assertArrayNotHasKey('has_delete', $rows[0]);
		static::assertSame('delete-user', $rows[1]['has_delete']['DELETE_CLASS'] ?? $rows[1]['has_delete']['ELEMENT_DELETE_CLASS'] ?? null);
		static::assertArrayNotHasKey('has_delete', $rows[2]);
	}

	public function testADeletedUserHasNoLinkToTheForm(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement('UPDATE user_main SET status = 0 WHERE UID = 3');

		$rows = $this->list()->getTemplateData()['elements_results'];

		static::assertSame(['users/edit/1', 'users/edit/2'], [$rows[0]['has_action_link'][0]['LINK_ACTION'], $rows[1]['has_action_link'][0]['LINK_ACTION']]);
		static::assertArrayNotHasKey('has_action_link', $rows[2]);
	}

	public function testAUserDoesNotGetADeleteAction(): void
	{
		$this->actingAs(2);

		static::assertArrayNotHasKey('has_delete', $this->list()->getTemplateData()['elements_results'][0]);
	}
}
