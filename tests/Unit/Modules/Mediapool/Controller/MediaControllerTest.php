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
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Controller\MediaController;
use App\Modules\Mediapool\Helper\Media\Orchestrator;
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

class MediaControllerTest extends TestCase
{
	/** @var array<string,mixed> */
	private array $body = ['media_id' => 'abc', 'csrf_token' => 'token'];
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private MediaController $controller;

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

		$this->requestStub->method('getParsedBody')->willReturn($this->body);
		$this->requestStub->method('getQueryParams')->willReturn(['filter' => 'images']);

		$this->controller = new MediaController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testListReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['node_id' => 5, 'filter' => 'images'])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateList')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('list');

		$result = $this->controller->list($this->requestStub, $this->responseStub, ['node_id' => 5]);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testListSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['node_id' => 5, 'filter' => 'images'])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateList')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('list')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->list($this->requestStub, $this->responseStub, ['node_id' => 5]);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetInfoReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['media_id' => 'abc'])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateGetInfo')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('getInfo');

		$result = $this->controller->getInfo($this->requestStub, $this->responseStub, ['media_id' => 'abc']);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetInfoSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['media_id' => 'abc'])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateGetInfo')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('getInfo')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->getInfo($this->requestStub, $this->responseStub, ['media_id' => 'abc']);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
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
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testEditSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
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
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithMediaId')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('delete');

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithMediaId')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('delete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testMoveReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
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
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testMoveSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
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
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testCloneReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithMediaId')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('clone');

		$result = $this->controller->clone($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws DatabaseException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testCloneSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($this->body)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithMediaId')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('clone')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->clone($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
