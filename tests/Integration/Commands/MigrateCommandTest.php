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

namespace Tests\Integration\Commands;

use App\Commands\MigrateCommand;
use App\Framework\Database\Migration\Repository;
use App\Framework\Database\Migration\Runner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command with the real Runner and the real migrations on an empty database:
 * what a user gets from "db:migrate" and "db:migrate --rollback" on the command line.
 */
#[Group('db')]
class MigrateCommandTest extends TestCase
{
	private const string MIGRATIONS = __DIR__ . '/../../../migrations/edge';

	private Connection $connection;
	private CommandTester $tester;

	protected function setUp(): void
	{
		parent::setUp();

		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
	}

	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}

	public function testMigrateAppliesAllMigrations(): void
	{
		$this->runCommand([]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration succeed.', $this->tester->getDisplay());
		static::assertSame([1, 2, 3], $this->versions());
		static::assertContains('user_main', $this->tables());
	}

	public function testMigrateTwiceReportsNothingToApply(): void
	{
		$this->runCommand([]);

		$this->runCommand([]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertStringContainsString('No migrations found to apply.', $this->tester->getDisplay());
		static::assertSame([1, 2, 3], $this->versions());
	}

	public function testMigrateToVersionStopsThere(): void
	{
		$this->runCommand(['version' => '2']);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertSame([1, 2], $this->versions());
	}

	public function testRollbackToVersionUndoesOnlyThatVersion(): void
	{
		$this->runCommand([]);

		$this->runCommand(['--rollback' => true, 'version' => '3']);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertSame([1, 2], $this->versions());
	}

	public function testRollbackWithoutVersionUndoesEverything(): void
	{
		$this->runCommand([]);

		$this->runCommand(['-r' => true]);

		static::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
		static::assertSame([], $this->versions());
		static::assertNotContains('user_main', $this->tables());
	}

	public function testInvalidVersionIsRejectedAndChangesNothing(): void
	{
		$this->runCommand(['version' => 'abc']);

		static::assertSame(Command::FAILURE, $this->tester->getStatusCode());
		static::assertStringContainsString('Invalid version "abc"', $this->tester->getDisplay());
		static::assertNotContains('_migration_version', $this->tables());
	}

	public function testRollbackOnEmptyDatabaseFails(): void
	{
		$this->runCommand(['--rollback' => true]);

		static::assertSame(Command::FAILURE, $this->tester->getStatusCode());
		static::assertStringContainsString('Migration failed: Migration table not found.', $this->tester->getDisplay());
	}

	/**
	 * Every call is a new process on the command line, so it gets a new command with a new Runner.
	 *
	 * @param array<string, mixed> $input
	 */
	private function runCommand(array $input): void
	{
		$runner       = new Runner(new Repository($this->connection), new Filesystem(new LocalFilesystemAdapter(self::MIGRATIONS)));
		$this->tester = new CommandTester(new MigrateCommand($runner));
		$this->tester->execute($input);
	}

	/**
	 * @return list<int>
	 */
	private function versions(): array
	{
		return array_map('intval', $this->connection->fetchFirstColumn('SELECT version FROM _migration_version ORDER BY version'));
	}

	/**
	 * @return list<string>
	 */
	private function tables(): array
	{
		return $this->connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table'");
	}
}
