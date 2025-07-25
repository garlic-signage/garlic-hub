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

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Helper\Datatable\ControllerFacade;
use App\Modules\Users\Helper\Datatable\DatatableBuilder;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Services\UsersAdminService;
use App\Modules\Users\Services\UsersDatatableService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class ControllerFacadeTest extends TestCase
{
	private ControllerFacade $controllerFacade;
	private DatatableBuilder&MockObject $datatableBuilderMock;
	private DatatablePreparer&MockObject $datatablePreparerMock;
	private UsersDatatableService&MockObject $usersServiceMock;
	private UsersAdminService&MockObject $usersAdminServiceMock;
	private Translator&MockObject $translatorMock;
	private Session&MockObject $sessionMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->datatableBuilderMock = $this->createMock(DatatableBuilder::class);
		$this->datatablePreparerMock = $this->createMock(DatatablePreparer::class);
		$this->usersServiceMock = $this->createMock(UsersDatatableService::class);
		$this->usersAdminServiceMock = $this->createMock(UsersAdminService::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->sessionMock = $this->createMock(Session::class);

		$this->controllerFacade = new ControllerFacade(
			$this->datatableBuilderMock,
			$this->datatablePreparerMock,
			$this->usersServiceMock,
			$this->usersAdminServiceMock
		);

		$this->usersServiceMock->method('getCurrentTotalResult')->willReturn(42);
	}


	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigure(): void
	{
		$mockUID = 12345;
		$mockUserData = ['UID' => $mockUID];

		$this->sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn($mockUserData);

		$this->usersServiceMock->expects($this->once())
			->method('setUID')
			->with($mockUID);

		$this->datatableBuilderMock->expects($this->once())
			->method('configureParameters')
			->with($mockUID);

		$this->datatableBuilderMock->expects($this->once())
			->method('setTranslator')
			->with($this->translatorMock);

		$this->datatablePreparerMock->expects($this->once())
			->method('setTranslator')
			->with($this->translatorMock);

		$this->controllerFacade->configure($this->translatorMock, $this->sessionMock);

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUser(): void
	{
		$this->usersAdminServiceMock->expects($this->once())->method('deleteUser')
			->with(12345)
			->willReturn(true);

		static::assertTrue($this->controllerFacade->deleteUser(12345));
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testProcessSubmittedUserInput(): void
	{
		$this->datatableBuilderMock->expects($this->once())->method('determineParameters');

		$this->usersServiceMock->expects($this->once())->method('loadDatatable');

		$this->controllerFacade->processSubmittedUserInput();
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testPrepareDataGrid(): void
	{
		// Arrange
		$this->datatableBuilderMock->expects($this->once())->method('buildTitle');
		$this->datatableBuilderMock->expects($this->once())->method('collectFormElements');
		$this->datatableBuilderMock->expects($this->once())
			->method('createPagination')
			->with(42);
		$this->datatableBuilderMock->expects($this->once())->method('createDropDown');
		$this->datatableBuilderMock->expects($this->once())->method('createTableFields');

		// Act
		$result = $this->controllerFacade->prepareDataGrid();

		// Assert
		static::assertSame($this->controllerFacade, $result);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareUITemplate(): void
	{
		$mockUID = 12345;
		$mockUserData = ['UID' => $mockUID];

		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($mockUserData);
		$this->controllerFacade->configure($this->translatorMock, $this->sessionMock);

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

		$result = $this->controllerFacade->prepareUITemplate();

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
}
