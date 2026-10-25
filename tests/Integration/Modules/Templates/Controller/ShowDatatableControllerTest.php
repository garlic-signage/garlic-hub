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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Templates\Controller\ShowDatatableController;
use PHPUnit\Framework\Attributes\Group;

/**
 * The list of the templates /templates: who sees which template, the columns, the filters and the actions.
 * Only the admin may open the list. The controller keeps the input of the request, so every test makes one request.
 * Templates: 1 of the admin, 2 of anna, 3 of bernd.
 */
#[Group('acl')]
class ShowDatatableControllerTest extends TemplatesControllerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		// the types of the list: the type of the schema is fabric.js, the form creates canvas
		$this->connection->executeStatement("UPDATE templates SET type = 'canvas'");
	}

	/**
	 * @param array<string,string|int> $query
	 */
	private function list(array $query = []): PageResponse
	{
		$response = $this->container->get(ShowDatatableController::class)->show($this->request('GET', '/templates')->withQueryParams($query), $this->newResponse());
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

	public function testThePageHasItsTemplateTheColumnsAndTheContextMenu(): void
	{
		$this->actingAs(1);

		$page = $this->list();
		$data = $page->getTemplateData();

		static::assertSame('templates/datatable', $page->getTemplate());
		static::assertSame(['name', 'type', 'used'], array_column($data['elements_result_header'], 'CONTROL_NAME'));
		static::assertSame(['LANG_COMPOSE', 'LANG_SETTINGS', 'LANG_TEMPLATE_DELETE', 'LANG_TEMPLATE_DELETE_CONFIRM'], array_keys($data['templates_actions_contextmenu']));
		foreach ($data['templates_actions_contextmenu'] as $key => $text)
			static::assertNotSame('', $text, $key);
	}

	public function testTheAdminSeesAllTemplates(): void
	{
		$this->actingAs(1);

		static::assertSame([1, 2, 3], $this->idsOf());
		static::assertStringContainsString('3', (string) $this->list()->getTemplateData()['LANG_COUNT_SEARCH_RESULTS']);
	}

	public function testAUserIsSentToTheStartPage(): void
	{
		$this->actingAs(2);

		$response = $this->container->get(ShowDatatableController::class)->show($this->request('GET', '/templates'), $this->newResponse());

		$this->assertRedirect($response, '/');
	}

	public function testTheNameIsALinkToTheComposerAndTheTypeIsShownByName(): void
	{
		$this->actingAs(1);

		$cells = $this->list()->getTemplateData()['elements_results'][1]['elements_result_element'];

		static::assertSame(
			['CONTROL_ELEMENT_VALUE_NAME' => 'Annas Vorlage', 'CONTROL_ELEMENT_VALUE_TITLE' => 'Template Editor: Annas Vorlage', 'CONTROL_ELEMENT_VALUE_LINK' => '/templates/composer/2', 'CONTROL_ELEMENT_VALUE_ID' => '2', 'CONTROL_ELEMENT_VALUE_CLASS' => '', 'CONTROL_ELEMENT_ADDITIONAL_TEXT' => ''],
			$cells[0]['is_link']
		);
		static::assertSame(['CONTROL_ELEMENT_VALUE_TEXT' => 'Canvas'], $cells[1]['is_text']);
	}

	public function testHowOftenATemplateIsUsed(): void
	{
		$this->actingAs(1);
		$this->connection->executeStatement("UPDATE playlists_items SET item_type = 'template', file_resource = '2' WHERE item_id IN (1, 2)");

		$rows = $this->list()->getTemplateData()['elements_results'];

		static::assertSame(['0', '2', '0'], array_map(static fn(array $row): string => $row['elements_result_element'][2]['is_text']['CONTROL_ELEMENT_VALUE_TEXT'], $rows));
	}

	public function testEveryTemplateHasTheContextMenu(): void
	{
		$this->actingAs(1);

		$row = $this->list()->getTemplateData()['elements_results'][0];

		static::assertSame('template-contextmenu', $row['has_action'][0]['ACTION_NAME']);
		static::assertSame('1', $row['has_action'][0]['ACTION_ID']);
	}

	public function testTheTemplatesAreFilteredByTheStartOfTheName(): void
	{
		$this->actingAs(1);
		static::assertSame([2], $this->idsOf(['name' => 'Anna']));
	}

	public function testTheTemplatesAreSortedByTheChosenColumn(): void
	{
		$this->actingAs(1);
		static::assertSame([3, 2, 1], $this->idsOf(['sort_column' => 'name', 'sort_order' => 'DESC']));
	}

	public function testAnUnknownSortColumnFallsBackToTheDefaultOrder(): void
	{
		$this->actingAs(1);
		static::assertSame([1, 2, 3], $this->idsOf(['sort_column' => 'bogus; DROP TABLE templates', 'sort_order' => 'sideways']));
		static::assertSame(3, $this->countTemplates());
	}

	public function testTheTemplatesAreOnPages(): void
	{
		$this->actingAs(1);
		static::assertSame([3], $this->idsOf(['elements_per_page' => 1, 'elements_page' => 3]));
	}

	public function testATemplateOfAnUnknownTypeIsListed(): void
	{
		static::markTestSkipped('Known bug: the list reads the name of the type from a list of types (canvas, svg, html); the type of the schema is fabric.js and any text can be stored, a template of another type gives a TypeError and the whole list is not shown. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(1);
		$this->connection->executeStatement("UPDATE templates SET type = 'fabric.js' WHERE template_id = 2");

		static::assertSame([1, 2, 3], $this->idsOf());
	}
}
