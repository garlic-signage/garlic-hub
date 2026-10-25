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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Paginator\Builder as PaginatorBuilder;
use App\Framework\Utils\Datatable\Results\Builder as ResultsBuilder;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\TextField;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Security\MemorySession;

/**
 * BuildService with the real form builder and the real builders for the pager and the table.
 */
class BuildServiceTest extends TestCase
{
	private BuildService $buildService;
	private ResultsBuilder $resultsBuilder;

	protected function setUp(): void
	{
		parent::setUp();

		$formBuilder = new FormBuilder(
			new FieldsFactory(),
			new FieldsRenderFactory(),
			new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession())
		);

		$this->resultsBuilder = new ResultsBuilder();
		$this->buildService   = new BuildService($formBuilder, new PaginatorBuilder(), $this->resultsBuilder);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormFieldCreatesTheFieldOfTheType(): void
	{
		$text     = $this->buildService->buildFormField(['type' => FieldType::TEXT, 'id' => 'name', 'name' => 'name', 'value' => 'Anna']);
		$dropdown = $this->buildService->buildFormField(['type' => FieldType::DROPDOWN, 'id' => 'type', 'options' => ['a' => 'A']]);

		static::assertInstanceOf(TextField::class, $text);
		static::assertSame('Anna', $text->getValue());
		static::assertInstanceOf(DropdownField::class, $dropdown);
		static::assertSame(['a' => 'A'], $dropdown->getOptions());
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormFieldWithAnInvalidTypeThrows(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Invalid field type');

		$this->buildService->buildFormField(['type' => 'nothing']);
	}

	#[Group('units')]
	public function testTheTableFieldsKeepTheirOrderNameAndSortability(): void
	{
		$this->buildService->createDatatableField('name', true);
		$this->buildService->createDatatableField('type', false);

		$fields = $this->buildService->getDatatableFields();

		static::assertSame(['name', 'type'], array_map(static fn($field): string => $field->getName(), $fields));
		static::assertSame([true, false], array_map(static fn($field): bool => $field->isSortable(), $fields));
	}

	#[Group('units')]
	public function testThereAreNoTableFieldsAtTheStart(): void
	{
		static::assertSame([], $this->buildService->getDatatableFields());
	}

	#[Group('units')]
	public function testTheResultsBuilderIsTheOneOfTheService(): void
	{
		static::assertSame($this->resultsBuilder, $this->buildService->getResultsBuilder());

		$this->buildService->createDatatableField('name', true);

		static::assertCount(1, $this->resultsBuilder->getHeaderFields());
	}

	#[Group('units')]
	public function testBuildPaginationDropDown(): void
	{
		static::assertSame(['min' => 5, 'max' => 50, 'steps' => 5], $this->buildService->buildPaginationDropDown(5, 50, 5));
	}

	#[Group('units')]
	public function testBuildPaginationDropDownWithTheDefaults(): void
	{
		static::assertSame(['min' => 10, 'max' => 100, 'steps' => 10], $this->buildService->buildPaginationDropDown());
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithoutThePagerArrows(): void
	{
		$links = $this->buildService->buildPaginationLinks(2, 10, 30);

		static::assertSame(
			[['name' => '1', 'page' => 1, 'active' => false], ['name' => '2', 'page' => 2, 'active' => true], ['name' => '3', 'page' => 3, 'active' => false]],
			$links
		);
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithThePagerArrows(): void
	{
		$links = $this->buildService->buildPaginationLinks(2, 10, 30, true);

		static::assertSame(['«', '‹', '1', '2', '3', '›', '»'], array_column($links, 'name'));
	}

	#[Group('units')]
	public function testBuildPaginationLinksAreShortenedByDefault(): void
	{
		$shortened = $this->buildService->buildPaginationLinks(1, 10, 200);
		$long      = $this->buildService->buildPaginationLinks(1, 10, 200, false, false);

		static::assertSame(['1', '2', '3', '18', '19', '20'], array_column($shortened, 'name'));
		static::assertCount(20, $long);
	}

	#[Group('units')]
	public function testAPageBehindTheLastOneIsCorrectedToThePagesThereAre(): void
	{
		$links = $this->buildService->buildPaginationLinks(99, 10, 20);

		static::assertSame(['1', '2'], array_column($links, 'name'));
		static::assertSame([false, false], array_column($links, 'active'));
	}
}
