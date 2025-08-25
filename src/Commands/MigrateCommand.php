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

namespace App\Commands;

use App\Framework\Database\Migration\Runner;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Represents a command used to execute database migrations.
 *
 * This command allows the execution of database migrations, with an optional
 * rollback functionality. A specific migration version can also be targeted.
 */
#[AsCommand(
	name: 'db:migrate',
	description: 'Executes a database migration.'
)]
class MigrateCommand extends Command
{
	private Runner $migrationRunner;

	public function __construct(Runner $migrationRunner)
	{
		$this->migrationRunner = $migrationRunner;

		parent::__construct();
	}

	protected function configure(): void
	{
		parent::configure();
		$this->addOption('rollback', 'r', InputOption::VALUE_NONE, 'Revert migrations (rollback)');
		$this->addArgument('version', InputArgument::OPTIONAL, 'Target version for migration');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$version    = $input->getArgument('version');
		$isRollback = $input->getOption('rollback');

		try
		{
			if ($isRollback)
				$this->migrationRunner->rollback($version);
			else
				$this->migrationRunner->execute($version);

			if ($this->migrationRunner->isApplied())
				$output->writeln('<info>Migration succeed.</info>');
			else
				$output->writeln('<comment>No migrations found to apply.</comment>');

		}
		catch (Exception | DatabaseException | FilesystemException $e)
		{
			$output->writeln('<error>Migration failed: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}
		return Command::SUCCESS;
	}
}
