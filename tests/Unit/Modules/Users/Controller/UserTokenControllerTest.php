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

namespace Tests\Unit\Modules\Users\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Users\Controller\UserTokenController;
use App\Modules\Users\Helper\Tokens\Orchestrator;
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

class UserTokenControllerTest extends TestCase
{
	/** @var array<string,mixed> */
	private array $input = ['UID' => 1, 'purpose' => 'password_reset'];
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private UserTokenController $controller;

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

		$this->controller = new UserTokenController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testDeleteReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
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
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
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
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testRefreshReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('refresh');

		$result = $this->controller->refresh($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testRefreshSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->input)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('refresh')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->refresh($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
