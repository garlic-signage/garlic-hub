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

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Config\Config;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Mediapool\Controller\ShowController;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class ShowControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private NodesService&Stub $nodesServiceStub;
	private Session&MockObject $sessionMock;
	private Translator&MockObject $translatorMock;
	private Config&Stub $configStub;
	private ShowController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);
		$this->nodesServiceStub = $this->createStub(NodesService::class);
		$this->controller       = new ShowController($this->nodesServiceStub);

		$this->sessionMock      = $this->createMock(Session::class);
		$this->translatorMock   = $this->createMock(Translator::class);
		$this->configStub       = $this->createStub(Config::class);
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
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
					'config' => $this->configStub,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceStub->method('isModuleAdmin')->willReturn(false);
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->translatorMock->expects($this->exactly(55))->method('translate');

		$this->responseMock->expects($this->never())->method($this->anything());

		$result = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('mediapool/overview', $result->getTemplate());
		static::assertArrayNotHasKey('folder_dialog_visibility', $result->getTemplateData());
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
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
					'config' => $this->configStub,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceStub->method('isModuleAdmin')->willReturn(true);
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->translatorMock->expects($this->exactly(57))->method('translate');

		$this->responseMock->expects($this->never())->method($this->anything());

		$result = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('mediapool/overview', $result->getTemplate());
	}

	/**
	 * @return void
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
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
					'config' => $this->configStub,
					default => null,
				};
			});
		$this->sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);

		$this->nodesServiceStub->method('isModuleAdmin')->willReturn(false);
		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$this->translatorMock->expects($this->exactly(56))->method('translate');

		$this->responseMock->expects($this->never())->method($this->anything());

		$result = $this->controller->show($this->requestMock, $this->responseMock);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('mediapool/overview', $result->getTemplate());
		static::assertArrayHasKey('folder_dialog_visibility', $result->getTemplateData());
	}



}
