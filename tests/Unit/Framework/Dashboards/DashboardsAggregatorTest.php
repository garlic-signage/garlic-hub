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

namespace Tests\Unit\Framework\Dashboards;

use App\Framework\Dashboards\DashboardInterface;
use App\Framework\Dashboards\DashboardsAggregator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class DashboardsAggregatorTest extends TestCase
{
	private DashboardsAggregator $aggregator;

	protected function setUp(): void
	{
		parent::setUp();

		$this->aggregator = new DashboardsAggregator();
	}

	#[Group('units')]
	public function testRegisterAndRenderDashboardsContents(): void
	{
		$this->aggregator->registerDashboard($this->dashboard('test', 'Test Title', 'Test Content'));

		$result = $this->aggregator->renderDashboardsContents();

		static::assertSame([['LANG_DASHBOARD_TITLE' => 'Test Title', 'DASHBOARD_CONTENT' => 'Test Content']], $result);
	}

	#[Group('units')]
	public function testRenderWithoutDashboardsReturnsEmptyList(): void
	{
		static::assertSame([], $this->aggregator->renderDashboardsContents());
	}

	#[Group('units')]
	public function testDashboardsAreRenderedInOrderOfRegistration(): void
	{
		$this->aggregator->registerDashboard($this->dashboard('first', 'First', 'Content 1'));
		$this->aggregator->registerDashboard($this->dashboard('second', 'Second', 'Content 2'));

		$result = $this->aggregator->renderDashboardsContents();

		static::assertSame(['First', 'Second'], array_column($result, 'LANG_DASHBOARD_TITLE'));
		static::assertSame(['Content 1', 'Content 2'], array_column($result, 'DASHBOARD_CONTENT'));
	}

	#[Group('units')]
	public function testRegisteringTheSameIdTwiceReplacesTheDashboard(): void
	{
		$this->aggregator->registerDashboard($this->dashboard('player', 'Old', 'Old Content'));
		$this->aggregator->registerDashboard($this->dashboard('player', 'New', 'New Content'));

		$result = $this->aggregator->renderDashboardsContents();

		static::assertSame([['LANG_DASHBOARD_TITLE' => 'New', 'DASHBOARD_CONTENT' => 'New Content']], $result);
	}

	private function dashboard(string $id, string $title, string $content): DashboardInterface
	{
		$dashboardStub = $this->createStub(DashboardInterface::class);
		$dashboardStub->method('getId')->willReturn($id);
		$dashboardStub->method('getTitle')->willReturn($title);
		$dashboardStub->method('renderContent')->willReturn($content);

		return $dashboardStub;
	}
}
