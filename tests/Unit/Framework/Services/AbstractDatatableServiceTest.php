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

use App\Framework\Database\BaseRepositories\FilterBase;
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

	public function fetchForModuleAdmin(FilterBase $repository, BaseParameters $parameters): static
	{
		return parent::fetchForModuleAdmin($repository, $parameters);
	}

	public function fetchForUser(FilterBase $repository, BaseParameters $parameters): static
	{
		return parent::fetchForUser($repository, $parameters);
	}


}
class AbstractDatatableServiceTest extends TestCase
{
	protected LoggerInterface&Stub $loggerStub;
	private FilterBase&MockObject $repositoryMock;
	private BaseParameters&MockObject $parametersMock;
	private ConcreteDatatableService $service;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loggerStub = $this->createStub(LoggerInterface::class);
		$this->repositoryMock = $this->createMock(FilterBase::class);
		$this->parametersMock = $this->createMock(BaseParameters::class);
		$this->service = new ConcreteDatatableService($this->loggerStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFiltered')
			->with(['empty'])
			->willReturn(12);

		$result = [['key' => 'value']];
			$this->repositoryMock->expects($this->once())->method('findAllFiltered')
				->with(['empty'])
				->willReturn($result);

		$this->service->fetchForModuleAdmin($this->repositoryMock, $this->parametersMock);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchForUser(): void
	{
		$this->service->setUID(123);

		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFilteredByUID')
			->with(['empty'], 123)
			->willReturn(12);

		$result = [['key' => 'value']];
		$this->repositoryMock->expects($this->once())->method('findAllFilteredByUID')
			->with(['empty'], 123)
			->willReturn($result);

		$this->service->fetchForUser($this->repositoryMock, $this->parametersMock);

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

}
