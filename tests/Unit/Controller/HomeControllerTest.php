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
use App\Framework\Dashboards\DashboardsAggregator;
use App\Framework\TemplateEngine\PageResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeControllerTest extends TestCase
{
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private DashboardsAggregator&Stub $dashboardsAggregatorStub;
	private HomeController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestStub              = $this->createStub(ServerRequestInterface::class);
		$this->responseStub             = $this->createStub(ResponseInterface::class);
		$this->dashboardsAggregatorStub = $this->createStub(DashboardsAggregator::class);
		$this->controller               = new HomeController($this->dashboardsAggregatorStub);
	}

	#[Group('units')]
	public function testIndexReturnsHomePage(): void
	{
		$this->dashboardsAggregatorStub->method('renderDashboardsContents')->willReturn([['dashboard' => 'player']]);
		$result = $this->controller->index($this->requestStub, $this->responseStub);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('home', $result->getTemplate());
		static::assertSame('Garlic Hub - Dashboard', $result->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertSame([['dashboard' => 'player']], $result->getTemplateData()['dashboard']);
		static::assertSame(['LANG_PAGE_TITLE' => 'Garlic Hub - Dashboard'], $result->getMainLayoutData());
	}

	#[Group('units')]
	public function testLegalsReturnsLegalPage(): void
	{
		$result = $this->controller->legals($this->requestStub, $this->responseStub);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('legals/legals', $result->getTemplate());
		static::assertSame([], $result->getTemplateData());
		static::assertSame(['LANG_PAGE_TITLE' => 'Garlic Hub - Website Legal Notice'], $result->getMainLayoutData());
	}

	#[Group('units')]
	public function testIndexWithoutDashboardsReturnsEmptyDashboardList(): void
	{
		$this->dashboardsAggregatorStub->method('renderDashboardsContents')->willReturn([]);

		$result = $this->controller->index($this->requestStub, $this->responseStub);

		static::assertInstanceOf(PageResponse::class, $result);
		static::assertSame('home', $result->getTemplate());
		static::assertSame([], $result->getTemplateData()['dashboard']);
	}
}
