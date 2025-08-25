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

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;

/**
 * The Repository class extends the SqlBase class and provides high-level database
 * interaction functionalities, including operations such as creating a migration table,
 * retrieving applied migrations, executing SQL batches, and listing database schema details.
 */
class Reposißtory extends SqlBase
{
	use CrudTraits;

	const string MIGRATION_TABLE_NAME = '_migration_version';

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, self::MIGRATION_TABLE_NAME, 'version');
	}

	/**
	 * @throws Exception
	 */
	public function createMigrationTable(): void
	{
		$sql = 'CREATE TABLE IF NOT EXISTS ' . self::MIGRATION_TABLE_NAME . ' (
					version INTEGER PRIMARY KEY,
					migrated_at DATETIME DEFAULT CURRENT_TIMESTAMP
		);';
		$this->connection->executeStatement($sql);
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function getAppliedMigrations(): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*')->from($this->table);

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @throws DatabaseException|Exception
	 */
	public function applySqlBatch(string $sqlBatch): static
	{
		$this->connection->beginTransaction();

		try
		{
			$this->connection->executeStatement($sqlBatch);
			$this->connection->commit();
		}
		catch (Exception $e)
		{
			$this->connection->rollback();
			$message = $e->getMessage() . ' SQL: ' . $sqlBatch;
			$code = $e->getCode();
			throw new DatabaseException($message, $code);
		}
		return $this;
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function showColumns(): array
	{
		return $this->connection->createSchemaManager()->listTableColumns($this->getTable());
	}

	/**
	 * @return list<Table>
	 * @throws Exception
	 */
	public function showTables(): array
	{
		return $this->connection->createSchemaManager()->listTables();
	}

}