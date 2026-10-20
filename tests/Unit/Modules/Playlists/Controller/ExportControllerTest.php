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

namespace Tests\Unit\Modules\Playlists\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Controller\ExportController;
use App\Modules\Playlists\Helper\Export\Orchestrator;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExportControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private ExportController $exportController;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->requestStub          = $this->createStub(ServerRequestInterface::class);
		$this->responseStub         = $this->createStub(ResponseInterface::class);
		$this->expectedResponseStub = $this->createStub(ResponseInterface::class);

		$this->requestStub->method('getParsedBody')->willReturn(['playlist_id' => 12]);

		$this->exportController = new ExportController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testExportReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['playlist_id' => 12])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('export');

		$result = $this->exportController->export($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testExportSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['playlist_id' => 12])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('export')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->exportController->export($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
