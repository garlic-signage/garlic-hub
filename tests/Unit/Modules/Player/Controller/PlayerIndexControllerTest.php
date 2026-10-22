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

use App\Framework\Validation\Sanitizer;
use App\Modules\Player\Controller\PlayerIndexController;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use App\Modules\Player\Services\PlayerIndexService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PlayerIndexControllerTest extends TestCase
{
	private PlayerIndexService&MockObject $playerIndexServiceMock;
	private IndexResponseHandler&MockObject $indexResponseHandler;
	private Sanitizer&MockObject $sanitizerMock;
	private ResponseInterface&MockObject $responseMock;
	private ServerRequestInterface&Stub $requestStub;
	private PlayerIndexController $playerIndexController;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerIndexServiceMock = $this->createMock(PlayerIndexService::class);
		$this->indexResponseHandler   = $this->createMock(IndexResponseHandler::class);
		$this->sanitizerMock          = $this->createMock(Sanitizer::class);
		$this->requestStub            = $this->createStub(ServerRequestInterface::class);
		$this->responseMock           = $this->createMock(ResponseInterface::class);

		$this->playerIndexController = new PlayerIndexController($this->playerIndexServiceMock, $this->indexResponseHandler, $this->sanitizerMock);
	}

	#[Group('units')]
	public function testIndexHandlesMissingFilePath(): void
	{
		$server = [
			'HTTP_USER_AGENT' => 'TestAgent',
			'HTTP_X_SIGNAGE_AGENT' => 'extra useragent',
			'SERVER_NAME' => 'extern'
		];
		$this->requestStub->method('getQueryParams')->willReturn([]);
		$this->requestStub->method('getServerParams')->willReturn($server);

		$this->sanitizerMock->method('int')->with(0)->willReturn(0);
		$this->playerIndexServiceMock->method('handleIndexRequest')
			->with(0, 'extra useragent', $server, false)
			->willReturn('');
		$this->indexResponseHandler->expects($this->never())->method('init');

		$this->responseMock->method('withHeader')
			->with('Content-Type', 'application/smil+xml')
			->willReturnSelf();
		$this->responseMock->method('withStatus')
			->with(404)
			->willReturnSelf();

		$this->playerIndexController->index($this->requestStub, $this->responseMock);
	}

	#[Group('units')]
	public function testIndexHandlesLocalhostPlayer(): void
	{
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '192.168.1.10', 'port' => 80];
		$this->requestStub->method('getQueryParams')->willReturn([]);
		$this->requestStub->method('getServerParams')->willReturn($serverData);
		$this->sanitizerMock->method('int')->with(0)->willReturn(0);
		$this->playerIndexServiceMock->method('handleIndexRequest')
			->with(0, 'TestAgent', $serverData, true)
			->willReturn('');

		$this->indexResponseHandler->expects($this->never())->method('init');

		$this->responseMock->method('withHeader')
			->with('Content-Type', 'application/smil+xml')
			->willReturnSelf();
		$this->responseMock->method('withStatus')
			->with(404)
			->willReturnSelf();

		$this->playerIndexController->index($this->requestStub, $this->responseMock);
	}

	#[Group('units')]
	public function testIndexHandlesDdevPlayer(): void
	{
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => 80];
		$this->requestStub->method('getQueryParams')->willReturn([]);
		$this->requestStub->method('getServerParams')->willReturn($serverData);

		$this->sanitizerMock->method('int')->with(0)->willReturn(0);
		$this->playerIndexServiceMock->method('handleIndexRequest')
			->with(0, 'TestAgent', $serverData, true)
			->willReturn('');
		$this->indexResponseHandler->expects($this->never())->method('init');

		$this->responseMock->method('withHeader')
			->with('Content-Type', 'application/smil+xml')
			->willReturnSelf();
		$this->responseMock->method('withStatus')
			->with(404)
			->willReturnSelf();

		$this->playerIndexController->index($this->requestStub, $this->responseMock);
	}

	#[Group('units')]
	public function testSendHead(): void
	{
		$this->responseMock->expects($this->never())->method($this->anything());
		$filePath = '/tmp/test.smil';
		$server = [
			'HTTP_USER_AGENT' => 'TestAgent',
			'SERVER_NAME' => 'localhost',
			'REQUEST_METHOD' => 'HEAD'
		];

		$this->requestStub->method('getQueryParams')->willReturn([]);
		$this->requestStub->method('getServerParams')->willReturn($server);

		$this->sanitizerMock->method('int')->with(0)->willReturn(0);
		$this->playerIndexServiceMock->method('handleIndexRequest')
			->with(0, 'TestAgent', $server, true)->willReturn($filePath);

		$this->indexResponseHandler->expects($this->once())->method('init')
			->with($server, $filePath);
		$this->indexResponseHandler->expects($this->once())->method('doHEAD')
			->with($this->responseMock)
			->willReturn($this->responseMock);
		$this->indexResponseHandler->expects($this->never())->method('doGET');

		$this->playerIndexController->index($this->requestStub, $this->responseMock);
	}

	#[Group('units')]
	public function testSendGet(): void
	{
		$this->responseMock->expects($this->never())->method($this->anything());
		$filePath = '/tmp/test.smil';
		$server = [
			'HTTP_USER_AGENT' => 'TestAgent',
			'REMOTE_ADDR' => '192.168.1.10',
			'SERVER_NAME' => 'localhost',
			'REQUEST_METHOD' => 'GET'
		];

		$this->requestStub->method('getQueryParams')->willReturn([]);
		$this->requestStub->method('getServerParams')->willReturn($server);

		$this->sanitizerMock->method('int')->with(0)->willReturn(0);
		$this->playerIndexServiceMock->method('handleIndexRequest')
			->with(0, 'TestAgent', $server, true)->willReturn($filePath);

		$this->indexResponseHandler->expects($this->once())->method('init')
			->with($server, $filePath);
		$this->indexResponseHandler->expects($this->never())->method('doHEAD');
		$this->indexResponseHandler->expects($this->once())->method('doGET')
			->with($this->responseMock)
			->willReturn($this->responseMock);

		$this->playerIndexController->index($this->requestStub, $this->responseMock);
	}

}
