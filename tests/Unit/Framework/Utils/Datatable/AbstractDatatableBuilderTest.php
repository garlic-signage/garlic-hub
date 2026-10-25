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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Shared\Acl\AbstractAclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;
use Tests\Support\MemorySession;

/**
 * The builder of a module with the real BuildService and real filter parameters. The ACL validator is a stub:
 * it knows the edition and the roles of the user.
 */
class AbstractDatatableBuilderTest extends TestCase
{
	private DatatableTestParameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new DatatableTestParameters(new MemorySession());
	}

	private function builder(string $edition = Config::PLATFORM_EDITION_ENTERPRISE, bool $moduleAdmin = false, bool $subAdmin = false): DatatableTestBuilder
	{
		$acl = $this->createStub(AbstractAclValidator::class);
		$acl->method('getConfig')->willReturn(new Config(new RecordingConfigLoader([]), [], ['APP_PLATFORM_EDITION' => $edition]));
		$acl->method('isModuleAdmin')->willReturn($moduleAdmin);
		$acl->method('isSubAdmin')->willReturn($subAdmin);

		return new DatatableTestBuilder(DatatableTestFactory::buildService(), $this->parameters, $acl, $this->createStub(Translator::class));
	}

	#[Group('units')]
	public function testTheStructureIsEmptyAtTheStart(): void
	{
		static::assertSame([], $this->builder()->getDatatableStructure());
	}

	/**
	 * The edge edition has one user and no companies: no filter for the owner or the company.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTheEdgeEditionAddsNothing(): void
	{
		$builder = $this->builder(Config::PLATFORM_EDITION_EDGE, true, true);

		$builder->configureParameters(1);

		static::assertFalse($builder->moduleParametersAdded);
		static::assertFalse($this->parameters->hasParameter(BaseParameters::PARAMETER_UID));
		static::assertFalse($this->parameters->hasParameter('company_id'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testAUserGetsTheModuleParametersOnly(): void
	{
		$builder = $this->builder();

		$builder->configureParameters(1);

		static::assertTrue($builder->moduleParametersAdded);
		static::assertFalse($this->parameters->hasParameter(BaseParameters::PARAMETER_UID));
		static::assertFalse($this->parameters->hasParameter('company_id'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('adminProvider')]
	public function testAdminsGetTheOwnerAndTheCompanyFilter(bool $moduleAdmin, bool $subAdmin): void
	{
		$builder = $this->builder(Config::PLATFORM_EDITION_CORE, $moduleAdmin, $subAdmin);

		$builder->configureParameters(1);

		static::assertTrue($builder->moduleParametersAdded);
		static::assertTrue($this->parameters->hasParameter(BaseParameters::PARAMETER_UID));
		static::assertTrue($this->parameters->hasParameter('company_id'));
	}

	/**
	 * @return array<string, array{bool, bool}>
	 */
	public static function adminProvider(): array
	{
		return ['module admin' => [true, false], 'sub admin' => [false, true], 'both' => [true, true]];
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetermineParametersCreatesTheTableFieldsAndParsesTheInput(): void
	{
		$builder = $this->builder();

		$builder->determineParameters(['name' => ' Lobby ', 'elements_page' => '3', 'elements_per_page' => '25', 'sort_order' => 'desc']);

		$structure = $builder->getDatatableStructure();
		static::assertSame(['name', 'type'], array_map(static fn($field): string => $field->getName(), $structure['header']));
		static::assertSame('Lobby', $this->parameters->getValueOfParameter('name'));
		static::assertSame(3, $this->parameters->getValueOfParameter('elements_page'));
		static::assertSame(25, $this->parameters->getValueOfParameter('elements_per_page'));
		static::assertSame('DESC', $this->parameters->getValueOfParameter('sort_order'));
	}

	/**
	 * The sortable table fields are the only columns the list may be sorted by (the column is part of ORDER BY).
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('sortColumnProvider')]
	public function testOnlyASortableTableFieldCanBeSortedBy(string $requested, string $expected): void
	{
		$builder = $this->builder();

		$builder->determineParameters(['sort_column' => $requested]);

		static::assertSame($expected, $this->parameters->getValueOfParameter('sort_column'));
	}

	/**
	 * @return array<string, array{string, string}> requested column, column which is used (the default is "name")
	 */
	public static function sortColumnProvider(): array
	{
		return [
			'the sortable field'           => ['name', 'name'],
			'a field which is not sortable' => ['type', 'name'],
			'a column of the table'        => ['password', 'name'],
			'injection'                    => ['name; DROP TABLE templates', 'name'],
			'nothing'                      => ['', 'name'],
		];
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreatePaginationUsesTheParametersAndTheResultCount(): void
	{
		$builder = $this->builder();
		$builder->determineParameters(['elements_page' => '2', 'elements_per_page' => '10']);

		$builder->createPagination(35);

		static::assertSame(
			['«:1', '‹:1', '1:1', '2:2*', '3:3', '4:4', '›:3', '»:4'],
			$this->shortLinks($builder->getDatatableStructure()['pager'])
		);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreatePaginationWithoutThePager(): void
	{
		$builder = $this->builder();
		$builder->determineParameters(['elements_per_page' => '10']);

		$builder->createPagination(25, false, false);

		static::assertSame(['1:1*', '2:2', '3:3'], $this->shortLinks($builder->getDatatableStructure()['pager']));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreatePaginationWithoutResults(): void
	{
		$builder = $this->builder();
		$builder->determineParameters([]);

		$builder->createPagination(0);

		static::assertSame(['1:1*'], $this->shortLinks($builder->getDatatableStructure()['pager']));
	}

	#[Group('units')]
	public function testCreateDropDownWithTheDefaults(): void
	{
		$builder = $this->builder();

		$builder->createDropDown();

		static::assertSame(['min' => 10, 'max' => 100, 'steps' => 10], $builder->getDatatableStructure()['dropdown']);
	}

	#[Group('units')]
	public function testCreateDropDownWithOwnSettings(): void
	{
		$builder = $this->builder();

		$builder->createDropDown(5, 50, 15);

		static::assertSame(['min' => 5, 'max' => 50, 'steps' => 15], $builder->getDatatableStructure()['dropdown']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheTitleAndTheFormOfTheModuleAreInTheStructure(): void
	{
		$builder = $this->builder();
		$builder->determineParameters(['name' => 'Lobby']);

		$builder->buildTitle();
		$builder->collectFormElements();

		$structure = $builder->getDatatableStructure();
		static::assertSame('Test overview', $structure['title']);
		static::assertSame('Lobby', $structure['form']['name']->getValue());
	}

	/**
	 * @param list<array{name: string, page: int, active?: bool}> $links
	 * @return list<string>
	 */
	private function shortLinks(array $links): array
	{
		return array_map(static fn(array $link): string => $link['name'] . ':' . $link['page'] . (($link['active'] ?? false) ? '*' : ''), $links);
	}
}
