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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\FormParameters\BaseFilterParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PrepareServiceTest extends TestCase
{
	private PrepareService $prepareService;
	private HeaderPreparer&MockObject $headerPreparerMock;
	private BodyPreparer&MockObject $bodyPreparerMock;
	private Preparer&MockObject $paginationPreparerMock;
	private FormBuilder&MockObject $formBuilderMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->headerPreparerMock = $this->createMock(HeaderPreparer::class);
		$this->bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$this->paginationPreparerMock = $this->createMock(Preparer::class);
		$this->formBuilderMock = $this->createMock(FormBuilder::class);

		$this->prepareService = new PrepareService(
			$this->formBuilderMock,
			$this->paginationPreparerMock,
			$this->headerPreparerMock,
			$this->bodyPreparerMock
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFormReturnsPreparedFormArray(): void
	{
		$datatableForm = [
			'field1' => $this->createMock(FieldInterface::class),
			'field2' => $this->createMock(FieldInterface::class)
		];
		$preparedForm = [
			'hidden' => [
				['key' => 'preparedField1', 'value' => 'preparedValue1']
			],
			'visible' => [
				['key' => 'preparedField2', 'value' => 'preparedValue2']
			]
		];

		$this->formBuilderMock
			->expects($this->once())
			->method('prepareForm')
			->with($datatableForm)
			->willReturn($preparedForm);

		$result = $this->prepareService->prepareForm($datatableForm);

		static::assertSame($preparedForm, $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testPreparePaginationReturnsPreparedPaginationArray(): void
	{
		$paginationLinks = [
			['name' => 'link1', 'page' => 1, 'active' => true],
			['name' => 'link2', 'page' => 2, 'active' => null]
		];
		$dropDownSettings = ['min' => 1, 'max' => 10, 'steps' => 2];
		$preparedLinks = ['preparedLink1' => 'preparedUrl1'];
		$preparedDropdown = ['preparedDropdown1' => 'value1'];

		$this->paginationPreparerMock
			->expects($this->once())
			->method('prepareLinks')
			->with($paginationLinks)
			->willReturn($preparedLinks);

		$this->paginationPreparerMock
			->expects($this->once())
			->method('prepareDropdown')
			->with($dropDownSettings)
			->willReturn($preparedDropdown);

		$result = $this->prepareService->preparePagination($paginationLinks, $dropDownSettings);

		static::assertSame(['links' => $preparedLinks, 'dropdown' => $preparedDropdown], $result);
	}

	/**
	 * @throws Exception
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareDatatableHeaderReturnsPreparedHeaderArray(): void
	{
		$tableHeaderFields = [
			$this->createMock(HeaderField::class),
			$this->createMock(HeaderField::class)
		];
		$langModules = ['module1', 'module2'];
		$expectedPreparedHeader = [
			['preparedField1' => $this->createMock(FieldInterface::class)],
			['preparedField2' => $this->createMock(FieldInterface::class)]
		];

		$basefilterParameterMock = $this->createMock(BaseFilterParameters::class);
		$this->prepareService->configure($basefilterParameterMock, 'testModule');
		$this->headerPreparerMock
			->expects($this->once())
			->method('configure')
			->with($basefilterParameterMock, 'testModule', $langModules);

		$this->headerPreparerMock
			->expects($this->once())
			->method('prepareTableHeader')
			->with($tableHeaderFields)
			->willReturn($expectedPreparedHeader);

		$result = $this->prepareService->prepareDatatableHeader($tableHeaderFields, $langModules);

		static::assertSame($expectedPreparedHeader, $result);
	}

	#[Group('units')]
	public function testGetBodyPreparer(): void
	{
		$result = $this->prepareService->getBodyPreparer();
		static::assertSame($this->bodyPreparerMock, $result);
	}

}