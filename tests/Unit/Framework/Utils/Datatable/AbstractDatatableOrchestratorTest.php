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

use App\Framework\Config\Config;
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Shared\Acl\AbstractAclValidator;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;
use Tests\Support\MemorySession;

/**
 * The orchestrator of a datatable with the real builder, preparer, parameters and the real services below them.
 * The service gets its rows from the test, the query is a stub.
 */
class AbstractDatatableOrchestratorTest extends TestCase
{
	private DatatableTestService $service;
	private DatatableTestOrchestrator $orchestrator;

	protected function setUp(): void
	{
		parent::setUp();

		$parameters = new DatatableTestParameters(new MemorySession());
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key . '@' . $module);

		$acl = $this->createStub(AbstractAclValidator::class);
		$acl->method('getConfig')->willReturn(new Config(new RecordingConfigLoader([]), [], ['APP_PLATFORM_EDITION' => 'edge']));

		$this->service      = new DatatableTestService($this->createStub(DatatableQuery::class), $parameters);
		$this->orchestrator = new DatatableTestOrchestrator(
			new DatatableTestBuilder(DatatableTestFactory::buildService(), $parameters, $acl, $translator),
			new DatatableTestPreparer(DatatableTestFactory::prepareService($translator), $parameters, $translator),
			$this->service
		);
	}

	/**
	 * @param array<string,mixed> $queryParams
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	private function process(array $queryParams): void
	{
		$this->orchestrator->configure();
		$this->orchestrator->processSubmittedUserInput($queryParams);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheParametersAreParsedBeforeTheDataIsLoaded(): void
	{
		$this->process(['name' => 'Lobby', 'elements_per_page' => '25', 'sort_column' => 'name', 'sort_order' => 'desc']);

		static::assertSame(1, $this->service->loaded);
		static::assertContains('Lobby', $this->service->parametersAtLoad);
		static::assertContains(25, $this->service->parametersAtLoad);
		static::assertContains('DESC', $this->service->parametersAtLoad);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareDataGridReturnsTheOrchestratorAndBuildsTheStructure(): void
	{
		$this->process([]);
		$this->service->setCurrentTotalResult(35);

		$result = $this->orchestrator->prepareDataGrid();

		static::assertSame($this->orchestrator, $result);
		$template = $this->orchestrator->prepareUITemplate();
		static::assertSame('Test overview', $template['title']);
		static::assertSame(['1', '2', '3', '4', '›', '»'], array_column($template['pagination_links'], 'ELEMENTS_PAGENAME'));
		static::assertSame(range(10, 100, 10), array_column($template['pagination_dropdown'], 'ELEMENTS_PER_PAGE_VALUE'));
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareUITemplateHasEverythingTheTemplatePreparerNeeds(): void
	{
		$this->process(['name' => 'Lobby', 'elements_page' => '2', 'elements_per_page' => '10']);
		$this->service->setCurrentTotalResult(25)->setCurrentFilterResults([['template_id' => 4, 'name' => 'Lobby', 'type' => 'canvas']]);
		$this->orchestrator->prepareDataGrid();

		$template = $this->orchestrator->prepareUITemplate();

		static::assertSame(
			[
				'filter_elements', 'pagination_dropdown', 'pagination_links', 'has_add', 'results_header', 'results_list', 'results_count',
				'title', 'template_name', 'module_name', 'additional_css', 'footer_modules', 'sort', 'page',
			],
			array_keys($template)
		);
		static::assertSame('generic/datatable', $template['template_name']);
		static::assertSame('testmodule', $template['module_name']);
		static::assertSame(['/css/test.css'], $template['additional_css']);
		static::assertSame(['/js/test.js'], $template['footer_modules']);
		static::assertSame(25, $template['results_count']);
		static::assertSame(['current' => 2, 'num_elements' => 10], $template['page']);
		static::assertSame('/templates/add', $template['has_add']['ELEMENTS_ADD_LINK']);
		static::assertSame(['name'], array_column($template['filter_elements']['visible'], 'HTML_ELEMENT_ID'));
		static::assertSame(['name', 'type'], array_column($template['results_header'], 'CONTROL_NAME'));
		static::assertSame(4, $template['results_list'][0]['UNIT_ID']);
		static::assertSame('Lobby', $template['results_list'][0]['elements_result_element'][0]['is_link']['CONTROL_ELEMENT_VALUE_NAME']);
	}

	/**
	 * The header is prepared before 'sort' is read: it must not change the order the filter form sends on.
	 *
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheSortOfTheTemplateIsTheSortOfTheRequest(): void
	{
		$this->process(['sort_column' => 'name', 'sort_order' => 'DESC']);
		$this->orchestrator->prepareDataGrid();

		$template = $this->orchestrator->prepareUITemplate();

		static::assertSame(['column' => 'name', 'order' => 'DESC'], $template['sort']);
		static::assertSame('▲', $template['results_header'][0]['if_sortable']['SORTABLE_ORDER']);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutResultsTheListIsEmpty(): void
	{
		$this->process([]);
		$this->orchestrator->prepareDataGrid();

		$template = $this->orchestrator->prepareUITemplate();

		static::assertSame([], $template['results_list']);
		static::assertSame(0, $template['results_count']);
		static::assertSame(['1'], array_column($template['pagination_links'], 'ELEMENTS_PAGENAME'));
	}
}
