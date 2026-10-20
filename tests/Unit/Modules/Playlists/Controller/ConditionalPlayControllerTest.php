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
use App\Modules\Playlists\Controller\ConditionalPlayController;
use App\Modules\Playlists\Helper\ConditionalPlay\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConditionalPlayControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private ConditionalPlayController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->orchestratorMock = $this->createMock(Orchestrator::class);
		$this->controller = new ConditionalPlayController($this->orchestratorMock);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateReturnsNonNullResponse(): void
	{
		$args = ['key' => 'value'];
		$expectedResponse = $this->createMock(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseMock)
			->willReturn($expectedResponse);

		$actualResponse = $this->controller->fetch($this->requestMock, $this->responseMock, $args);

		static::assertSame($expectedResponse, $actualResponse);
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateReturnsNullFetchMethodCalled(): void
	{
		$args = ['key' => 'value'];
		$expectedResponse = $this->createMock(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseMock)
			->willReturn(null);

		$this->orchestratorMock->expects($this->once())->method('fetch')
			->with($this->responseMock)
			->willReturn($expectedResponse);

		$actualResponse = $this->controller->fetch($this->requestMock, $this->responseMock, $args);

		static::assertSame($expectedResponse, $actualResponse);
	}

	/**
	 * @throws ModuleException
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveReturnsNonNullResponse(): void
	{
		$expectedResponse = $this->createMock(ResponseInterface::class);

		$this->requestMock->expects($this->once())->method('getParsedBody')->willReturn(['key' => 'value']);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['key' => 'value'])
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateSave')
			->with($this->responseMock)
			->willReturn($expectedResponse);

		$actualResponse = $this->controller->save($this->requestMock, $this->responseMock);

		static::assertSame($expectedResponse, $actualResponse);
	}

	/**
	 * @throws ModuleException
	 * @throws UserException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testSaveCallsSaveAndReturnsResponse(): void
	{
		$expectedResponse = $this->createMock(ResponseInterface::class);

		$this->requestMock->expects($this->once())->method('getParsedBody')->willReturn(['key' => 'value']);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['key' => 'value'])
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateSave')
			->with($this->responseMock)
			->willReturn(null);

		$this->orchestratorMock->expects($this->once())->method('save')
			->with($this->responseMock)
			->willReturn($expectedResponse);

		$actualResponse = $this->controller->save($this->requestMock, $this->responseMock);

		static::assertSame($expectedResponse, $actualResponse);
	}
}
