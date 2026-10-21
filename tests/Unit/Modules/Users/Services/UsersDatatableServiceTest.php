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

namespace Tests\Unit\Modules\Users\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Helper\Datatable\UsersFilterDefinition;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\UsersDatatableService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsersDatatableServiceTest extends TestCase
{
	private UsersFilterDefinition&Stub $filterDefinitionStub;
	private DatatableQuery&MockObject $datatableQueryMock;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private UserSession&Stub $userSessionStub;
	private UsersDatatableService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$loggerStub = $this->createStub(LoggerInterface::class);
		$this->filterDefinitionStub = $this->createStub(UsersFilterDefinition::class);
		$this->datatableQueryMock   = $this->createMock(DatatableQuery::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->userSessionStub = $this->createStub(UserSession::class);
		$this->service = new UsersDatatableService($this->filterDefinitionStub, $this->datatableQueryMock, $this->parametersMock, $this->aclValidatorMock, $this->userSessionStub, $loggerStub);
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

		$this->datatableQueryMock->expects($this->once())->method('findForModuleAdmin')
			->with($this->filterDefinitionStub, ['empty'])
			->willReturn([['result' => 1]]);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame([['result' => 1]], $this->service->getCurrentFilterResults());
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


		$this->datatableQueryMock->expects($this->once())->method('findForUser')
			->with($this->filterDefinitionStub, ['empty'], 789)
			->willReturn([['result' => 1]]);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame([['result' => 1]], $this->service->getCurrentFilterResults());
	}
}
