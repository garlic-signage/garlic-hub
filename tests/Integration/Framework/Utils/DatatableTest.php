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

namespace Tests\Integration\Framework\Utils;

use App\Framework\Config\Config;
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\TemplateEngine\MustacheEngineFactory;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use App\Shared\Acl\AbstractAclValidator;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Integration\Support\RepositoryTestCase;
use Tests\Support\RecordingConfigLoader;
use Tests\Support\MemoryCache;
use Tests\Support\RecordingTranslationLoader;
use Tests\Support\MemorySession;
use Tests\Unit\Framework\Utils\Datatable\DatatableTestBuilder;
use Tests\Unit\Framework\Utils\Datatable\DatatableTestFactory;
use Tests\Unit\Framework\Utils\Datatable\DatatableTestOrchestrator;
use Tests\Unit\Framework\Utils\Datatable\DatatableTestParameters;
use Tests\Unit\Framework\Utils\Datatable\DatatableTestPreparer;

/**
 * A whole datatable page: real database, real query, builder, preparer, orchestrator, template preparer and the template
 * generic/datatable.mustache. The HTML is read like a browser reads it. Fixtures: 33 templates (UID 2, anna), "List 01"
 * to "List 30", and the three of templates.sql.
 */
#[Group('db')]
class DatatableTest extends RepositoryTestCase
{
	private DOMXPath $xpath;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		for ($i = 1; $i <= 30; $i++)
		{
			$this->connection->executeStatement(
				'INSERT INTO templates (UID, visibility, type, name) VALUES (2, ?, ?, ?)',
				['private', $i % 2 === 0 ? 'fabric.js' : 'canvas', sprintf('List %02d', $i)]
			);
		}
	}

	/**
	 * @param array<string,mixed> $query the query string of the request
	 */
	private function render(array $query): void
	{
		$conditions = new FilterConditions();
		$parameters = new DatatableTestParameters(new MemorySession());

		$locales = $this->createStub(Locales::class);
		$locales->method('getLanguageCode')->willReturn('en');
		$translator = new Translator($locales, new RecordingTranslationLoader(['en' => [
			'main'       => ['filter' => 'Filter', 'elements_per_page' => 'per page', 'name' => 'Name', 'type' => 'Type'],
			'testmodule' => ['count_search_results' => '{count, plural, one {1 Template} other {# Templates}}', 'add' => 'Add'],
		]]), new MessageFormatterFactory(), new MemoryCache());

		$acl = $this->createStub(AbstractAclValidator::class);
		$acl->method('getConfig')->willReturn(new Config(new RecordingConfigLoader([]), [], ['APP_PLATFORM_EDITION' => 'edge']));

		$definition = new TemplatesFilterDefinition($conditions);
		$service    = new class(new DatatableQuery($this->connection), $definition, $parameters) extends AbstractDatatableService
		{
			public function __construct(DatatableQuery $query, private readonly TemplatesFilterDefinition $definition, private readonly DatatableTestParameters $parameters)
			{
				parent::__construct(new NullLogger(), $query);
			}

			public function loadDatatable(): void
			{
				$this->fetchForModuleAdmin($this->definition, $this->parameters);
			}
		};

		$orchestrator = new DatatableTestOrchestrator(
			new DatatableTestBuilder(DatatableTestFactory::buildService(), $parameters, $acl, $translator),
			new DatatableTestPreparer(DatatableTestFactory::prepareService($translator), $parameters, $translator),
			$service
		);

		$orchestrator->configure();
		$orchestrator->processSubmittedUserInput($query);
		$orchestrator->prepareDataGrid();

		$page    = new DatatableTemplatePreparer($translator)->preparerUITemplate($orchestrator->prepareUITemplate());
		$response = new PageResponse($page);
		$adapter  = new MustacheAdapter(new MustacheEngineFactory()->create(__DIR__ . '/../../../../templates'));

		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<html><head><meta charset="utf-8"></head><body>' . $adapter->render($response->getTemplate(), $response->getTemplateData()) . '</body></html>');
		libxml_clear_errors();
		$this->xpath = new DOMXPath($document);
	}

	private function element(string $query): ?DOMElement
	{
		$nodes = $this->xpath->query($query);
		$node  = $nodes !== false ? $nodes->item(0) : null;

		return $node instanceof DOMElement ? $node : null;
	}

	private function text(string $query): string
	{
		return trim(preg_replace('/\s+/', ' ', $this->element($query)->textContent ?? '') ?? '');
	}

	private function attribute(string $query, string $attribute): string
	{
		return $this->element($query)?->getAttribute($attribute) ?? '';
	}

	/**
	 * @return list<string>
	 */
	private function names(): array
	{
		$names = [];
		foreach ($this->xpath->query('//ul[contains(@class,"results-body")]/li[contains(@class,"name")]') ?: [] as $node)
		{
			if ($node instanceof DOMElement)
				$names[] = trim($node->textContent);
		}

		return $names;
	}

	public function testTheFirstPageShowsTenRowsSortedByName(): void
	{
		$this->render([]);

		static::assertSame('Test overview', $this->text('//h1'));
		static::assertSame(
			['Admin Vorlage', 'Annas Vorlage', 'Bernds Vorlage', 'List 01', 'List 02', 'List 03', 'List 04', 'List 05', 'List 06', 'List 07'],
			$this->names()
		);
		static::assertSame('33 Templates', $this->text('//form//div[contains(text(),"Templates")]'));
	}

	public function testTheRowLinksToTheTemplate(): void
	{
		$this->render(['name' => 'Annas']);

		static::assertSame(['Annas Vorlage'], $this->names());
		static::assertSame('/templates/2', $this->attribute('//ul[contains(@class,"results-body")]//a', 'href'));
		static::assertSame('Open: Annas Vorlage', $this->attribute('//ul[contains(@class,"results-body")]//a', 'title'));
		static::assertSame('1 Template', $this->text('//form//div[contains(text(),"Template")]'));
	}

	public function testThePagerMarksOnlyTheCurrentPage(): void
	{
		$this->render(['elements_page' => '2']);

		static::assertSame(['List 08', 'List 09', 'List 10', 'List 11', 'List 12', 'List 13', 'List 14', 'List 15', 'List 16', 'List 17'], $this->names());
		static::assertSame(1, (int) $this->xpath->evaluate('count(//a[contains(@class,"page_numbers") and contains(@class,"active_page")])'));
		static::assertStringContainsString('active_page', $this->attribute('//a[@id="page_number_2"]', 'class'));
		static::assertStringNotContainsString('active_page', $this->attribute('//a[@id="page_number_1"]', 'class'));
	}

	public function testThePagerKeepsFilterAndSortInItsLinks(): void
	{
		$this->render(['elements_page' => '1', 'sort_order' => 'DESC']);

		static::assertSame(
			'testmodule?elements_page=2&sort_column=name&sort_order=DESC&elements_per_page=10',
			$this->attribute('//a[@id="page_number_2"]', 'href')
		);
	}

	public function testTheDropdownSelectsTheNumberPerPage(): void
	{
		$this->render(['elements_per_page' => '20']);

		static::assertCount(20, $this->names());
		static::assertSame('20', $this->attribute('//select[@id="elements_per_page"]/option[@selected]', 'value'));
		static::assertSame(10, (int) $this->xpath->evaluate('count(//select[@id="elements_per_page"]/option)'));
	}

	public function testNoIdOccursTwice(): void
	{
		$this->render([]);

		$ids = [];
		foreach ($this->xpath->query('//*[@id]') ?: [] as $node)
			$ids[] = $node instanceof \DOMElement ? $node->getAttribute('id') : '';

		static::assertSame([], array_keys(array_filter(array_count_values($ids), static fn(int $count): bool => $count > 1)));
	}

	/**
	 * Click on the header of the column which is sorted: the order turns.
	 */
	public function testTheSortedHeaderOffersTheOtherOrder(): void
	{
		$this->render(['sort_column' => 'name', 'sort_order' => 'ASC']);

		static::assertSame('testmodule?elements_page=1&sort_column=name&sort_order=desc&elements_per_page=10', $this->attribute('//a[@id="name_sort_column"]', 'href'));
		static::assertStringContainsString('▼', $this->text('//ul[@class="results-header"]/li[@class="name"]'));
	}

	public function testTheSortOrderOfTheFilterFormIsTheOneOfTheRequest(): void
	{
		$this->render(['sort_column' => 'name', 'sort_order' => 'desc']);

		static::assertSame('DESC', $this->attribute('//input[@id="sort_order"]', 'value'));
		static::assertSame('name', $this->attribute('//input[@id="sort_column"]', 'value'));
		static::assertSame(['List 30', 'List 29'], array_slice($this->names(), 0, 2));
	}

	public function testAColumnWhichIsNotSortableIsSortedByTheDefault(): void
	{
		$this->render(['sort_column' => 'content; DROP TABLE templates', 'sort_order' => 'asc']);

		static::assertSame('name', $this->attribute('//input[@id="sort_column"]', 'value'));
		static::assertSame('Admin Vorlage', $this->names()[0]);
		static::assertSame(33, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates'));
	}

	public function testTheFilterFormHasTheFilterValue(): void
	{
		$this->render(['name' => 'List 1']);

		static::assertSame('List 1', $this->attribute('//form[@id="form_elements_search"]//input[@id="name"]', 'value'));
		// "List 1" is part of List 10 to List 19
		static::assertSame(['List 10', 'List 11', 'List 12', 'List 13', 'List 14', 'List 15', 'List 16', 'List 17', 'List 18', 'List 19'], $this->names());
	}

	/**
	 * What the visitor types ends in the filter field and in the links, it must not become markup.
	 */
	public function testAFilterValueWithMarkupIsOnlyText(): void
	{
		$this->render(['name' => '"><script>alert(1)</script>']);

		static::assertSame(0, (int) $this->xpath->evaluate('count(//script)'));
		static::assertSame('"><script>alert(1)</script>', $this->attribute('//input[@id="name"]', 'value'));
		static::assertSame([], $this->names());
		static::assertSame('0 Templates', $this->text('//form//div[contains(text(),"Templates")]'));
	}

	public function testTheAddLinkAndTheHeaderTextsAreThere(): void
	{
		$this->render([]);

		static::assertSame('/templates/add', $this->attribute('//a[@id="createUnitAction"]', 'href'));
		static::assertSame('Add', $this->text('//a[@id="createUnitAction"]'));
		static::assertSame('Name', $this->text('//a[@id="name_sort_column"]'));
		static::assertSame('Type', $this->text('//ul[@class="results-header"]/li[@class="type"]'));
	}
}
