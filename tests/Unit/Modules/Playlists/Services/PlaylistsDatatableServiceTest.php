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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Exceptions\CoreException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistUsageService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaylistsDatatableServiceTest extends TestCase
{
	protected LoggerInterface&Stub $loggerStub;
	private PlaylistsRepository&MockObject $repositoryMock;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private PlaylistUsageService&MockObject $playlistUsageServiceMock;
	private UserSession&Stub $userSessionStub;
	private PlaylistsDatatableService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->loggerStub       = $this->createStub(LoggerInterface::class);
		$this->repositoryMock   = $this->createMock(PlaylistsRepository::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->playlistUsageServiceMock = $this->createMock(PlaylistUsageService::class);

		$this->userSessionStub = $this->createStub(UserSession::class);
		$this->service = new PlaylistsDatatableService($this->repositoryMock, $this->parametersMock, $this->aclValidatorMock, $this->playlistUsageServiceMock, $this->userSessionStub, $this->loggerStub);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchForModuleAdmin(): void
	{
		$this->playlistUsageServiceMock->expects($this->never())->method($this->anything());
		$this->userSessionStub->method('getUID')->willReturn(345);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(345)
			->willReturn(true);

		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')
			->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFiltered')
			->with(['empty'])
			->willReturn(12);

		$result =	[
			0 => ['result1' => 'result1'],
			1 => ['result2' => 1]
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
		$this->playlistUsageServiceMock->expects($this->never())->method($this->anything());
		$this->userSessionStub->method('getUID')->willReturn(789);
		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with(789)
			->willReturn(false);

		$this->parametersMock->expects($this->exactly(2))
			->method('getInputParametersArray')->willReturn(['empty']);

		$this->repositoryMock->expects($this->once())->method('countAllFilteredByUID')
			->with(['empty'], 789)
			->willReturn(12);

		$result =	[
			0 => ['result1' => 'result1'],
			1 => ['result2' => 1]
		];
		$this->repositoryMock->expects($this->once())->method('findAllFilteredByUID')
			->with(['empty'], 789)
			->willReturn($result);

		$this->service->loadDatatable();

		static::assertSame(12, $this->service->getCurrentTotalResult());
		static::assertSame($result, $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetPlaylistsInUseWithEmptyIds(): void
	{
		$this->expectUntouched($this->repositoryMock, $this->parametersMock, $this->aclValidatorMock, $this->playlistUsageServiceMock);
		$result = $this->service->getPlaylistsInUse([]);
		static::assertSame([], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetPlaylistsInUseWithValidIds(): void
	{
		$this->expectUntouched($this->repositoryMock, $this->parametersMock, $this->aclValidatorMock);
		$playlistIds = [1, 2, 3];
		$expectedResult = [
			1 => true,
			2 => true,
			3 => true,
		];
		$this->playlistUsageServiceMock->expects($this->once())->method('determinePlaylistsInUse')
			->with($playlistIds)
			->willReturn($expectedResult);
		$result = $this->service->getPlaylistsInUse($playlistIds);

		static::assertSame($expectedResult, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
