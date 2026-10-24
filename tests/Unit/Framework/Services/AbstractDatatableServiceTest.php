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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConcreteDatatableService extends AbstractDatatableService
{
	public function loadDatatable(): void {}
}

/**
 * The service hands the input fields to the query and keeps what comes back. The stubs answer only when they get
 * the definition, the fields and the user which belong to the call, so a wrong call shows up as a wrong result.
 * With a real query and database this is tested in tests/Integration/Framework/Services.
 */
class AbstractDatatableServiceTest extends TestCase
{
	private const array FIELDS = ['name' => ['value' => 'Anna']];

	private FilterDefinition&Stub $definitionStub;
	private DatatableQuery&Stub $datatableQueryStub;
	private BaseParameters&Stub $parametersStub;
	private ConcreteDatatableService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->definitionStub     = $this->createStub(FilterDefinition::class);
		$this->datatableQueryStub = $this->createStub(DatatableQuery::class);
		$this->parametersStub     = $this->createStub(BaseParameters::class);
		$this->parametersStub->method('getInputParametersArray')->willReturn(self::FIELDS);

		$this->service = new ConcreteDatatableService(new NullLogger(), $this->datatableQueryStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$rows = [['key' => 'value']];
		$this->datatableQueryStub->method('countForModuleAdmin')->willReturnCallback(
			fn(FilterDefinition $definition, array $fields): int => $definition === $this->definitionStub && $fields === self::FIELDS ? 12 : -1
		);
		$this->datatableQueryStub->method('findForModuleAdmin')->willReturnCallback(
			fn(FilterDefinition $definition, array $fields): array => $definition === $this->definitionStub && $fields === self::FIELDS ? $rows : []
		);

		$this->service->fetchForModuleAdmin($this->definitionStub, $this->parametersStub);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($rows, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForUser(): void
	{
		$rows = [['key' => 'value']];
		$this->datatableQueryStub->method('countForUser')->willReturnCallback(
			fn(FilterDefinition $definition, array $fields, int $UID): int => $definition === $this->definitionStub && $fields === self::FIELDS && $UID === 123 ? 12 : -1
		);
		$this->datatableQueryStub->method('findForUser')->willReturnCallback(
			fn(FilterDefinition $definition, array $fields, int $UID): array => $definition === $this->definitionStub && $fields === self::FIELDS && $UID === 123 ? $rows : []
		);

		$this->service->fetchForUser($this->definitionStub, $this->parametersStub, 123);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($rows, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchReturnsTheServiceAndReplacesTheLastResult(): void
	{
		$this->datatableQueryStub->method('countForModuleAdmin')->willReturnOnConsecutiveCalls(5, 0);
		$this->datatableQueryStub->method('findForModuleAdmin')->willReturnOnConsecutiveCalls([['id' => 1]], []);

		$first  = $this->service->fetchForModuleAdmin($this->definitionStub, $this->parametersStub);
		static::assertSame($this->service, $first);
		static::assertSame(5, $this->service->getCurrentTotalResult());

		$this->service->fetchForModuleAdmin($this->definitionStub, $this->parametersStub);

		static::assertSame(0, $this->service->getCurrentTotalResult());
		static::assertSame([], $this->service->getCurrentFilterResults());
	}
}
