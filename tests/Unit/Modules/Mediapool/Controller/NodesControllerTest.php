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

namespace Tests\Unit\Modules\Mediapool\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Controller\NodesController;
use App\Modules\Mediapool\Helper\Nodes\Orchestrator;
use Doctrine\DBAL\Exception as DBALException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

class NodesControllerTest extends TestCase
{
	/** @var array<string,mixed> */
	private array $input = ['node_id' => 1, 'name' => 'folder', 'csrf_token' => 'token'];
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private NodesController $controller;

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

		$this->requestStub->method('getParsedBody')->willReturn($this->input);

		$this->controller = new NodesController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testList(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['parent_id' => 5])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('list')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->list($this->requestStub, $this->responseStub, ['parent_id' => 5]);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddReturnsValidationError(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateAdd')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('add');

		$result = $this->controller->add($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddSucceeds(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateAdd')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('add')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->add($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditReturnsValidationError(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateEdit')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('edit');

		$result = $this->controller->edit($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditSucceeds(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateEdit')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('edit')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->edit($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testMoveReturnsValidationError(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateMove')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('move');

		$result = $this->controller->move($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testMoveSucceeds(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateMove')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('move')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->move($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteReturnsValidationError(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateDelete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('delete');

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$this->expectInput();
		$this->orchestratorMock->expects($this->once())->method('validateDelete')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('delete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	private function expectInput(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->input)
			->willReturnSelf();
	}
}
