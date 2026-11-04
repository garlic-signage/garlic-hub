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

use App\Framework\Exceptions\CoreException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs the tasks which are due. The cron starts it every 5 minutes, a lock keeps a second start
 * away while a long task still runs. A task which fails is logged, the others run anyway.
 */
class Scheduler
{
	public const string STATUS_OK     = 'ok';
	public const string STATUS_FAILED = 'failed';

	// the cron starts a few seconds earlier or later: a daily task must not slip by 5 minutes every day
	private const int GRACE_SECONDS = 60;

	/**
	 * @param list<ScheduledTask> $tasks
	 */
	public function __construct(
		private readonly SchedulerRunRepository $runs,
		private readonly array $tasks,
		private readonly string $lockFile,
		private readonly LoggerInterface $logger
	) {}

	/**
	 * @return list<string>|null the names of the tasks which ran, null when another run still holds the lock
	 * @throws CoreException
	 * @throws Exception
	 */
	public function runDueTasks(DateTimeImmutable $now): ?array
	{
		// read only is enough for a lock: a file of root from a run by hand does not stop the cron
		$lock = @fopen($this->lockFile, 'c') ?: @fopen($this->lockFile, 'r');
		if ($lock === false)
			throw new CoreException('The lock file ' . $this->lockFile . ' of the scheduler can not be opened.');

		try
		{
			if (!flock($lock, LOCK_EX | LOCK_NB))
				return null;

			return $this->runTasks($now);
		}
		finally
		{
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * @return list<string>
	 * @throws Exception
	 */
	private function runTasks(DateTimeImmutable $now): array
	{
		$lastRuns = $this->runs->findLastRuns();
		$ran      = [];
		foreach ($this->tasks as $task)
		{
			if (!$this->isDue($task, $lastRuns[$task->name()] ?? null, $now))
				continue;

			try
			{
				$task->run();
				$this->runs->store($task->name(), $now, self::STATUS_OK, null);
			}
			catch (Throwable $e)
			{
				$this->logger->error('Scheduled task ' . $task->name() . ' failed: ' . $e->getMessage());
				$this->runs->store($task->name(), $now, self::STATUS_FAILED, $e->getMessage());
			}

			$ran[] = $task->name();
		}

		return $ran;
	}

	private function isDue(ScheduledTask $task, ?string $lastRun, DateTimeImmutable $now): bool
	{
		if ($lastRun === null)
			return true;

		$last = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastRun);
		if ($last === false)
			return true;

		return $now->getTimestamp() - $last->getTimestamp() >= $task->interval()->value - self::GRACE_SECONDS;
	}
}
