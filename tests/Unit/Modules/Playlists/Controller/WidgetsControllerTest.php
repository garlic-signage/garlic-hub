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
use App\Modules\Playlists\Controller\WidgetsController;
use App\Modules\Playlists\Helper\Widgets\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WidgetsControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private WidgetsController $widgetsController;

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

		$this->widgetsController = new WidgetsController($this->orchestratorMock);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchReturnsValidationError(): void
	{
		$args = ['item_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($args)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('fetch');

		$result = $this->widgetsController->fetch($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFetchSucceeds(): void
	{
		$args = ['item_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($args)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('fetch')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->widgetsController->fetch($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveReturnsValidationError(): void
	{
		$input = ['item_id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateSave')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('save');

		$result = $this->widgetsController->save($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveSucceeds(): void
	{
		$input = ['item_id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateSave')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('save')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->widgetsController->save($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
