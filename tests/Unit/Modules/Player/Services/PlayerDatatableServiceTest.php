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

namespace Tests\Unit\Modules\Player\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerDatatableService;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlayerDatatableServiceTest extends TestCase
{
	protected LoggerInterface&Stub $loggerStub;
	private PlayerFilterDefinition&Stub $filterDefinitionStub;
	private DatatableQuery&MockObject $datatableQueryMock;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private UserSession&Stub $userSessionStub;
	private PlayerDatatableService $service;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loggerStub       = $this->createStub(LoggerInterface::class);
		$this->filterDefinitionStub = $this->createStub(PlayerFilterDefinition::class);
		$this->datatableQueryMock   = $this->createMock(DatatableQuery::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->userSessionStub = $this->createStub(UserSession::class);
		$this->service = new PlayerDatatableService($this->filterDefinitionStub, $this->datatableQueryMock, $this->parametersMock, $this->aclValidatorMock, $this->userSessionStub, $this->loggerStub);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$this->userSessionStub->method('getUID')->willReturn(345);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(345)
			->willReturn(true);

		$this->parametersMock->expects($this->once())
			->method('getInputParametersArray')
			->willReturn(['empty']);

		$this->datatableQueryMock->expects($this->once())->method('countForModuleAdmin')
			->with($this->filterDefinitionStub, ['empty'])
			->willReturn(12);

		$result = [
			['id' => 1, 'name' => 'John Doe', 'age' => 29],
			['id' => 2, 'name' => 'Jane Smith', 'age' => 34],
			['id' => 3, 'name' => 'Alice Brown', 'age' => 25],
		];

		$this->datatableQueryMock->expects($this->once())->method('findForModuleAdmin')
			->with($this->filterDefinitionStub, ['empty'])
			->willReturn($result);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchForUser(): void
	{
		$this->userSessionStub->method('getUID')->willReturn(789);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(789)
			->willReturn(false);

		$this->parametersMock->expects($this->once())
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->datatableQueryMock->expects($this->once())->method('countForUser')
			->with($this->filterDefinitionStub, ['empty'], 789)
			->willReturn(12);

		$result = [
			['id' => 1, 'name' => 'John Doe', 'age' => 29],
			['id' => 2, 'name' => 'Jane Smith', 'age' => 34],
			['id' => 3, 'name' => 'Alice Brown', 'age' => 25],
		];

		$this->datatableQueryMock->expects($this->once())->method('findForUser')
			->with($this->filterDefinitionStub, ['empty'], 789)
			->willReturn($result);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

}
