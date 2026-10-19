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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\Datatable\DatatableFacade;
use App\Modules\Player\Helper\Datatable\DatatableBuilder;
use App\Modules\Player\Helper\Datatable\DatatablePreparer;
use App\Modules\Player\Services\PlayerDatatableService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatableFacadeTest extends TestCase
{
	private DatatableBuilder&MockObject $datatableBuilderMock;
	private DatatablePreparer&MockObject $datatablePreparerMock;
	private PlayerDatatableService&MockObject $playerServiceMock;
	private Translator&MockObject $translatorMock;
	private Session&MockObject $sessionMock;
	private DatatableFacade $datatableFacade;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->datatableBuilderMock = $this->createMock(DatatableBuilder::class);
		$this->datatablePreparerMock = $this->createMock(DatatablePreparer::class);
		$this->playerServiceMock = $this->createMock(PlayerDatatableService::class);
		$this->translatorMock = $this->createMock(Translator::class);
		$this->sessionMock = $this->createMock(Session::class);

		$this->datatableFacade = new DatatableFacade(
			$this->datatableBuilderMock,
			$this->datatablePreparerMock,
			$this->playerServiceMock
		);

		$this->playerServiceMock->method('getCurrentTotalResult')->willReturn(42);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testConfigure(): void
	{
		$mockUID = 12345;
		$mockUserData = ['UID' => $mockUID];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($mockUserData);

		$this->playerServiceMock->expects($this->once())->method('setUID')
			->with($mockUID);
		$this->datatableBuilderMock->expects($this->once())->method('configureParameters')
			->with($mockUID);
		$this->datatableBuilderMock->expects($this->once())->method('setTranslator')
			->with($this->translatorMock);
		$this->datatablePreparerMock->expects($this->once())->method('setTranslator')
			->with($this->translatorMock);
		$this->datatableFacade->configure($this->translatorMock, $this->sessionMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testProcessSubmittedUserInput(): void
	{
		$this->datatableBuilderMock->expects($this->once())->method('determineParameters')->with(['page' => 2]);
		$this->playerServiceMock->expects($this->once())->method('loadDatatable');
		$this->datatableFacade->processSubmittedUserInput(['page' => 2]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareDataGrid(): void
	{
		$this->datatableBuilderMock->expects($this->once())->method('buildTitle');
		$this->datatableBuilderMock->expects($this->once())->method('collectFormElements');
		$this->datatableBuilderMock->expects($this->once())
			->method('createPagination')
			->with(42);
		$this->datatableBuilderMock->expects($this->once())->method('createDropDown');
		$this->datatableBuilderMock->expects($this->once())->method('createTableFields');
		$result = $this->datatableFacade->prepareDataGrid();
		static::assertSame($this->datatableFacade, $result);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testPrepareContextMenu(): void
	{
		$mockedContextMenu = [
			['label' => 'Edit', 'action' => 'edit'],
			['label' => 'Delete', 'action' => 'delete']
		];

		$this->datatablePreparerMock->expects($this->once())
			->method('formatContextMenu')
			->willReturn($mockedContextMenu);

		$result = $this->datatableFacade->prepareContextMenu();
		static::assertSame($mockedContextMenu, $result);
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \DateMalformedStringException
	 */
	#[Group('units')]
	public function testPrepareUITemplate(): void
	{
		$mockUserData = ['UID' => 1];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($mockUserData);

		$this->datatableFacade->configure($this->translatorMock, $this->sessionMock);

		$datatableStructure = [
			'pager' => ['links' => ['prev' => '...', 'next' => '...']],
			'dropdown' => ['options' => [10, 20, 30]],
			'form' => ['field1' => 'value1'],
			'header' => ['field1' => 'Header 1'],
			'title' => 'Test Title',
		];

		$pagination = [
			'dropdown' => ['10', '20', '30'],
			'links' => ['prev' => '...', 'next' => '...'],
		];

		$this->datatableBuilderMock->expects($this->once())->method('getDatatableStructure')
			->willReturn($datatableStructure);

		$this->datatablePreparerMock->expects($this->once())
			->method('preparePagination')
			->with($datatableStructure['pager'], $datatableStructure['dropdown'])
			->willReturn($pagination);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareFilterForm')
			->with($datatableStructure['form'])
			->willReturn(['filter' => 'form']);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareTableHeader')
			->with($datatableStructure['header'], ['player', 'main'])
			->willReturn(['header' => 'formatted']);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareSort')
			->willReturn(['sort_config']);

		$this->datatablePreparerMock->expects($this->once())
			->method('preparePage')
			->willReturn(['pagination_config']);

		$this->playerServiceMock->expects($this->once())
			->method('getCurrentTotalResult')
			->willReturn(42);

		$this->datatablePreparerMock->expects($this->once())
			->method('prepareTableBody')
			->with($this->playerServiceMock->getCurrentFilterResults(), $datatableStructure['header'], 1)
			->willReturn(['body' => 'rows']);

		$result = $this->datatableFacade->prepareUITemplate();

		$expectedResult = [
			'filter_elements' => ['filter' => 'form'],
			'pagination_dropdown' => ['10', '20', '30'],
			'pagination_links' => ['prev' => '...', 'next' => '...'],
			'has_add' => [],
			'results_header' => ['header' => 'formatted'],
			'results_list' => ['body' => 'rows'],
			'results_count' => 42,
			'title' => 'Test Title',
			'template_name' => 'player/datatable',
			'module_name' => 'player',
			'additional_css' => ['/css/player/datatable.css'],
			'footer_modules' => ['/js/player/datatable/init.js'],
			'sort' => ['sort_config'],
			'page' => ['pagination_config']
		];

		static::assertSame($expectedResult, $result);
	}
}
