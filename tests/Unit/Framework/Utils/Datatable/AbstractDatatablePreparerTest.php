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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\Html\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ConcreteDatatablePreparer extends AbstractDatatablePreparer
{
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array {return [];}
}
class AbstractDatatablePreparerTest extends TestCase
{
	private AbstractDatatablePreparer $datatablePreparer;
	private PrepareService&MockObject $prepareServiceMock;
	private BaseFilterParameters&Stub $parametersStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->prepareServiceMock = $this->createMock(PrepareService::class);
		$this->parametersStub = $this->createStub(BaseFilterParameters::class);
		$this->datatablePreparer = new ConcreteDatatablePreparer('TestModule', $this->prepareServiceMock, $this->parametersStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFilterForm(): void
	{
		$params = [
			'test' => $this->createStub(FieldInterface::class)
		];
		$this->prepareServiceMock->expects($this->once())->method('prepareForm')->with($params);

		$this->datatablePreparer->prepareFilterForm($params);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreparePagination(): void
	{
		$params1 = [['name' => 'test1', 'page' => 1, 'active' => null]];
		$params2 = ['min' => 1, 'max' => 10, 'steps' => 5];
		$this->prepareServiceMock->expects($this->once())->method('preparePagination')->with($params1, $params2);

		$this->datatablePreparer->preparePagination($params1, $params2);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareTableHeader(): void
	{
		$params1 = [
			$this->createStub(HeaderField::class),
			$this->createStub(HeaderField::class)
		];
		$params2 = ['lang1', 'lang22'];
		$this->prepareServiceMock->expects($this->once())->method('prepareDatatableHeader')->with($params1, $params2);

		$this->datatablePreparer->prepareTableHeader($params1, $params2);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareAddWithoutParams(): void
	{
		$this->prepareServiceMock->expects($this->never())->method($this->anything());
		$translatorMock = $this->createMock(Translator::class);
		$this->datatablePreparer->setTranslator($translatorMock);

		$translatorMock->expects($this->once())->method('translate')
			->with('add', 'TestModule')->willReturn('test');

		$expected = [
			'ADD_BI_ICON' => 'folder-plus',
			'LANG_ELEMENTS_ADD_LINK' => 'test',
			'ELEMENTS_ADD_LINK' => '#'
		];

		$result = $this->datatablePreparer->prepareAdd();

		static::assertSame($expected, $result);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareAddWithParam(): void
	{
		$this->prepareServiceMock->expects($this->never())->method($this->anything());
		$translatorMock = $this->createMock(Translator::class);
		$this->datatablePreparer->setTranslator($translatorMock);

		$translatorMock->expects($this->once())->method('translate')
			->with('add', 'TestModule')->willReturn('test2');

		$expected = [
			'ADD_BI_ICON' => 'hurz',
			'LANG_ELEMENTS_ADD_LINK' => 'test2',
			'ELEMENTS_ADD_LINK' => '#'
		];

		$result = $this->datatablePreparer->prepareAdd('hurz');

		static::assertSame($expected, $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPrepareSort(): void
	{
		$this->prepareServiceMock->expects($this->never())->method($this->anything());

		$this->parametersStub
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, 'current_column'],
				[BaseFilterParametersInterface::PARAMETER_SORT_ORDER, 'asc|desc']
			]);


		$expected = [
			'column' => 'current_column',
			'order' => 'asc|desc',
		];

		$result = $this->datatablePreparer->prepareSort();

		static::assertSame($expected, $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreparePage(): void
	{
		$this->prepareServiceMock->expects($this->never())->method($this->anything());
		$this->parametersStub
			->method('getValueOfParameter')
			->willReturnMap([
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE, 1],
				[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE, 100]
			]);

		$expected = ['current' => 1, 'num_elements' => 100,];
		$result   = $this->datatablePreparer->preparePage();

		static::assertSame($expected, $result);
	}
}