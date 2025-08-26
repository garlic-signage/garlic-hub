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

namespace App\Framework\Database\BaseRepositories;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Represents a transaction manager that encapsulates connection-based transaction handling.
 * Provides methods to begin, commit, and roll back transactions, as well as check transaction status.
 *
 * This class is immutable and relies on an internal connection instance for managing transactions.
 *
 * Throws exceptions on failure of transaction-related operations.
 */
readonly class Transactions
{
	private Connection $connection;

	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * @throws Exception
	 */
	public function begin(): void
	{
		$this->connection->beginTransaction();
	}

	public function isActive(): bool
	{
		return $this->connection->isTransactionActive();
	}

	/**
	 * @throws Exception
	 */
	public function commit(): void
	{
		$this->connection->commit();
	}

	/**
	 * @throws Exception
	 */
	public function rollBack(): void
	{
		$this->connection->rollBack();
	}

}