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
use App\Framework\Database\Migration\Runner;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The real migrations of the edge edition on an empty database, up and down. These tests are the
 * safety net for a release: every migration must apply, must be reversible and must keep the data.
 */
#[Group('db')]
class RunnerTest extends TestCase
{
	private const string MIGRATIONS = __DIR__ . '/../../../../../migrations/edge';

	private Connection $connection;

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

	public function testEveryMigrationHasAnUpAndADownFileAndTheNumbersHaveNoGap(): void
	{
		$versions = ['up' => [], 'down' => []];
		foreach (scandir(self::MIGRATIONS) ?: [] as $file)
		{
			if (preg_match('/^(\d{3})_[a-z0-9_]+\.(up|down)\.sql$/', $file, $m))
				$versions[$m[2]][] = (int) $m[1];
		}
		$up   = $versions['up'];
		$down = $versions['down'];
		sort($up);
		sort($down);

		static::assertNotEmpty($up);
		static::assertSame($up, $down, 'Every migration needs an up and a down file.');
		static::assertSame(range(1, count($up)), $up, 'The version numbers must be 1, 2, 3 ... without a gap.');
	}

	public function testExecuteAppliesAllMigrationsAndRecordsTheVersions(): void
	{
		$runner = $this->runner(self::MIGRATIONS);

		$runner->execute();

		static::assertTrue($runner->isApplied());
		static::assertSame([1, 2, 3], $this->versions());
		foreach (['user_main', 'user_acl', 'player', 'playlists', 'playlists_items', 'mediapool_nodes', 'mediapool_files', 'templates'] as $table)
			static::assertContains($table, $this->tables());
	}

	public function testExecuteAgainChangesNothing(): void
	{
		$this->runner(self::MIGRATIONS)->execute();
		$schema = $this->schema();

		$second = $this->runner(self::MIGRATIONS);
		$second->execute();

		static::assertFalse($second->isApplied());
		static::assertSame([1, 2, 3], $this->versions());
		static::assertSame($schema, $this->schema());
	}

	/**
	 * Runner::execute() filters the files with array_filter() without ARRAY_FILTER_USE_KEY, so the
	 * callback gets the file name instead of the version and every file passes.
	 */
	public function testExecuteUpToATargetVersionStopsThere(): void
	{
		$this->runner(self::MIGRATIONS)->execute(2);

		static::assertSame([1, 2], $this->versions());
		static::assertContains('templates', $this->tables());
		// version 3 renames multizone to layout
		static::assertContains('multizone', $this->columns('playlists'));
		static::assertNotContains('layout', $this->columns('playlists'));

		$this->runner(self::MIGRATIONS)->execute();

		static::assertSame([1, 2, 3], $this->versions());
		static::assertContains('layout', $this->columns('playlists'));
	}

	public function testRollbackRemovesEverything(): void
	{
		$runner = $this->runner(self::MIGRATIONS);
		$runner->execute();

		$runner->rollback();

		static::assertSame([], $this->versions());
		static::assertSame(['_migration_version'], array_values(array_diff($this->tables(), ['sqlite_sequence'])));
	}

	public function testRollbackOfOneVersionRestoresTheSchemaBeforeIt(): void
	{
		$schemas = [];
		foreach ([1, 2, 3] as $version)
		{
			$this->withMigrationsUpTo($version, fn(string $dir) => $this->runner($dir)->execute());
			$schemas[$version] = $this->schema();
		}

		$this->runner(self::MIGRATIONS)->rollback(3);

		static::assertSame($schemas[2], $this->schema(), 'The down migration of version 3 must undo exactly its up migration.');
		static::assertSame([1, 2], $this->versions());
	}

	public function testRollbackOfTheVersionsOneByOne(): void
	{
		$this->runner(self::MIGRATIONS)->execute();
		$runner = $this->runner(self::MIGRATIONS);

		$runner->rollback(3);
		static::assertSame([1, 2], $this->versions());

		$runner->rollback(2);
		static::assertSame([1], $this->versions());
	}

	/**
	 * Runner::rollback() takes the files from the directory, not the applied versions from the
	 * database. A version which was undone already is undone a second time and fails.
	 */
	public function testRollbackSkipsVersionsWhichAreNotApplied(): void
	{
		$this->runner(self::MIGRATIONS)->execute();
		$runner = $this->runner(self::MIGRATIONS);
		$runner->rollback(3);

		$runner->rollback(3);

		static::assertSame([1, 2], $this->versions());
	}

	public function testUpDownUpGivesTheSameSchema(): void
	{
		$runner = $this->runner(self::MIGRATIONS);
		$runner->execute();
		$first = $this->schema();

		$runner->rollback();
		$runner->execute();

		static::assertSame($first, $this->schema());
		static::assertSame([1, 2, 3], $this->versions());
	}

