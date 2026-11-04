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

namespace App\Commands;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Shared\Scheduler\Scheduler;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Started by the cron every 5 minutes. Without the switch of the settings page it ends at once:
 * the system is lean by default.
 */
#[AsCommand(
	name: 'schedule:run',
	description: 'Runs the scheduled tasks which are due.'
)]
class ScheduleRunCommand extends Command
{
	public function __construct(private readonly Config $config, private readonly Scheduler $scheduler)
	{
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try
		{
			if (!$this->config->isSwitchOn('enabled', 'system', 'Scheduler'))
				return Command::SUCCESS;

			$ran = $this->scheduler->runDueTasks(new DateTimeImmutable());
			if ($ran === null)
				$output->writeln('<comment>The scheduler still runs.</comment>', OutputInterface::VERBOSITY_VERBOSE);
			else
				$output->writeln('Tasks run: ' . ($ran === [] ? '-' : implode(', ', $ran)), OutputInterface::VERBOSITY_VERBOSE);
		}
		catch (CoreException | Exception $e)
		{
			$output->writeln('<error>The scheduler failed: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}
}
