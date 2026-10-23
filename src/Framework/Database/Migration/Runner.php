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

namespace App\Framework\Database\Migration;

use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;

/**
 * Class Runner
 *
 * This class is responsible for managing database migrations, providing functionalities to apply
 * and roll back migrations. It interacts with a repository layer and a filesystem to execute
 * migration tasks and maintain a migration history.
 */
class Runner
{
	private Repository $migrateRepository;
	private Filesystem $filesystem;

	private bool $applied = false;

	public function __construct(Repository $MigrateRepository, Filesystem $filesystem)
	{
		$this->migrateRepository = $MigrateRepository;
		$this->filesystem = $filesystem;
	}

	/**
	 * @param int|null $targetVersion
	 * @throws DatabaseException
	 * @throws Exception
	 * @throws FilesystemException
	 */
	public function execute(?int $targetVersion = null): void
	{
		if (!$this->hasMigrationTable())
			$this->migrateRepository->createMigrationTable();

		$appliedMigrations   = $this->getAppliedMigrations();
		$availableMigrations = $this->determineAvailableMigrations();

		// Filter all available migrations up to the target version, the key of the array is the version
		$targetMigrations = $targetVersion
			? array_filter($availableMigrations, fn(int $version) => $version <= $targetVersion, ARRAY_FILTER_USE_KEY)
			: $availableMigrations; // or apply all available migrations

		foreach ($targetMigrations as $version => $file)
		{
			$ar = array_column($appliedMigrations, 'version');
			if (in_array($version, $ar))
				continue;

			$this->applyMigration($version, $file);

			$this->applied = true;
		}
	}

	/**
	 * @throws Exception
	 * @throws DatabaseException
	 * @throws FilesystemException
	 */
	public function rollback(?int $targetVersion = null): void
	{
		if (!$this->hasMigrationTable())
			throw new DatabaseException('Migration table not found.');

		// only what is applied can be undone: the files of a version which was undone already do not fit any more
		$applied = array_map('intval', array_column($this->getAppliedMigrations(), 'version'));
		$availableRollbacks = array_filter(
			$this->determineAvailableRollbacks(),
			fn(int $version) => in_array($version, $applied, true),
			ARRAY_FILTER_USE_KEY
		);

		// undo the versions from the newest down to the target version, without a target all of them
		foreach (array_reverse($availableRollbacks, true) as $version => $file)
		{
			if ($targetVersion !== null && $version < $targetVersion)
				break;

			$this->rollbackMigration($version, $file);
			$this->applied = true;
		}
	}

	public function isApplied(): bool
	{
		return $this->applied;
	}

	/**
	 * @throws Exception
	 */
	private function hasMigrationTable(): bool
	{
		return !empty($this->migrateRepository->showTables());
	}

	/**
	 * @param int $version
	 * @param string $file
	 * @throws DatabaseException
	 * @throws Exception
	 * @throws FilesystemException
	 */
	private function applyMigration(int $version, string $file): void
	{
		$sql = $this->filesystem->read($file);
		$this->migrateRepository->applySqlBatch($sql);
		$this->recordMigration($version);
	}

	/**
	 * @throws DatabaseException
	 * @throws FilesystemException
	 * @throws Exception
	 */
	private function rollbackMigration(int $version, string $file): void
	{
		$sql = $this->filesystem->read($file);
		$this->migrateRepository->applySqlBatch($sql);
		$this->removeMigrationRecord($version);
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	private function getAppliedMigrations(): array
	{
		return $this->migrateRepository->getAppliedMigrations();
	}

	/**
	 * @return array<int,string>
	 * @throws FilesystemException
	 */
	private function determineAvailableMigrations(): array
	{
		return $this->determineAvailableTasks();
	}

	/**
	 * @return array<int,string>
	 * @throws FilesystemException
	 */
	private function determineAvailableRollbacks(): array
	{
		return $this->determineAvailableTasks('down');
	}

	/**
	 * @return array<int,string>
	 * @throws FilesystemException
	 */
	private function determineAvailableTasks(string $direction = 'up'): array
	{
		$files = $this->filesystem->listContents('', false);

		$migrations = [];
		foreach ($files as $file)
		{
			if ($file->isFile() && preg_match('/^(\d+)_.*\.'.$direction.'\.sql$/', $file->path(), $matches))
				$migrations[(int)$matches[1]] = $file->path();
		}
		ksort($migrations);
		return $migrations;
	}
	/**
	 * @throws Exception
	 */
	private function recordMigration(int $version): void
	{
		$this->migrateRepository->insert(['version' => $version]);
	}

	/**
	 * @throws Exception
	 */
	private function removeMigrationRecord(int $version): void
	{
		$this->migrateRepository->delete($version);
	}

}