	/**
	 * What an update does to a database with data: the data must still be there.
	 */
	public function testTheLastMigrationsKeepTheDataOfAnOlderDatabase(): void
	{
		$this->withMigrationsUpTo(1, fn(string $dir) => $this->runner($dir)->execute());
		$this->connection->executeStatement("INSERT INTO user_main (UID, username, email, status) VALUES (1, 'admin', 'admin@example.com', 3), (2, 'anna', 'anna@example.com', 3)");
		$this->connection->executeStatement("INSERT INTO playlists (UID, playlist_name, multizone) VALUES (2, 'Lobby', 'a:1:{s:4:\"zone\";i:1;}')");
		$this->connection->executeStatement("INSERT INTO player (UID, playlist_id, uuid, player_name) VALUES (2, 1, '11111111-1111-4111-8111-111111111111', 'Eingang')");
		$this->connection->executeStatement("INSERT INTO mediapool_files (media_id, node_id, UID, checksum, mimetype, filename, extension) VALUES ('m1', 2, 2, 'aaaa', 'image/jpeg', 'bild.jpg', 'jpg')");

		$this->runner(self::MIGRATIONS)->execute();

		static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
		static::assertSame('a:1:{s:4:"zone";i:1;}', $this->connection->fetchOne("SELECT layout FROM playlists WHERE playlist_name = 'Lobby'"));
		static::assertSame('Eingang', $this->connection->fetchOne('SELECT player_name FROM player'));
		static::assertSame('bild.jpg', $this->connection->fetchOne("SELECT filename FROM mediapool_files WHERE media_id = 'm1'"));
		// the admin gets the rights for the templates module
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_acl WHERE UID = 1 AND module = 'templates'"));
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_acl WHERE UID = 2 AND module = 'templates'"));
	}

	public function testABrokenMigrationIsRolledBackAndNotRecorded(): void
	{
		$dir = $this->temporaryMigrations([
			'001_first.up.sql'    => 'CREATE TABLE one (id INTEGER);',
			'001_first.down.sql'  => 'DROP TABLE one;',
			'002_broken.up.sql'   => "CREATE TABLE two (id INTEGER);\nINSERT INTO does_not_exist VALUES (1);",
			'002_broken.down.sql' => 'DROP TABLE two;',
		]);

		try
		{
			$this->runner($dir)->execute();
			static::fail('Expected a DatabaseException');
		}
		catch (DatabaseException)
		{
			static::assertSame([1], $this->versions());
			static::assertContains('one', $this->tables());
			static::assertNotContains('two', $this->tables(), 'The part of the broken migration which worked must be undone.');
		}
		finally
		{
			$this->removeDirectory($dir);
		}
	}

	public function testRollbackWithoutAMigrationTableFails(): void
	{
		$this->expectException(DatabaseException::class);
		$this->runner(self::MIGRATIONS)->rollback();
	}

	private function runner(string $directory): Runner
	{
		return new Runner(new Repository($this->connection), new Filesystem(new LocalFilesystemAdapter($directory)));
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
		return array_map('strval', $this->connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"));
	}

	/**
	 * @return list<string>
	 */
	private function columns(string $table): array
	{
		return array_map('strval', array_column($this->connection->fetchAllAssociative('PRAGMA table_info(' . $table . ')'), 'name'));
	}

	/**
	 * Tables, indexes and triggers with their definition, without the bookkeeping of the migrations.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function schema(): array
	{
		return $this->connection->fetchAllAssociative(
			"SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' AND name <> '_migration_version' ORDER BY type, name"
		);
	}

	/**
	 * Runs the callback with a directory which holds only the migrations up to the version.
	 *
	 * @param callable(string):mixed $callback
	 */
	private function withMigrationsUpTo(int $version, callable $callback): void
	{
		$files = [];
		foreach (scandir(self::MIGRATIONS) ?: [] as $file)
		{
			if (preg_match('/^(\d{3})_.*\.sql$/', $file, $m) && (int) $m[1] <= $version)
				$files[$file] = (string) file_get_contents(self::MIGRATIONS . '/' . $file);
		}

		$dir = $this->temporaryMigrations($files);
		try
		{
			$callback($dir);
		}
		finally
		{
			$this->removeDirectory($dir);
		}
	}

	/**
	 * @param array<string,string> $files
	 */
	private function temporaryMigrations(array $files): string
	{
		$dir = sys_get_temp_dir() . '/garlic-hub-migrations-' . bin2hex(random_bytes(4));
		mkdir($dir);
		foreach ($files as $name => $sql)
			file_put_contents($dir . '/' . $name, $sql);

		return $dir;
	}

	private function removeDirectory(string $dir): void
	{
		foreach (glob($dir . '/*') ?: [] as $file)
			unlink($file);
		rmdir($dir);
	}
}
