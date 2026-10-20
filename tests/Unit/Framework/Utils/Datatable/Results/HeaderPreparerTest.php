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

namespace Tests\Unit\Framework\Utils\Datatable\Results;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\UrlBuilder;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class HeaderPreparerTest extends TestCase
{
	private TranslatorManager&MockObject $translatorManagerMock;
	private UrlBuilder&MockObject $urlBuilderMock;
	private BaseFilterParameters&Stub $filterParametersStub;
	private HeaderPreparer $headerPreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorManagerMock = $this->createMock(TranslatorManager::class);
		$this->urlBuilderMock = $this->createMock(UrlBuilder::class);
		$this->filterParametersStub = $this->createStub(BaseFilterParameters::class);

		$this->headerPreparer = new HeaderPreparer($this->translatorManagerMock, $this->urlBuilderMock);
	}

	#[Group('units')]
	public function testConfigureAddsNoModulesWhenEmptyArray(): void
	{
		$site = 'test-site';
		$languageModules = [];

		$this->urlBuilderMock->expects($this->once())->method('setSite')
			->with($site);

		$this->translatorManagerMock->expects($this->never())->method('addLanguageModule');

		$this->headerPreparer->configure($this->filterParametersStub, $site, $languageModules);
	}

	#[Group('units')]
	public function testConfigureSetsFilterParameterCorrectly(): void
	{
		$site = 'example-site';
		$languageModules = ['lang1', 'lang2'];

		$this->urlBuilderMock->expects($this->once())->method('setSite')->with($site);
		$modules = [];
		$this->translatorManagerMock->expects($this->exactly(2))->method('addLanguageModule')
			->willReturnCallback(function (string $module) use (&$modules) { $modules[] = $module; return $this->translatorManagerMock; });

		$this->headerPreparer->configure($this->filterParametersStub, $site, $languageModules);

		static::assertSame($languageModules, $modules);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeaderReturnsCorrectHeaderForSortableField(): void
	{
		$headerFieldStub = $this->createStub(HeaderField::class);
		$headerFieldStub->method('getName')->willReturn('column1');
		$headerFieldStub->method('isSortable')->willReturn(true);
		$this->filterParametersStub
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'column1'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'asc'],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, 1],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 10]
			]);

		$site = 'example-site';
		$languageModules = ['lang1'];

		$this->headerPreparer->configure($this->filterParametersStub, $site, $languageModules);


		$this->urlBuilderMock->expects($this->once())
			->method('buildFilterUrl')
			->willReturn('https://test.com/sort');

		$this->translatorManagerMock->expects($this->once())
			->method('translate')
			->with($headerFieldStub)
			->willReturn('Translated Column 1');

		$expectedResult = [
			[
				'CONTROL_NAME' => 'column1',
				'if_sortable' => [
					'SORTABLE_ORDER' => '▼',
					'SORT_CONTROL_NAME' => 'column1',
					'LINK_CONTROL_SORT_ORDER' => 'https://test.com/sort',
					'LANG_CONTROL_NAME' => 'Translated Column 1',
				],
			],
		];

		$result = $this->headerPreparer->prepareTableHeader([$headerFieldStub]);

		static::assertEquals($expectedResult, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeaderReturnsCorrectHeaderForSortableFieldDESC(): void
	{
		$headerFieldStub = $this->createStub(HeaderField::class);
		$headerFieldStub->method('getName')->willReturn('column1');
		$headerFieldStub->method('isSortable')->willReturn(true);
		$this->filterParametersStub
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'column1'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'desc'],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, 1],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 10]
			]);

		$site = 'example-site';
		$languageModules = ['lang1'];

		$this->headerPreparer->configure($this->filterParametersStub, $site, $languageModules);


		$this->urlBuilderMock->expects($this->once())
			->method('buildFilterUrl')
			->willReturn('https://test.com/sort');

		$this->translatorManagerMock->expects($this->once())
			->method('translate')
			->with($headerFieldStub)
			->willReturn('Translated Column 1');

		$expectedResult = [
			[
				'CONTROL_NAME' => 'column1',
				'if_sortable' => [
					'SORTABLE_ORDER' => '▲',
					'SORT_CONTROL_NAME' => 'column1',
					'LINK_CONTROL_SORT_ORDER' => 'https://test.com/sort',
					'LANG_CONTROL_NAME' => 'Translated Column 1',
				],
			],
		];

		$result = $this->headerPreparer->prepareTableHeader([$headerFieldStub]);

		static::assertEquals($expectedResult, $result);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeaderReturnsCorrectHeaderForSortableFieldNotEqual(): void
	{
		$headerFieldStub = $this->createStub(HeaderField::class);
		$headerFieldStub->method('getName')->willReturn('column_alternative');
		$headerFieldStub->method('isSortable')->willReturn(true);
		$this->filterParametersStub
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'column1'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'desc'],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, 1],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 10]
			]);

		$site = 'example-site';
		$languageModules = ['lang1'];

		$this->headerPreparer->configure($this->filterParametersStub, $site, $languageModules);


		$this->urlBuilderMock->expects($this->once())
			->method('buildFilterUrl')
			->willReturn('https://test.com/sort');

		$this->translatorManagerMock->expects($this->once())
			->method('translate')
			->with($headerFieldStub)
			->willReturn('Translated Column 1');

		$expectedResult = [
			[
				'CONTROL_NAME' => 'column_alternative',
				'if_sortable' => [
					'SORTABLE_ORDER' => '◆',
					'SORT_CONTROL_NAME' => 'column_alternative',
					'LINK_CONTROL_SORT_ORDER' => 'https://test.com/sort',
					'LANG_CONTROL_NAME' => 'Translated Column 1',
				],
			],
		];

		$result = $this->headerPreparer->prepareTableHeader([$headerFieldStub]);

		static::assertEquals($expectedResult, $result);
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeaderReturnsCorrectHeaderForNonSortableField(): void
	{
		$this->urlBuilderMock->expects($this->never())->method($this->anything());
		$headerFieldStub = $this->createStub(HeaderField::class);
		$headerFieldStub->method('getName')->willReturn('column2');
		$headerFieldStub->method('isSortable')->willReturn(false);

		$this->translatorManagerMock->expects($this->once())
			->method('translate')
			->with($headerFieldStub)
			->willReturn('Translated Column 2');

		$expectedResult = [
			[
				'CONTROL_NAME' => 'column2',
				'LANG_CONTROL_NAME_2' => 'Translated Column 2',
			],
		];

		$result = $this->headerPreparer->prepareTableHeader([$headerFieldStub]);

		static::assertEquals($expectedResult, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeaderHandlesEmptyInput(): void
	{
		$this->translatorManagerMock->expects($this->never())->method($this->anything());
		$this->urlBuilderMock->expects($this->never())->method($this->anything());
		$expectedResult = [];

		$result = $this->headerPreparer->prepareTableHeader([]);

		static::assertEquals($expectedResult, $result);
	}

}