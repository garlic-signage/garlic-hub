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

namespace Tests\Unit\Framework\Utils\Datatable\Paginator;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreparerTest extends TestCase
{
	private Preparer $preparer;
	private BaseFilterParameters&MockObject $baseFilterParametersMock;
	private UrlBuilder&MockObject $urlBuilderMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->urlBuilderMock           = $this->createMock(UrlBuilder::class);
		$this->baseFilterParametersMock = $this->createMock(BaseFilterParameters::class);
		$this->preparer                 = new Preparer($this->urlBuilderMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareLinks(): void
	{
		// Arrange
		$pageLinks = [
			['page' => 1, 'name' => 'Page 1', 'active' => null],
			['page' => 2, 'name' => 'Page 2', 'active' => true]
		];

		$link = '/link/to/somewhere';

		$this->preparer->setBaseFilter($this->baseFilterParametersMock);

		$this->baseFilterParametersMock->expects($this->exactly(3))
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'name'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'asc'],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 100]
			]);
		$this->urlBuilderMock->expects($this->once())->method('setSortColumn')
			->with('name')->willReturn($this->urlBuilderMock);
		$this->urlBuilderMock->expects($this->once())->method('setSortOrder')
			->with('asc')->willReturn($this->urlBuilderMock);
		$this->urlBuilderMock->expects($this->once())->method('setElementsPerPage')
			->with(100)->willReturn($this->urlBuilderMock);

		$this->urlBuilderMock->expects($this->exactly(2))->method('buildFilterUrl')->willReturn($link);

		$expectedResult = [
			[
				'ELEMENTS_PAGELINK' => $link,
				'ELEMENTS_PAGENAME' => 'Page 1',
				'ELEMENTS_PAGENUMBER' => 1,
				'ELEMENTS_ACTIVE_PAGE' => ''
			],
			[
				'ELEMENTS_PAGELINK' => $link,
				'ELEMENTS_PAGENAME' => 'Page 2',
				'ELEMENTS_PAGENUMBER' => 2,
				'ELEMENTS_ACTIVE_PAGE' => 'active_page'
			],
		];

		$result = $this->preparer->prepareLinks($pageLinks);

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testRenderElementsPerSiteDropDown(): void
	{
		$settings = ['min' => 10, 'max' => 30, 'steps' => 10];
		$elementsPage = 2;
		$site = 'example-site';

		$this->preparer->setBaseFilter($this->baseFilterParametersMock)->setSite($site);

		$this->baseFilterParametersMock
			->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'name'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'asc'],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, $elementsPage],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 20]
			]);

		$this->urlBuilderMock->expects($this->once())->method('setSortColumn')
			->with('name')->willReturn($this->urlBuilderMock);
		$this->urlBuilderMock->expects($this->once())->method('setSortOrder')
			->with('asc')->willReturn($this->urlBuilderMock);
		$this->urlBuilderMock->expects($this->once())->method('setPage')
			->with($elementsPage)->willReturn($this->urlBuilderMock);

		$link = '/link/to/somewhere';

		$this->urlBuilderMock->expects($this->exactly(3))->method('buildFilterUrl')->willReturn($link);

		$expectedResult = [
			[
				'ELEMENTS_PER_PAGE_VALUE' => 10,
				'ELEMENTS_PER_PAGE_DATA_LINK' => $link,
				'ELEMENTS_PER_PAGE_NAME' => 10,
				'ELEMENTS_PER_PAGE_SELECTED' => ''
			],
			[
				'ELEMENTS_PER_PAGE_VALUE' => 20,
				'ELEMENTS_PER_PAGE_DATA_LINK' => $link,
				'ELEMENTS_PER_PAGE_NAME' => 20,
				'ELEMENTS_PER_PAGE_SELECTED' => 'selected'
			],
			[
				'ELEMENTS_PER_PAGE_VALUE' => 30,
				'ELEMENTS_PER_PAGE_DATA_LINK' => $link,
				'ELEMENTS_PER_PAGE_NAME' => 30,
				'ELEMENTS_PER_PAGE_SELECTED' => ''
			]
		];

		// Act
		$result = $this->preparer->prepareDropdown($settings);

		// Assert
		static::assertSame($expectedResult, $result);
	}
}
