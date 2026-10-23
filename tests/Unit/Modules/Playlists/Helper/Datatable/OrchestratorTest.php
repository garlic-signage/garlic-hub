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


namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Datatable\Orchestrator;
use App\Modules\Playlists\Helper\Datatable\DatatableBuilder;
use App\Modules\Playlists\Helper\Datatable\DatatablePreparer;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private Orchestrator $orchestrator;
	private DatatableBuilder&MockObject $datatableBuilderMock;
	private DatatablePreparer&MockObject $datatablePreparerMock;
	private PlaylistsDatatableService&MockObject $playlistsServiceMock;
	private UserSession&MockObject $userSessionMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->datatableBuilderMock = $this->createMock(DatatableBuilder::class);
		$this->datatablePreparerMock = $this->createMock(DatatablePreparer::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsDatatableService::class);
		$this->userSessionMock = $this->createMock(UserSession::class);

		$this->orchestrator = new Orchestrator(
			$this->datatableBuilderMock,
			$this->datatablePreparerMock,
			$this->playlistsServiceMock,
			$this->userSessionMock
		);

		$this->playlistsServiceMock->method('getCurrentTotalResult')->willReturn(42);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testConfigure(): void
	{
		$this->expectUntouched($this->playlistsServiceMock, $this->datatablePreparerMock);
		$mockUID = 12345;
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn($mockUID);

		$this->datatableBuilderMock->expects($this->once())->method('configureParameters')
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
		$this->datatablePreparerMock->expects($this->never())->method($this->anything());
		$this->userSessionMock->expects($this->never())->method($this->anything());
		$this->datatableBuilderMock->expects($this->once())->method('determineParameters')->with(['page' => 2]);

		$this->playlistsServiceMock->expects($this->once())->method('loadDatatable');

		$this->orchestrator->processSubmittedUserInput(['page' => 2]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareDataGrid(): void
	{
		$this->playlistsServiceMock->expects($this->once())->method('getCurrentTotalResult')->willReturn(42);
		$this->datatablePreparerMock->expects($this->never())->method($this->anything());
		$this->userSessionMock->expects($this->never())->method($this->anything());
		$this->datatableBuilderMock->expects($this->once())->method('buildTitle');
		$this->datatableBuilderMock->expects($this->once())->method('collectFormElements');
		$this->datatableBuilderMock->expects($this->once())
			->method('createPagination')
			->with(42);
		$this->datatableBuilderMock->expects($this->once())->method('createDropDown');
		$this->datatableBuilderMock->expects($this->never())->method('createTableFields');
		$result = $this->orchestrator->prepareDataGrid();
		static::assertSame($this->orchestrator, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
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

		$this->datatableBuilderMock->expects($this->once())->method('getDatatableStructure')
			->willReturn($mockDatatableStructure);

		$this->datatablePreparerMock->expects($this->once())->method('preparePagination')
			->with($mockDatatableStructure['pager'], $mockDatatableStructure['dropdown'])
			->willReturn($mockPagination);

		$this->datatablePreparerMock->expects($this->once())->method('prepareFilterForm')
			->with($mockDatatableStructure['form'])
			->willReturn(['prepared_filter_form']);

		$this->datatablePreparerMock->expects($this->once())->method('prepareAdd')
			->with('folder-plus')
			->willReturn(['prepared_add']);

		$this->datatablePreparerMock->expects($this->once())->method('prepareTableHeader')
			->with($mockDatatableStructure['header'], ['playlists', 'main'])
			->willReturn(['prepared_header']);

		$this->datatablePreparerMock->expects($this->once())->method('prepareSort')
			->willReturn(['prepared_sort']);

		$this->datatablePreparerMock->expects($this->once())->method('preparePage')
			->willReturn(['prepared_page']);

		$this->playlistsServiceMock->expects($this->once())->method('getCurrentTotalResult')
			->willReturn($currentTotalResult);

		$currentFilterResults = [
			['playlist_id' => 1, 'playlist_name' => 'playlist1', 'description' => 'description1'],
			['playlist_id' => 2, 'playlist_name' => 'playlist2', 'description' => 'description2'],
			['playlist_id' => 3, 'playlist_name' => 'playlist3', 'description' => 'description3'],
			['playlist_id' => 4, 'playlist_name' => 'playlist4', 'description' => 'description4'],
		];
		$this->playlistsServiceMock->expects($this->exactly(1))->method('getCurrentFilterResults')
			->willReturn($currentFilterResults);

		$showerIds = array_column($currentFilterResults, 'playlist_id');
		$this->playlistsServiceMock->expects($this->once())
			->method('getPlaylistsInUse')->with($showerIds)
			->willReturn([]);

		$this->datatablePreparerMock->expects($this->once())->method('setUsedPlaylists')->with([]);

		$this->datatablePreparerMock->expects($this->once())->method('prepareTableBody')
			->with($currentFilterResults, $mockDatatableStructure['header'], static::anything())
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
			'template_name' => 'playlists/datatable',
			'module_name' => 'playlists',
			'additional_css' => ['/css/playlists/overview.css'],
			'footer_modules' => ['/js/playlists/overview/init.js'],
			'sort' => ['prepared_sort'],
			'page' => ['prepared_page']
		], $result);
	}

	#[Group('units')]
	public function testPrepareContextMenu(): void
	{
		$this->expectUntouched($this->datatableBuilderMock, $this->playlistsServiceMock, $this->userSessionMock);
		$mockContextMenu = [
			['name' => 'Option1', 'action' => 'action1'],
			['name' => 'Option2', 'action' => 'action2']
		];

		$this->datatablePreparerMock->expects($this->once())
			->method('formatContextMenu')
			->willReturn($mockContextMenu);

		$result = $this->orchestrator->prepareContextMenu();

		static::assertSame($mockContextMenu, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
