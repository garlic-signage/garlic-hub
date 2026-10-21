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

namespace Tests\Unit\Framework\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterDefinition;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseParameters;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConcreteDatatableService extends AbstractDatatableService
{
	public function loadDatatable(): void {}
}

class AbstractDatatableServiceTest extends TestCase
{
	private FilterDefinition&Stub $definitionStub;
	private DatatableQuery&MockObject $datatableQueryMock;
	private BaseParameters&MockObject $parametersMock;
	private ConcreteDatatableService $service;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->definitionStub     = $this->createStub(FilterDefinition::class);
		$this->datatableQueryMock = $this->createMock(DatatableQuery::class);
		$this->parametersMock     = $this->createMock(BaseParameters::class);
		$this->service = new ConcreteDatatableService($this->createStub(LoggerInterface::class), $this->datatableQueryMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$this->parametersMock->expects($this->once())
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->datatableQueryMock->expects($this->once())->method('countForModuleAdmin')
			->with($this->definitionStub, ['empty'])
			->willReturn(12);

		$result = [['key' => 'value']];
		$this->datatableQueryMock->expects($this->once())->method('findForModuleAdmin')
			->with($this->definitionStub, ['empty'])
			->willReturn($result);

		$this->service->fetchForModuleAdmin($this->definitionStub, $this->parametersMock);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForUser(): void
	{
		$this->parametersMock->expects($this->once())
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->datatableQueryMock->expects($this->once())->method('countForUser')
			->with($this->definitionStub, ['empty'], 123)
			->willReturn(12);

		$result = [['key' => 'value']];
		$this->datatableQueryMock->expects($this->once())->method('findForUser')
			->with($this->definitionStub, ['empty'], 123)
			->willReturn($result);

		$this->service->fetchForUser($this->definitionStub, $this->parametersMock, 123);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}
}
