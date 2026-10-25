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

namespace App\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Enums\PlayerReports;

use App\Framework\Exceptions\CoreException;

/**
 * Handles the preparation of subscription data for processing.
 * Collects and organizes various subscription configurations and reports
 * specific to the player's entity.
 */
class SubscriptionPreparer extends AbstractPreparer implements PreparerInterface
{
	/**
	 * @return list<array<string, string>>
	 */
	public function prepare(): array
	{
		$subscriptions = [];

		if (!empty($this->playerEntity->getCommands()))
			$subscriptions[] = $this->replaceTaskSchedule();

		if (count($this->playerEntity->getReports()) === 0)
			return $subscriptions;

		// the reports are a list of names: ['inventory', 'played', ...]
		$reports = $this->playerEntity->getReports();
		if (in_array(PlayerReports::INVENTORY->value, $reports, true))
			$subscriptions[] = $this->replaceReportInventory();
		if (in_array(PlayerReports::PLAYED->value, $reports, true))
			$subscriptions[] = $this->replaceReportPlayed();
		if (in_array(PlayerReports::EVENTS->value, $reports, true))
			$subscriptions[] = $this->replaceReportEvents();
		if (in_array(PlayerReports::SYSTEM_CONFIGURATION->value, $reports, true))
			$subscriptions[] = $this->replaceSystemConfiguration();
		if (in_array(PlayerReports::TASKS_EXECUTIONS->value, $reports, true))
			$subscriptions[] = $this->replaceTasksExecutions();

		return $subscriptions;
	}

	/**
	 * @return array<string, mixed>
	 * @throws CoreException
	 */
	private function replaceTaskSchedule(): array
	{
		return [
			'SUBSCRIPTION_TYPE'   => 'TaskSchedule',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getIndexPath().'/task_scheduler.xml',
			'SUBSCRIPTION_METHOD' => 'get',
			'SUBSCRIPTION_RANDOM' => rand(1000, 9999)
		];
	}

	/**
	 * @return array<string, string>
	 * @throws CoreException
	 */
	protected function replaceReportInventory(): array
	{
		return [
			'SUBSCRIPTION_TYPE'   => 'InventoryReport',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getReportServer() . '/inventory-' . $this->playerEntity->getUuid() . '.xml',
			'SUBSCRIPTION_METHOD' => 'put'
		];
	}

	/**
	 * @return array<string, string>
	 * @throws CoreException
	 */
	protected function replaceReportPlayed(): array
	{
		return [
			'SUBSCRIPTION_TYPE' => 'PlaylogCollection',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getReportServer(),
			'SUBSCRIPTION_METHOD' => 'put'
		];
	}

	/**
	 * @return array<string, string>
	 * @throws CoreException
	 */
	protected function replaceReportEvents(): array
	{
		return [
			'SUBSCRIPTION_TYPE'   => 'EventlogCollection',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getReportServer(),
			'SUBSCRIPTION_METHOD' => 'put'
		];
	}

	/**
	 * @return array<string, string>
	 * @throws CoreException
	 */
	protected function replaceSystemConfiguration(): array
	{
		return [
			'SUBSCRIPTION_TYPE'   => 'SystemReport',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getReportServer(). '/system-' . $this->playerEntity->getUuid().'.xml',
			'SUBSCRIPTION_METHOD' => 'put'
		];
	}


	/**
	 * @return array<string, string>
	 * @throws CoreException
	 */
	protected function replaceTasksExecutions(): array
	{
		return [
			'SUBSCRIPTION_TYPE' => 'TaskExecutionReport',
			'SUBSCRIPTION_ACTION' => $this->playerEntity->getReportServer() . '/task_execution-' . $this->playerEntity->getUuid(). '.xml',
			'SUBSCRIPTION_METHOD' => 'put'
		];
	}
}