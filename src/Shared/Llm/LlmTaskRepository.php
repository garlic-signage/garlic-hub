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

namespace App\Shared\Llm;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The tasks of an owner (a user or a company): one row per task, see LlmTask.
 */
abstract class LlmTaskRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection, string $table, string $ownerField)
	{
		parent::__construct($connection, $table, $ownerField);
	}

	/**
	 * @return array<string,array<string,mixed>> the rows by task
	 * @throws Exception
	 */
	public function findAllOfOwner(int $ownerId): array
	{
		$tasks = [];
		foreach ($this->findAllBy([$this->idField => $ownerId]) as $row)
			// @infection-ignore-all the column is text
			$tasks[(string) $row['task']] = $row;

		return $tasks;
	}

	/**
	 * @return array<int|string,mixed> empty without a row
	 * @throws Exception
	 */
	public function findTask(int $ownerId, string $task): array
	{
		return $this->findFirstBy([$this->idField => $ownerId, 'task' => $task]);
	}

	/**
	 * @param array<string,int|string|null> $fields
	 * @throws Exception
	 */
	public function storeTask(int $ownerId, string $task, array $fields): void
	{
		$this->deleteTask($ownerId, $task);
		$this->insert([$this->idField => $ownerId, 'task' => $task] + $fields);
	}

	/**
	 * @throws Exception
	 */
	public function deleteWithConnection(int $ownerId, int $connectionId): void
	{
		$this->deleteBy([$this->idField => $ownerId, 'connection_id' => $connectionId]);
	}

	/**
	 * @param array<string,int|string|null> $fields
	 * @throws Exception
	 */
	public function updateWithConnection(int $ownerId, int $connectionId, array $fields): void
	{
		$this->updateWithWhere($fields, [$this->idField => $ownerId, 'connection_id' => $connectionId]);
	}

	/**
	 * @throws Exception
	 */
	public function deleteTask(int $ownerId, string $task): void
	{
		$this->deleteBy([$this->idField => $ownerId, 'task' => $task]);
	}
}
