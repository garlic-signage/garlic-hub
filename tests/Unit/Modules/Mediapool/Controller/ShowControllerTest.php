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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Modules\Mediapool\Controller\ShowController;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\SimpleCache\InvalidArgumentException;


class ShowControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private NodesService&MockObject $nodesServiceMock;
	private Session&MockObject $sessionMock;
	private Translator&MockObject $translatorMock;
	private Config&MockObject $configMock;
	private ShowController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);
		$this->nodesServiceMock = $this->createMock(NodesService::class);
		$this->controller       = new ShowController($this->nodesServiceMock);

		$this->sessionMock      = $this->createMock(Session::class);
		$this->translatorMock   = $this->createMock(Translator::class);
		$this->configMock       = $this->createMock(Config::class);
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testShowOverview(): void
	{
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnCallback(function ($attribute)
			{
				return match ($attribute)
				{
					'translator' => $this->translatorMock,
					'session' => $this->sessionMock,
					'config' => $this->configMock,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceMock->method('isModuleAdmin')->willReturn(false);
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->translatorMock->expects($this->exactly(55))->method('translate');

		$this->mockResponse();

		$this->controller->show($this->requestMock, $this->responseMock);
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testShowOverviewWithAdmin(): void
	{
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnCallback(function ($attribute)
			{
				return match ($attribute)
				{
					'translator' => $this->translatorMock,
					'session' => $this->sessionMock,
					'config' => $this->configMock,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceMock->method('isModuleAdmin')->willReturn(true);
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->translatorMock->expects($this->exactly(57))->method('translate');

		$this->mockResponse();

		$this->controller->show($this->requestMock, $this->responseMock);
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testShowOverviewWithEnterprise(): void
	{
		$this->requestMock->expects($this->exactly(3))->method('getAttribute')
			->willReturnCallback(function ($attribute)
			{
				return match ($attribute)
				{
					'translator' => $this->translatorMock,
					'session' => $this->sessionMock,
					'config' => $this->configMock,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceMock->method('isModuleAdmin')->willReturn(false);
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$this->translatorMock->expects($this->exactly(56))->method('translate');

		$this->mockResponse();

		$this->controller->show($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws Exception
	 */
	private function mockResponse(): void
	{
		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$this->responseMock->expects($this->once())
			->method('getBody')
			->willReturn($streamInterfaceMock);

		$streamInterfaceMock->expects($this->once())
			->method('write');

		$this->responseMock->expects($this->once())
			->method('withHeader')
			->with('Content-Type', 'text/html')
			->willReturnSelf();
	}

}
