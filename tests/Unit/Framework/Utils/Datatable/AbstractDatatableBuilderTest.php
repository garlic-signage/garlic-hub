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

use App\Shared\Acl\AbstractAclValidator;
use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\AbstractDatatableBuilder;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\Datatable\Results\HeaderField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConcreteDatatableBuilder extends AbstractDatatableBuilder
{

	public bool $moduleParametersAdded = false;

	public function buildTitle(): void{}

	public function collectFormElements(): void{}

	public function createTableFields(): static {return $this;}

	protected function addModuleParameters(): void
	{
		$this->moduleParametersAdded = true;
	}
}

class AbstractDatatableBuilderTest extends TestCase
{
	private ConcreteDatatableBuilder $datatableBuilder;
	private BuildService&MockObject $buildServiceMock;
	private BaseFilterParameters&MockObject $parametersMock;
	private AbstractAclValidator&MockObject $aclValidatorMock;
	private Config&Stub $configStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->buildServiceMock = $this->createMock(BuildService::class);
		$this->parametersMock = $this->createMock(BaseFilterParameters::class);
		$this->aclValidatorMock = $this->createMock(AbstractAclValidator::class);
		$this->configStub = $this->createStub(Config::class);
		$this->aclValidatorMock->method('getConfig')->willReturn($this->configStub);

		$this->datatableBuilder = new ConcreteDatatableBuilder($this->buildServiceMock, $this->parametersMock, $this->aclValidatorMock, $this->createStub(Translator::class));
	}

	#[Group('units')]
	public function testConfigureParametersInEdgeEdition(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->aclValidatorMock->expects($this->never())->method('isModuleAdmin');
		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addCompany');

		$this->datatableBuilder->configureParameters(1);

		static::assertFalse($this->datatableBuilder->moduleParametersAdded);
	}

	#[Group('units')]
	public function testConfigureParametersForModuleAdmin(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')->with(1)->willReturn(true);
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addCompany');

		$this->datatableBuilder->configureParameters(1);

		static::assertTrue($this->datatableBuilder->moduleParametersAdded);
	}

	#[Group('units')]
	public function testConfigureParametersForSubAdmin(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);
		$this->aclValidatorMock->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->once())->method('isSubAdmin')->with(1)->willReturn(true);
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addCompany');

		$this->datatableBuilder->configureParameters(1);

		static::assertTrue($this->datatableBuilder->moduleParametersAdded);
	}

	#[Group('units')]
	public function testConfigureParametersForUser(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isSubAdmin')->willReturn(false);
		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addCompany');

		$this->datatableBuilder->configureParameters(1);

		static::assertTrue($this->datatableBuilder->moduleParametersAdded);
	}

	#[Group('units')]
	public function testDetermineParameters(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->buildServiceMock->expects($this->once())->method('getDatatableFields')->willReturn([
			new HeaderField()->setName('name')->sortable(true),
			new HeaderField()->setName('type')->sortable(false)
		]);
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with(['some' => 'input']);
		$this->parametersMock->expects($this->once())->method('parseInputFilterAllUsers')->with(['name']);

		$this->datatableBuilder->determineParameters(['some' => 'input']);
	}

	#[Group('units')]
	public function testCreatePaginationWithValidData(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$resultCount = 100;

		$currentPage = 1;
		$itemsPerPage = 10;
		$paginationLinks = ['page1', 'page2', 'page3'];

		$this->parametersMock
			->expects($this->atLeastOnce())->method('getValueOfParameter')
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
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$resultCount = 50;

		$currentPage = 1;
		$itemsPerPage = 20;
		$paginationLinks = ['page1'];

		$this->parametersMock
			->expects($this->atLeastOnce())->method('getValueOfParameter')
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
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$resultCount = 0;

		$currentPage = 1;
		$itemsPerPage = 10;
		$paginationLinks = [];

		$this->parametersMock
			->expects($this->atLeastOnce())->method('getValueOfParameter')
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
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
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
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
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
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
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