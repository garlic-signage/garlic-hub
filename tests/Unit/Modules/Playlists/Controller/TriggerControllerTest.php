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
use App\Modules\Playlists\Controller\TriggerController;
use App\Modules\Playlists\Helper\Trigger\Orchestrator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

class TriggerControllerTest extends TestCase
{
	private TriggerController $triggerController;
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->orchestratorMock = $this->createMock(Orchestrator::class);
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);

		$this->triggerController = new TriggerController($this->orchestratorMock);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchTrigger(): void
	{
		$args = ['key' => 'value'];

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturn($this->orchestratorMock);

		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseMock)
			->willReturn(null);

		$this->orchestratorMock->expects($this->once())->method('fetch')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->triggerController->fetchTrigger($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws UserException
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testFetchTriggerFailedValidation(): void
	{
		$args = ['key' => 'value'];
		$validationResponseMock = $this->createMock(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturn($this->orchestratorMock);

		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseMock)
			->willReturn($validationResponseMock);

		$this->orchestratorMock->expects($this->never())->method('fetch');

		$this->triggerController->fetchTrigger($this->requestMock, $this->responseMock, $args);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveSuccess(): void
	{
		$inputValues = ['key' => 'value'];

		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn($inputValues);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputValues)
			->willReturn($this->orchestratorMock);

		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseMock)
			->willReturn(null);

		$this->orchestratorMock->expects($this->once())->method('save')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->triggerController->save($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveFailedValidation(): void
	{
		$inputValues = ['key' => 'value'];
		$validationResponseMock = $this->createMock(ResponseInterface::class);

		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn($inputValues);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputValues)
			->willReturn($this->orchestratorMock);

		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseMock)
			->willReturn($validationResponseMock);

		$this->orchestratorMock->expects($this->never())->method('save');

		$response = $this->triggerController->save($this->requestMock, $this->responseMock);

		static::assertSame($validationResponseMock, $response);
	}
}
