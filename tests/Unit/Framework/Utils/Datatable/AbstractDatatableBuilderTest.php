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

use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConcreteDatatableBuilder extends AbstractDatatableBuilder
{

	public function buildTitle(): void{}

	public function configureParameters(int $UID): void{}

	public function determineParameters(): void	{}

	public function collectFormElements(): void{}

	public function createTableFields(): static {return $this;}
}

class AbstractDatatableBuilderTest extends TestCase
{
	private AbstractDatatableBuilder $datatableBuilder;
	private BuildService&MockObject $buildServiceMock;
	private BaseFilterParameters&MockObject $parametersMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->buildServiceMock = $this->createMock(BuildService::class);
		$this->parametersMock = $this->createMock(BaseFilterParameters::class);

		$this->datatableBuilder = new ConcreteDatatableBuilder($this->buildServiceMock, $this->parametersMock);
	}

	#[Group('units')]
	public function testCreatePaginationWithValidData(): void
	{
		$resultCount = 100;

		$currentPage = 1;
		$itemsPerPage = 10;
		$paginationLinks = ['page1', 'page2', 'page3'];

		$this->parametersMock
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, $currentPage],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, $itemsPerPage],
			]);

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationLinks')
			->with($currentPage, $itemsPerPage, $resultCount, true, true)
			->willReturn($paginationLinks);

		$this->datatableBuilder->createPagination($resultCount);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('pager', $datatableStructure);
		static::assertEquals($paginationLinks, $datatableStructure['pager']);
	}

	#[Group('units')]
	public function testCreatePaginationWithoutPager(): void
	{
		$resultCount = 50;

		$currentPage = 1;
		$itemsPerPage = 20;
		$paginationLinks = ['page1'];

		$this->parametersMock
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, $currentPage],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, $itemsPerPage]
			]);

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationLinks')
			->with($currentPage, $itemsPerPage, $resultCount, false, false)
			->willReturn($paginationLinks);

		$this->datatableBuilder->createPagination($resultCount, false, false);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('pager', $datatableStructure);
		static::assertEquals($paginationLinks, $datatableStructure['pager']);
	}

	#[Group('units')]
	public function testCreatePaginationWithZeroResults(): void
	{
		$resultCount = 0;

		$currentPage = 1;
		$itemsPerPage = 10;
		$paginationLinks = [];

		$this->parametersMock
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, $currentPage],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, $itemsPerPage],
			]);

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationLinks')
			->with($currentPage, $itemsPerPage, $resultCount, true, true)
			->willReturn($paginationLinks);

		$this->datatableBuilder->createPagination($resultCount);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('pager', $datatableStructure);
		static::assertEquals($paginationLinks, $datatableStructure['pager']);
	}

	#[Group('units')]
	public function testCreateDropDownWithValidData(): void
	{
		$min = 10;
		$max = 50;
		$steps = 10;

		$dropdownOptions = [10, 20, 30, 40, 50];

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationDropDown')
			->with($min, $max, $steps)
			->willReturn($dropdownOptions);

		$this->datatableBuilder->createDropDown($min, $max, $steps);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('dropdown', $datatableStructure);
		static::assertEquals($dropdownOptions, $datatableStructure['dropdown']);
	}

	#[Group('units')]
	public function testCreateDropDownWithCustomSteps(): void
	{
		$min = 10;
		$max = 100;
		$steps = 20;

		$dropdownOptions = [10, 30, 50, 70, 90];

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationDropDown')
			->with($min, $max, $steps)
			->willReturn($dropdownOptions);

		$this->datatableBuilder->createDropDown($min, $max, $steps);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('dropdown', $datatableStructure);
		static::assertEquals($dropdownOptions, $datatableStructure['dropdown']);
	}

	#[Group('units')]
	public function testCreateDropDownWithEmptyRange(): void
	{
		$min = 0;
		$max = 0;
		$steps = 1;

		$dropdownOptions = [];

		$this->buildServiceMock
			->expects($this->once())
			->method('buildPaginationDropDown')
			->with($min, $max, $steps)
			->willReturn($dropdownOptions);

		$this->datatableBuilder->createDropDown($min, $max, $steps);

		$datatableStructure = $this->datatableBuilder->getDatatableStructure();
		static::assertArrayHasKey('dropdown', $datatableStructure);
		static::assertEquals($dropdownOptions, $datatableStructure['dropdown']);
	}

}