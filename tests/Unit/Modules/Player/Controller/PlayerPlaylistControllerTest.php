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


namespace Tests\Unit\Modules\Player\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Player\Controller\PlayerPlaylistController;
use App\Modules\Player\Helper\PlayerPlaylist\Orchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class PlayerPlaylistControllerTest extends TestCase
{
	private ResponseInterface&Stub $responseStub;
	private ServerRequestInterface&MockObject $requestMock;
	private Orchestrator&MockObject $orchestratorMock;
	private PlayerPlaylistController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock = $this->createMock(Orchestrator::class);
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseStub     = $this->createStub(ResponseInterface::class);

		$this->controller = new PlayerPlaylistController($this->orchestratorMock);
	}

	#[Group('units')]
	public function testReplacePlaylistReturnsResponseWhenValidateForReplacePlaylistReturnsResponse(): void
	{
		$inputData = ['key' => 'value'];
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn($inputData);

		$expectedResponse = $this->createStub(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputData)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateForReplacePlaylist')
			->with($this->responseStub)
			->willReturn($expectedResponse);

		$result = $this->controller->replacePlaylist($this->requestMock, $this->responseStub);

		static::assertSame($expectedResponse, $result);
	}

	#[Group('units')]
	public function testReplacePlaylistCallsReplaceMasterPlaylistWhenValidationReturnsNull(): void
	{
		$inputData = ['key' => 'value'];
		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($inputData);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputData)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateForReplacePlaylist')
			->with($this->responseStub)
			->willReturn(null);

		$expectedResponse = $this->createStub(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('replaceMasterPlaylist')
			->with($this->responseStub)
			->willReturn($expectedResponse);

		$result = $this->controller->replacePlaylist($this->requestMock, $this->responseStub);

		static::assertSame($expectedResponse, $result);
	}

	#[Group('units')]
	public function testPushPlaylistReturnsResponseWhenValidateStandardInputReturnsResponse(): void
	{
		$inputData = ['key' => 'value'];
		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($inputData);

		$expectedResponse = $this->createStub(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputData)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateStandardInput')
			->with($this->responseStub)
			->willReturn($expectedResponse);

		$result = $this->controller->pushPlaylist($this->requestMock, $this->responseStub);

		static::assertSame($expectedResponse, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPushPlaylistReturnsResponseWhenCheckPlayerReturnsResponse(): void
	{
		$inputData = ['key' => 'value'];

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($inputData);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputData)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateStandardInput')
			->with($this->responseStub)
			->willReturn(null);

		$expectedResponse = $this->createStub(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('checkPlayer')
			->with($this->responseStub)
			->willReturn($expectedResponse);

		$result = $this->controller->pushPlaylist($this->requestMock, $this->responseStub);

		static::assertSame($expectedResponse, $result);
	}

	#[Group('units')]
	public function testPushPlaylistCallsPushPlaylistWhenAllValidationsPass(): void
	{
		$inputData = ['key' => 'value'];

		$this->requestMock->expects($this->once())->method('getParsedBody')
			->willReturn($inputData);

		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($inputData)
			->willReturnSelf();

		$this->orchestratorMock->expects($this->once())->method('validateStandardInput')
			->with($this->responseStub)
			->willReturn(null);

		$this->orchestratorMock->expects($this->once())->method('checkPlayer')
			->with($this->responseStub)
			->willReturn(null);

		$expectedResponse = $this->createStub(ResponseInterface::class);

		$this->orchestratorMock->expects($this->once())->method('pushPlaylist')
			->with($this->responseStub)
			->willReturn($expectedResponse);

		$result = $this->controller->pushPlaylist($this->requestMock, $this->responseStub);

		static::assertSame($expectedResponse, $result);
	}

}
