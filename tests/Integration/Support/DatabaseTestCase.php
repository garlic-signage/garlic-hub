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

namespace Tests\Integration\Support;

use App\Framework\Database\Migration\Repository;
use App\Framework\Database\Migration\Runner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests which run against a real database.
 *
 * The schema is built by the real migrations (migrations/edge), not by a copy for tests.
 * Whenever a migration changes the schema, the tests see it. Every test gets a fresh
 * in-memory database, so tests can not influence each other.
 *
 * Test data comes from SQL scripts in tests/resources/sql, loaded with loadFixture().
 *
 * Do not extend this class directly. Use RepositoryTestCase (group db) or
 * ContainerTestCase (group acl). Groups are not inherited, set them on every concrete class.
 */
abstract class DatabaseTestCase extends TestCase
{
	private const string MIGRATION_DIR = __DIR__ . '/../../../migrations/edge';
	private const string FIXTURE_DIR   = __DIR__ . '/../../resources/sql';

	protected Connection $connection;

	protected function setUp(): void
	{
		parent::setUp();

		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

		$runner = new Runner(
			new Repository($this->connection),
			new Filesystem(new LocalFilesystemAdapter(self::MIGRATION_DIR))
		);
		$runner->execute();
	}

	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}

	/**
	 * @param string $name path relative to tests/resources/sql, e.g. 'player/players.sql'
	 */
	protected function loadFixture(string $name): void
	{
		$sql = file_get_contents(self::FIXTURE_DIR . '/' . $name);
		if ($sql === false)
			self::fail('Fixture not found: ' . $name);

		$this->connection->executeStatement($sql);
	}
}
