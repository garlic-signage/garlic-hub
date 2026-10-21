<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Templates\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use App\Modules\Templates\Services\AclValidator;
use App\Modules\Templates\Services\TemplatesDatatableService;
use App\Modules\Templates\Services\TemplatesUsageService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplatesDatatableServiceTest extends TestCase
{
	private TemplatesFilterDefinition&Stub $filterDefinitionStub;
	private DatatableQuery&MockObject $datatableQueryMock;
	private Parameters&Stub $parametersStub;
	private TemplatesUsageService&MockObject $usageServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private TemplatesDatatableService $service;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws UserException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->filterDefinitionStub = $this->createStub(TemplatesFilterDefinition::class);
		$this->datatableQueryMock   = $this->createMock(DatatableQuery::class);
		$this->parametersStub   = $this->createStub(Parameters::class);
		$this->usageServiceMock = $this->createMock(TemplatesUsageService::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$userSessionStub        = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(5);

		$this->parametersStub->method('getInputParametersArray')->willReturn(['name' => ['value' => 'x']]);

		$this->service = new TemplatesDatatableService(
			$this->filterDefinitionStub,
			$this->datatableQueryMock,
			$this->parametersStub,
			$userSessionStub,
			$this->usageServiceMock,
			$this->aclValidatorMock,
			$this->createStub(LoggerInterface::class)
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCheckDisplayRightsUsesSessionUser(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(5)->willReturn(true);
		$this->datatableQueryMock->expects($this->never())->method($this->anything());
		$this->usageServiceMock->expects($this->never())->method($this->anything());

		static::assertTrue($this->service->checkDisplayRights());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCheckDisplayRightsDenied(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(5)->willReturn(false);
		$this->datatableQueryMock->expects($this->never())->method($this->anything());
		$this->usageServiceMock->expects($this->never())->method($this->anything());

		static::assertFalse($this->service->checkDisplayRights());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadDatatableForModuleAdmin(): void
	{
		$results = [['template_id' => 1], ['template_id' => 2]];
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')->with(5)->willReturn(true);
		$this->datatableQueryMock->expects($this->once())->method('countForModuleAdmin')
			->with($this->filterDefinitionStub, ['name' => ['value' => 'x']])
			->willReturn(2);
		$this->datatableQueryMock->expects($this->once())->method('findForModuleAdmin')
			->with($this->filterDefinitionStub, ['name' => ['value' => 'x']])
			->willReturn($results);
		$this->usageServiceMock->expects($this->never())->method($this->anything());

		$this->service->loadDatatable();

		static::assertSame(2, $this->service->getCurrentTotalResult());
		static::assertSame($results, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadDatatableForSubAdminLoadsNothing(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->once())->method('isSubAdmin')->with(5)->willReturn(true);
		$this->datatableQueryMock->expects($this->never())->method($this->anything());
		$this->usageServiceMock->expects($this->never())->method($this->anything());

		$this->service->loadDatatable();

		static::assertSame(0, $this->service->getCurrentTotalResult());
		static::assertSame([], $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadDatatableForUser(): void
	{
		$results = [['template_id' => 1]];
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->once())->method('isSubAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->once())->method('isEditor')->willReturn(false);
		$this->aclValidatorMock->expects($this->once())->method('isViewer')->willReturn(false);
		$this->datatableQueryMock->expects($this->once())->method('countForUser')
			->with($this->filterDefinitionStub, ['name' => ['value' => 'x']], 5)
			->willReturn(1);
		$this->datatableQueryMock->expects($this->once())->method('findForUser')
			->with($this->filterDefinitionStub, ['name' => ['value' => 'x']], 5)
			->willReturn($results);
		$this->usageServiceMock->expects($this->never())->method($this->anything());

		$this->service->loadDatatable();

		static::assertSame(1, $this->service->getCurrentTotalResult());
		static::assertSame($results, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetTemplatesInUse(): void
	{
		$this->usageServiceMock->expects($this->once())->method('determineTemplatesInUse')
			->with([1, 2])
			->willReturn([2 => 4]);
		$this->datatableQueryMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());

		static::assertSame([2 => 4], $this->service->getTemplatesInUse([1, 2]));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetTemplatesInUseWithoutIds(): void
	{
		$this->usageServiceMock->expects($this->never())->method($this->anything());
		$this->datatableQueryMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());

		static::assertSame([], $this->service->getTemplatesInUse([]));
	}
}
