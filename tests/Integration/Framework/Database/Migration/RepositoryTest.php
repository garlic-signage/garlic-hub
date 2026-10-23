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

namespace Tests\Integration\Framework\Database\Migration;

use App\Framework\Database\Migration\Repository;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The bookkeeping of the migrations on an empty database.
 */
#[Group('db')]
class RepositoryTest extends TestCase
{
	private Connection $connection;
	private Repository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
		$this->repository = new Repository($this->connection);
	}

	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}

	/**
	 * @throws Exception
	 */
	public function testAnEmptyDatabaseHasNoTables(): void
	{
		static::assertSame([], $this->repository->showTables());
	}

	/**
	 * @throws Exception
	 */
	public function testCreateMigrationTableCanBeCalledTwice(): void
	{
		$this->repository->createMigrationTable();
		$this->repository->createMigrationTable();

		$tables = $this->repository->showTables();
		static::assertCount(1, $tables);
		static::assertSame('_migration_version', $tables[0]->getName());
	}

	/**
	 * @throws Exception
	 */
	public function testShowColumnsOfTheMigrationTable(): void
	{
		$this->repository->createMigrationTable();

		$columns = array_keys($this->repository->showColumns());

		static::assertSame(['version', 'migrated_at'], $columns);
	}

	/**
	 * @throws Exception
	 */
	public function testAppliedMigrationsAreTheRecordedVersions(): void
	{
		$this->repository->createMigrationTable();
		static::assertSame([], $this->repository->getAppliedMigrations());

		$this->repository->insert(['version' => 1]);
		$this->repository->insert(['version' => 2]);

		$applied = $this->repository->getAppliedMigrations();
		static::assertSame([1, 2], array_map('intval', array_column($applied, 'version')));
		static::assertNotEmpty($this->connection->fetchOne('SELECT migrated_at FROM _migration_version WHERE version = 1'));
	}

	/**
	 * @throws Exception
	 * @throws DatabaseException
	 */
	public function testApplySqlBatchRunsSeveralStatements(): void
	{
		$result = $this->repository->applySqlBatch('CREATE TABLE a (id INTEGER); CREATE TABLE b (id INTEGER);');

		static::assertSame($this->repository, $result);
		static::assertCount(2, $this->repository->showTables());
	}

	/**
	 * @throws Exception
	 */
	public function testApplySqlBatchRollsBackABrokenBatchAndNamesTheSql(): void
	{
		try
		{
			$this->repository->applySqlBatch('CREATE TABLE a (id INTEGER); INSERT INTO nowhere VALUES (1);');
			static::fail('Expected a DatabaseException');
		}
		catch (DatabaseException $e)
		{
			static::assertStringContainsString('INSERT INTO nowhere', $e->getMessage());
			static::assertSame([], $this->repository->showTables());
		}
	}
}
