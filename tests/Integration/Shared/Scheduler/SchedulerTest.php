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

namespace Tests\Integration\Shared\Scheduler;

use App\Shared\Scheduler\ScheduledTask;
use App\Shared\Scheduler\Scheduler;
use App\Shared\Scheduler\SchedulerRunRepository;
use App\Shared\Scheduler\TaskInterval;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The scheduler with its table: a task runs when it never ran or when its interval is over.
 */
#[Group('db')]
class SchedulerTest extends RepositoryTestCase
{
	private SchedulerRunRepository $runs;
	private string $lockFile;
	/** @var list<string> */
	private array $executed = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->runs     = new SchedulerRunRepository($this->connection);
		$this->lockFile = sys_get_temp_dir() . '/scheduler_test_' . getmypid() . '.lock';
	}

	protected function tearDown(): void
	{
		@unlink($this->lockFile);
		parent::tearDown();
	}

	private function task(string $name, TaskInterval $interval, bool $fails = false): ScheduledTask
	{
		$onRun = function () use ($name, $fails): void
		{
			$this->executed[] = $name;
			if ($fails)
				throw new RuntimeException('broken');
		};

		return new readonly class($name, $interval, $onRun) implements ScheduledTask
		{
			public function __construct(private string $name, private TaskInterval $interval, private Closure $onRun) {}

			public function name(): string
			{
				return $this->name;
			}

			public function interval(): TaskInterval
			{
				return $this->interval;
			}

			public function run(): void
			{
				($this->onRun)();
			}
		};
	}

	/**
	 * @param list<ScheduledTask> $tasks
	 */
	private function scheduler(array $tasks): Scheduler
	{
		return new Scheduler($this->runs, $tasks, $this->lockFile, new NullLogger());
	}

	public function testATaskWhichNeverRanIsDue(): void
	{
		$ran = $this->scheduler([$this->task('daily', TaskInterval::DAILY)])->runDueTasks(new DateTimeImmutable('2026-11-04 03:00:00'));

		static::assertSame(['daily'], $ran);
		static::assertSame(['daily' => '2026-11-04 03:00:00'], $this->runs->findLastRuns());
	}

	public function testATaskRunsAgainWhenItsIntervalIsOver(): void
	{
		$scheduler = $this->scheduler([$this->task('daily', TaskInterval::DAILY), $this->task('hourly', TaskInterval::HOURLY)]);
		$scheduler->runDueTasks(new DateTimeImmutable('2026-11-04 03:00:00'));

		static::assertSame([], $scheduler->runDueTasks(new DateTimeImmutable('2026-11-04 03:55:00')));
		static::assertSame(['hourly'], $scheduler->runDueTasks(new DateTimeImmutable('2026-11-04 04:00:00')));
		// the cron starts a few seconds earlier: the daily task does not wait 5 minutes more
		static::assertSame(['daily', 'hourly'], $scheduler->runDueTasks(new DateTimeImmutable('2026-11-05 02:59:58')));
	}

	public function testAFailingTaskDoesNotStopTheOthers(): void
	{
		$ran = $this->scheduler([$this->task('broken', TaskInterval::DAILY, true), $this->task('daily', TaskInterval::DAILY)])->runDueTasks(new DateTimeImmutable('2026-11-04 03:00:00'));

		static::assertSame(['broken', 'daily'], $ran);
		static::assertSame(['broken', 'daily'], $this->executed);
		$row = $this->connection->fetchAssociative('SELECT status, error FROM scheduler_runs WHERE task = ?', ['broken']);
		static::assertSame(['status' => Scheduler::STATUS_FAILED, 'error' => 'broken'], $row);
	}

	public function testASecondRunWaitsForTheFirstOne(): void
	{
		$lock = fopen($this->lockFile, 'c');
		static::assertNotFalse($lock);
		flock($lock, LOCK_EX);

		$ran = $this->scheduler([$this->task('daily', TaskInterval::DAILY)])->runDueTasks(new DateTimeImmutable('2026-11-04 03:00:00'));

		flock($lock, LOCK_UN);
		fclose($lock);
		static::assertNull($ran);
		static::assertSame([], $this->executed);
	}

	public function testALockFileWhichIsNotWritableDoesNotStopTheRun(): void
	{
		// e.g. left by a run of root by hand
		touch($this->lockFile);
		chmod($this->lockFile, 0444);

		$ran = $this->scheduler([$this->task('daily', TaskInterval::DAILY)])->runDueTasks(new DateTimeImmutable('2026-11-04 03:00:00'));

		static::assertSame(['daily'], $ran);
	}
}
