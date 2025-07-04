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


namespace App\Framework\Dashboards;

class DashboardsAggregator
{
	/**
	 * @var array<string,DashboardInterface>|array<empty,empty>
	 */
	private array $dashboards = [];

	public function __construct()
	{
	}

	public function registerDashboard(DashboardInterface $dashboard): void
	{
		$this->dashboards[$dashboard->getId()] = $dashboard;
	}

	/**
	 * @return list<array<string,string>>
	 */
	public function renderDashboardsContents(): array
	{
		$resolvedDashboards = [];
		foreach ($this->dashboards as $dashboard)
		{
			/** @var DashboardInterface $dashboard */
			$resolvedDashboards[] = [
				'LANG_DASHBOARD_TITLE' => $dashboard->getTitle(),
				'DASHBOARD_CONTENT'    =>$dashboard->renderContent()
			];
		}

		return $resolvedDashboards;
	}
}