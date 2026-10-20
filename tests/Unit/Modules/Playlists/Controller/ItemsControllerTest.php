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
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Controller\ItemsController;
use App\Modules\Playlists\Helper\Items\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ItemsControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private ItemsController $itemsController;

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

		$this->itemsController = new ItemsController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadItemsReturnsValidationError(): void
	{
		$args = ['id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateLoadItems')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('loadItems');

		$result = $this->itemsController->loadItems($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadItemsSucceeds(): void
	{
		$args = ['id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateLoadItems')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('loadItems')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->loadItems($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertReturnsValidationError(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateInsert')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('insert');

		$result = $this->itemsController->insert($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testInsertSucceeds(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateInsert')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('insert')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->insert($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditReturnsValidationError(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateEdit')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('edit');

		$result = $this->itemsController->edit($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditSucceeds(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateEdit')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('edit')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->edit($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchReturnsValidationError(): void
	{
		$args = ['id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateFetch')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('fetch');

		$result = $this->itemsController->fetch($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchSucceeds(): void
	{
		$args = ['id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateFetch')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('fetch')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->fetch($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateItemOrdersReturnsValidationError(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUpdateItemOrders')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('updateItemOrders');

		$result = $this->itemsController->updateItemOrders($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUpdateItemOrdersSucceeds(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUpdateItemOrders')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('updateItemOrders')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->updateItemOrders($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteReturnsValidationError(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateDelete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('delete');

		$result = $this->itemsController->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$input = ['id' => 12];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateDelete')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('delete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->itemsController->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

}
