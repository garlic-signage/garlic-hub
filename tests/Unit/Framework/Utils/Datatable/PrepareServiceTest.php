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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PrepareServiceTest extends TestCase
{
	private PrepareService $prepareService;
	private HeaderPreparer&MockObject $headerPreparerMock;
	private BodyPreparer&Stub $bodyPreparerStub;
	private Preparer&MockObject $paginationPreparerMock;
	private FormBuilder&MockObject $formBuilderMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->headerPreparerMock = $this->createMock(HeaderPreparer::class);
		$this->bodyPreparerStub = $this->createStub(BodyPreparer::class);
		$this->paginationPreparerMock = $this->createMock(Preparer::class);
		$this->formBuilderMock = $this->createMock(FormBuilder::class);

		$this->prepareService = new PrepareService(
			$this->formBuilderMock,
			$this->paginationPreparerMock,
			$this->headerPreparerMock,
			$this->bodyPreparerStub
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFormReturnsPreparedFormArray(): void
	{
		$this->headerPreparerMock->expects($this->never())->method($this->anything());
		$this->paginationPreparerMock->expects($this->never())->method($this->anything());
		$datatableForm = [
			'field1' => $this->createStub(FieldInterface::class),
			'field2' => $this->createStub(FieldInterface::class)
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
		$this->headerPreparerMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
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
			$this->createStub(HeaderField::class),
			$this->createStub(HeaderField::class)
		];
		$langModules = ['module1', 'module2'];
		$expectedPreparedHeader = [
			['preparedField1' => $this->createStub(FieldInterface::class)],
			['preparedField2' => $this->createStub(FieldInterface::class)]
		];

		$basefilterParameterStub = $this->createStub(BaseFilterParameters::class);
		$this->paginationPreparerMock->expects($this->once())->method('setSite')->with('testModule')->willReturnSelf();
		$this->paginationPreparerMock->expects($this->once())->method('setBaseFilter')->with($basefilterParameterStub)->willReturnSelf();
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->prepareService->configure($basefilterParameterStub, 'testModule');
		$this->headerPreparerMock
			->expects($this->once())
			->method('configure')
			->with($basefilterParameterStub, 'testModule', $langModules);

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
		$this->expectUntouched($this->headerPreparerMock, $this->paginationPreparerMock, $this->formBuilderMock);
		$result = $this->prepareService->getBodyPreparer();
		static::assertSame($this->bodyPreparerStub, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
