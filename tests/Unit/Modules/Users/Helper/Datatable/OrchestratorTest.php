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

namespace Tests\Unit\Modules\Users\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\Datatable\DatatableBuilder;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Helper\Datatable\Orchestrator;
use App\Modules\Users\Services\UsersDatatableService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private Orchestrator $orchestrator;
	private DatatableBuilder&MockObject $datatableBuilderMock;
	private DatatablePreparer&MockObject $datatablePreparerMock;
	private UsersDatatableService&MockObject $usersServiceMock;
	private UserSession&MockObject $userSessionMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->datatableBuilderMock = $this->createMock(DatatableBuilder::class);
		$this->datatablePreparerMock = $this->createMock(DatatablePreparer::class);
		$this->usersServiceMock = $this->createMock(UsersDatatableService::class);
		$this->userSessionMock = $this->createMock(UserSession::class);

		$this->orchestrator = new Orchestrator(
			$this->datatableBuilderMock,
			$this->datatablePreparerMock,
			$this->usersServiceMock,
			$this->userSessionMock
		);

		$this->usersServiceMock->method('getCurrentTotalResult')->willReturn(42);
	}


	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testConfigure(): void
	{
		$this->expectUntouched($this->usersServiceMock, $this->datatablePreparerMock);
		$mockUID = 12345;
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn($mockUID);

		$this->datatableBuilderMock->expects($this->once())
			->method('configureParameters')
			->with($mockUID);

		$this->orchestrator->configure();
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessSubmittedUserInput(): void
	{
		$this->expectUntouched($this->datatablePreparerMock, $this->userSessionMock);
		$this->datatableBuilderMock->expects($this->once())->method('determineParameters')->with(['page' => 2]);

		$this->usersServiceMock->expects($this->once())->method('loadDatatable');

		$this->orchestrator->processSubmittedUserInput(['page' => 2]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareDataGrid(): void
	{
		$this->usersServiceMock->expects($this->once())->method('getCurrentTotalResult')->willReturn(42);
		$this->expectUntouched($this->datatablePreparerMock, $this->userSessionMock);
		// Arrange
		$this->datatableBuilderMock->expects($this->once())->method('buildTitle');
		$this->datatableBuilderMock->expects($this->once())->method('collectFormElements');
		$this->datatableBuilderMock->expects($this->once())
			->method('createPagination')
			->with(42);
		$this->datatableBuilderMock->expects($this->once())->method('createDropDown');
		$this->datatableBuilderMock->expects($this->never())->method('createTableFields');

		// Act
		$result = $this->orchestrator->prepareDataGrid();

		// Assert
		static::assertSame($this->orchestrator, $result);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testPrepareUITemplate(): void
	{
		$mockUID = 12345;
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn($mockUID);
		$this->orchestrator->configure();

		$mockDatatableStructure = [
			'pager' => ['page_1', 'page_2'],
			'dropdown' => ['option_1', 'option_2'],
			'form' => ['field_1' => 'value_1'],
			'header' => ['header_1', 'header_2'],
			'title' => 'Mock Title'
		];

		$mockPagination = [
			'dropdown' => 'mock_dropdown',
			'links' => 'mock_links',
		];

		$mockFormattedList = ['row_1', 'row_2'];
		$currentTotalResult = 42;

		$this->datatableBuilderMock->expects($this->once())
			->method('getDatatableStructure')
			->willReturn($mockDatatableStructure);

		$this->datatablePreparerMock->expects($this->once())
			->method('preparePagination')
			->with($mockDatatableStructure['pager'], $mockDatatableStructure['dropdown'])
			->willReturn($mockPagination);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareFilterForm')
			->with($mockDatatableStructure['form'])
			->willReturn(['prepared_filter_form']);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareAdd')
			->with('person-add')
			->willReturn(['prepared_add']);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareTableHeader')
			->with($mockDatatableStructure['header'], ['users', 'main'])
			->willReturn(['prepared_header']);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareSort')
			->willReturn(['prepared_sort']);

		$this->datatablePreparerMock->expects($this->once())
			->method('preparePage')
			->willReturn(['prepared_page']);

		$this->usersServiceMock->expects($this->once())
			->method('getCurrentTotalResult')
			->willReturn($currentTotalResult);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareTableBody')
			->with($this->usersServiceMock->getCurrentFilterResults(), $mockDatatableStructure['header'], static::anything())
			->willReturn($mockFormattedList);

		$result = $this->orchestrator->prepareUITemplate();

		static::assertEquals([
			'filter_elements' => ['prepared_filter_form'],
			'pagination_dropdown' => 'mock_dropdown',
			'pagination_links' => 'mock_links',
			'has_add' => ['prepared_add'],
			'results_header' => ['prepared_header'],
			'results_list' => $mockFormattedList,
			'results_count' => $currentTotalResult,
			'title' => 'Mock Title',
			'template_name' => 'users/datatable',
			'module_name' => 'users',
			'additional_css' => ['/css/users/datatable.css'],
			'footer_modules' => ['/js/users/datatable/init.js'],
			'sort' => ['prepared_sort'],
			'page' => ['prepared_page']
		], $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
