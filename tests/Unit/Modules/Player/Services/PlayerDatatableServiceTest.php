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

use App\Framework\Exceptions\CoreException;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Repositories\PlayerRepository;
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
	private PlayerRepository&MockObject $repositoryMock;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private PlayerDatatableService $service;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loggerStub       = $this->createStub(LoggerInterface::class);
		$this->repositoryMock   = $this->createMock(PlayerRepository::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->service = new PlayerDatatableService($this->repositoryMock, $this->parametersMock, $this->aclValidatorMock, $this->loggerStub);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$this->service->setUID(345);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(345)
			->willReturn(true);

		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')
			->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFiltered')
			->with(['empty'])
			->willReturn(12);

		$result = [
			['id' => 1, 'name' => 'John Doe', 'age' => 29],
			['id' => 2, 'name' => 'Jane Smith', 'age' => 34],
			['id' => 3, 'name' => 'Alice Brown', 'age' => 25],
		];

		$this->repositoryMock->expects($this->once())->method('findAllFiltered')
			->with(['empty'])
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
		$this->service->setUID(789);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(789)
			->willReturn(false);

		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFilteredByUID')
			->with(['empty'], 789)
			->willReturn(12);

		$result = [
			['id' => 1, 'name' => 'John Doe', 'age' => 29],
			['id' => 2, 'name' => 'Jane Smith', 'age' => 34],
			['id' => 3, 'name' => 'Alice Brown', 'age' => 25],
		];

		$this->repositoryMock->expects($this->once())->method('findAllFilteredByUID')
			->with(['empty'], 789)
			->willReturn($result);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

}
