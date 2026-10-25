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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\Paginator\Builder as PaginatorBuilder;
use App\Framework\Utils\Datatable\Results\Builder as ResultsBuilder;
use App\Framework\Utils\Html\FieldType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The preparer of a module with the real PrepareService. The Translator answers "<key>@<module>".
 */
class AbstractDatatablePreparerTest extends TestCase
{
	private DatatableTestPreparer $preparer;

	/**
	 * @param array<string,mixed> $input
	 * @throws ModuleException
	 */
	private function preparer(array $input = []): DatatableTestPreparer
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key . '@' . $module);

		return new DatatableTestPreparer(DatatableTestFactory::prepareService($translator), new FilterParametersFake($input), $translator);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws ModuleException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->preparer = $this->preparer();
	}

	/**
	 * @throws \App\Framework\Exceptions\FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFilterForm(): void
	{
		$form = [
			'hidden' => DatatableTestFactory::formBuilder()->createField(['id' => 'a', 'type' => FieldType::HIDDEN, 'value' => 1]),
			'name'   => DatatableTestFactory::formBuilder()->createField(['id' => 'name', 'type' => FieldType::TEXT, 'name' => 'name', 'label' => 'Name']),
		];

		$result = $this->preparer->prepareFilterForm($form);

		static::assertSame(['<input type="hidden" name="a" id="a" value="1">'], array_column($result['hidden'], 'HIDDEN_HTML_ELEMENT'));
		static::assertSame(['name'], array_column($result['visible'], 'HTML_ELEMENT_ID'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreparePagination(): void
	{
		$preparer = $this->preparer(['elements_page' => '2', 'elements_per_page' => '10']);
		$pager    = (new PaginatorBuilder())->configure(2, 10, 30, false, false)->buildPagerLinks()->getPagerLinks();

		$result = $preparer->preparePagination($pager, ['min' => 10, 'max' => 20, 'steps' => 10]);

		static::assertSame(['1', '2', '3'], array_column($result['links'], 'ELEMENTS_PAGENAME'));
		static::assertSame(['active_page'], array_values(array_filter(array_column($result['links'], 'ELEMENTS_ACTIVE_PAGE'))));
		static::assertSame([10, 20], array_column($result['dropdown'], 'ELEMENTS_PER_PAGE_VALUE'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeader(): void
	{
		$builder = new ResultsBuilder();
		$builder->createField('name', true);

		$header = $this->preparer->prepareTableHeader($builder->getHeaderFields(), ['testmodule', 'main']);

		static::assertSame('name@testmodule', $header[0]['if_sortable']['LANG_CONTROL_NAME']);
		static::assertStringStartsWith('testmodule?elements_page=1&sort_column=name', $header[0]['if_sortable']['LINK_CONTROL_SORT_ORDER']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareAddHasTheIconTheLinkAndTheTextOfTheModule(): void
	{
		static::assertSame(
			['ADD_BI_ICON' => 'file-plus', 'LANG_ELEMENTS_ADD_LINK' => 'add@testmodule', 'ELEMENTS_ADD_LINK' => '/templates/add'],
			$this->preparer->prepareAdd('file-plus', '/templates/add')
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareAddWithoutParameters(): void
	{
		static::assertSame(
			['ADD_BI_ICON' => 'folder-plus', 'LANG_ELEMENTS_ADD_LINK' => 'add@testmodule', 'ELEMENTS_ADD_LINK' => '#'],
			$this->preparer->prepareAdd()
		);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareSortAndPage(): void
	{
		$preparer = $this->preparer(['sort_column' => 'created', 'sort_order' => 'DESC', 'elements_page' => '3', 'elements_per_page' => '25']);

		static::assertSame(['column' => 'created', 'order' => 'DESC'], $preparer->prepareSort());
		static::assertSame(['current' => 3, 'num_elements' => 25], $preparer->preparePage());
	}

	/**
	 * The header only reads the sort order, the sort of the filter form after it is still the real one.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareSortAfterThePreparedHeaderStillHasTheRealOrder(): void
	{
		$preparer = $this->preparer(['sort_column' => 'name', 'sort_order' => 'DESC']);
		$builder  = new ResultsBuilder();
		$builder->createField('name', true);
		$builder->createField('created', true);
		$preparer->prepareTableHeader($builder->getHeaderFields(), ['main']);

		static::assertSame(['column' => 'name', 'order' => 'DESC'], $preparer->prepareSort());
	}

	#[Group('units')]
	public function testPrepareTableBodyOfTheModule(): void
	{
		$builder = new ResultsBuilder();
		$builder->createField('name', true);

		$body = $this->preparer->prepareTableBody([['template_id' => 4, 'name' => 'Lobby']], $builder->getHeaderFields(), 7);

		static::assertSame(4, $body[0]['UNIT_ID']);
		static::assertSame('Lobby', $body[0]['elements_result_element'][0]['is_link']['CONTROL_ELEMENT_VALUE_NAME']);
	}
}
