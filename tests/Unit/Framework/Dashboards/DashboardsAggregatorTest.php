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
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class DashboardsAggregatorTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRegisterAndRenderDashboardsContents(): void
	{
		$dashboardMock = $this->createMock(DashboardInterface::class);
		$dashboardMock->method('getId')->willReturn('test');
		$dashboardMock->method('getTitle')->willReturn('Test Title');
		$dashboardMock->method('renderContent')->willReturn('Test Content');

		$aggregator = new DashboardsAggregator();
		$aggregator->registerDashboard($dashboardMock);

		$result = $aggregator->renderDashboardsContents();

		static::assertCount(1, $result);
		static::assertSame('Test Title', $result[0]['LANG_DASHBOARD_TITLE']);
		static::assertSame('Test Content', $result[0]['DASHBOARD_CONTENT']);
	}

}
