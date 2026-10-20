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
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Paginator\Builder;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Class BuildServiceTest
 *
 * Unit tests for the BuildService class.
 * This class tests the buildFormField method which creates a form field using the FormBuilder.
 */
class BuildServiceTest extends TestCase
{
	private BuildService $buildService;
	private Builder&MockObject $paginatorBuilderMock;
	private \App\Framework\Utils\Datatable\Results\Builder&MockObject $resultsBuilderMock;
	private FormBuilder&MockObject $formBuilderMock;
	private FieldInterface&Stub $fieldStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->fieldStub = $this->createStub(FieldInterface::class);

		$this->paginatorBuilderMock = $this->createMock(Builder::class);
		$this->resultsBuilderMock   = $this->createMock(\App\Framework\Utils\Datatable\Results\Builder::class);

		$this->buildService = new BuildService($this->formBuilderMock, $this->paginatorBuilderMock, $this->resultsBuilderMock);

	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormFieldReturnsFieldInterface(): void
	{
		$this->paginatorBuilderMock->expects($this->never())->method($this->anything());
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$attributes = [
			'name' => 'email',
			'type' => 'text',
			'label' => 'Email'
		];

		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with($attributes)
			->willReturn($this->fieldStub);

		$result = $this->buildService->buildFormField($attributes);

		static::assertSame($this->fieldStub, $result);
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormFieldWithEmptyAttributes(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->paginatorBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock
			->expects($this->once())
			->method('createField')
			->with([])
			->willReturn($this->fieldStub);

		$result = $this->buildService->buildFormField();

		static::assertSame($this->resultsBuilderMock, $this->buildService->getResultsBuilder());
		static::assertSame($this->fieldStub, $result);
	}

	#[Group('units')]
	public function testCreateDatatableFieldAddsField(): void
	{
		$this->paginatorBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$fieldName = 'name';

		$this->resultsBuilderMock
			->expects($this->once())
			->method('createField')
			->with($fieldName, true);

		$this->buildService->createDatatableField($fieldName, true);
	}

	#[Group('units')]
	public function testCreateDatatableFieldWithUnSortableField(): void
	{
		$this->paginatorBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$fieldName = 'age';

		$this->resultsBuilderMock
			->expects($this->once())
			->method('createField')
			->with($fieldName, false);

		$this->buildService->createDatatableField($fieldName, false);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetDatatableFieldsReturnsExpectedFields(): void
	{
		$this->paginatorBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$expectedFields = [
			$this->createStub(HeaderField::class),
			$this->createStub(HeaderField::class)
		];

		$this->resultsBuilderMock
			->expects($this->once())
			->method('getHeaderFields')
			->willReturn($expectedFields);

		$result = $this->buildService->getDatatableFields();

		static::assertSame($expectedFields, $result);
	}


	#[Group('units')]
	public function testBuildPaginationDropDownReturnsCorrectSettings(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$min = 5;
		$max = 50;
		$steps = 5;

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('createDropDown')
			->with($min, $max, $steps)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getDropDownSettings')
			->willReturn(['min' => 15, 'max' => 50, 'steps' => 5]);

		$result = $this->buildService->buildPaginationDropDown($min, $max, $steps);

		static::assertSame(['min' => 15, 'max' => 50, 'steps' => 5], $result);
	}

	#[Group('units')]
	public function testBuildPaginationDropDownWithDefaultValues(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->paginatorBuilderMock
			->expects($this->once())
			->method('createDropDown')
			->with(10, 100, 10)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getDropDownSettings')
			->willReturn(['min' => 10, 'max' => 100, 'steps' => 10]);

		$result = $this->buildService->buildPaginationDropDown();

		static::assertSame(['min' => 10, 'max' => 100, 'steps' => 10],$result);
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithValidParameters(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$currentPage = 1;
		$itemsPerPage = 10;
		$totalItems = 100;
		$expectedLinks = [
			['name' => '1', 'page' => 1],
			['name' => '2', 'page' => 2],
			['name' => '3', 'page' => 3]
		];

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('configure')
			->with($currentPage, $itemsPerPage, $totalItems, false, true)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('buildPagerLinks')
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getPagerLinks')
			->willReturn($expectedLinks);

		$result = $this->buildService->buildPaginationLinks($currentPage, $itemsPerPage, $totalItems);

		static::assertSame($expectedLinks, $result);
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithUsePagerTrue(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$currentPage = 2;
		$itemsPerPage = 20;
		$totalItems = 200;
		$expectedLinks = [
			['name' => '1', 'page' => 1],
			['name' => '2', 'page' => 2],
			['name' => '3', 'page' => 3]
		];

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('configure')
			->with($currentPage, $itemsPerPage, $totalItems, true, true)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('buildPagerLinks')
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getPagerLinks')
			->willReturn($expectedLinks);

		$result = $this->buildService->buildPaginationLinks($currentPage, $itemsPerPage, $totalItems, true);

		static::assertSame($expectedLinks, $result);
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithShortenedFalse(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$currentPage = 3;
		$itemsPerPage = 5;
		$totalItems = 50;
		$expectedLinks = [
			['name' => '1', 'page' => 1],
			['name' => '2', 'page' => 2],
			['name' => '3', 'page' => 3]
		];

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('configure')
			->with($currentPage, $itemsPerPage, $totalItems, false, false)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('buildPagerLinks')
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getPagerLinks')
			->willReturn($expectedLinks);

		$result = $this->buildService->buildPaginationLinks($currentPage, $itemsPerPage, $totalItems, false, false);

		static::assertSame($expectedLinks, $result);
	}

	#[Group('units')]
	public function testBuildPaginationLinksWithExceedingCurrentPage(): void
	{
		$this->resultsBuilderMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$currentPage = 15;
		$itemsPerPage = 10;
		$totalItems = 100;
		$expectedLinks = []; // Expected behavior for out-of-range currentPage can vary

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('configure')
			->with($currentPage, $itemsPerPage, $totalItems, false, true)
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('buildPagerLinks')
			->willReturnSelf();

		$this->paginatorBuilderMock
			->expects($this->once())
			->method('getPagerLinks')
			->willReturn($expectedLinks);

		$result = $this->buildService->buildPaginationLinks($currentPage, $itemsPerPage, $totalItems);

		static::assertSame($expectedLinks, $result);
	}


}