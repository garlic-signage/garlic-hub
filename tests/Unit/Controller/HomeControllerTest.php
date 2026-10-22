<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Controller;

use App\Controller\HomeController;
use App\Framework\Http\Session;
use App\Framework\Dashboards\DashboardsAggregator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class HomeControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private Session&MockObject $sessionMock;
	private DashboardsAggregator $dashboardsAggregatorStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock  = $this->createMock(ServerRequestInterface::class);
		$this->responseMock = $this->createMock(ResponseInterface::class);
		$this->sessionMock  = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()
			->getMock();
		$this->dashboardsAggregatorStub = $this->createStub(DashboardsAggregator::class);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIndexReturnsHomePage(): void
	{
		$this->requestMock->method('getAttribute')->with('session')->willReturn($this->sessionMock);
		$this->sessionMock->method('exists')->with('user')->willReturn(true);
		$this->sessionMock->method('get')->with('user')->willReturn(['username' => 'testuser']);
		$this->responseMock->method('getBody')->willReturn($this->createStub(StreamInterface::class));
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();

		$controller = new HomeController($this->dashboardsAggregatorStub);
		$result = $controller->index($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLegalsReturnsLegalPage(): void
	{
		// the legal page is static, it neither reads the request nor the session
		$this->requestMock->expects($this->never())->method($this->anything());
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->responseMock->method('getBody')->willReturn($this->createStub(StreamInterface::class));
		$this->responseMock->expects($this->once())->method('withHeader')->with('Content-Type', 'text/html')->willReturnSelf();

		$controller = new HomeController($this->dashboardsAggregatorStub);
		$result = $controller->legals($this->requestMock, $this->responseMock);

		static::assertSame($this->responseMock, $result);
	}

}
