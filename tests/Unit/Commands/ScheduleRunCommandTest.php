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

namespace Tests\Unit\Commands;

use App\Commands\ScheduleRunCommand;
use App\Framework\Config\Config;
use App\Shared\Scheduler\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The cron starts the command every 5 minutes: it does nothing while the scheduler is off.
 */
class ScheduleRunCommandTest extends TestCase
{
	private function command(string $enabled, Scheduler $scheduler): CommandTester
	{
		$config = $this->createStub(Config::class);
		$config->method('isSwitchOn')->willReturnMap([['enabled', 'system', 'Scheduler', $enabled === '1']]);

		return new CommandTester(new ScheduleRunCommand($config, $scheduler));
	}

	#[Group('units')]
	public function testNothingRunsWhileTheSchedulerIsOff(): void
	{
		$scheduler = $this->createMock(Scheduler::class);
		$scheduler->expects($this->never())->method('runDueTasks');
		$tester = $this->command('0', $scheduler);

		$tester->execute([]);

		static::assertSame(Command::SUCCESS, $tester->getStatusCode());
	}

	#[Group('units')]
	public function testTheDueTasksRunWhileTheSchedulerIsOn(): void
	{
		$scheduler = $this->createMock(Scheduler::class);
		$scheduler->expects($this->once())->method('runDueTasks')->willReturn(['audit.cleanup']);
		$tester = $this->command('1', $scheduler);

		$tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

		static::assertSame(Command::SUCCESS, $tester->getStatusCode());
		static::assertSame("Tasks run: audit.cleanup\n", $tester->getDisplay());
	}

	#[Group('units')]
	public function testWithoutDueTasksNoneIsShown(): void
	{
		$scheduler = $this->createStub(Scheduler::class);
		$scheduler->method('runDueTasks')->willReturn([]);
		$tester = $this->command('1', $scheduler);

		$tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

		static::assertSame("Tasks run: -\n", $tester->getDisplay());
	}
}
