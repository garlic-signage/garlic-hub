<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Shared\Scheduler;

use App\Framework\Database\BaseRepositories\SqlBase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The last run of every task, one row per task.
 */
class SchedulerRunRepository extends SqlBase
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'scheduler_runs', 'task');
	}

	/**
	 * @return array<string,string> task => last run (Y-m-d H:i:s)
	 * @throws Exception
	 */
	public function findLastRuns(): array
	{
		$lastRuns = [];
		foreach ($this->connection->createQueryBuilder()->select('task', 'last_run_at')->from($this->table)->executeQuery()->fetchAllAssociative() as $row)
		{
			$lastRuns[(string) $row['task']] = (string) $row['last_run_at'];
		}

		return $lastRuns;
	}

	/**
	 * @throws Exception
	 */
	public function store(string $task, DateTimeImmutable $runAt, string $status, ?string $error): void
	{
		$fields = ['last_run_at' => $runAt->format('Y-m-d H:i:s'), 'status' => $status, 'error' => $error];
		if ($this->connection->update($this->table, $fields, ['task' => $task]) === 0)
			$this->connection->insert($this->table, ['task' => $task] + $fields);
	}
}
