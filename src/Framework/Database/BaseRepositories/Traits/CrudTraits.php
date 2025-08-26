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

namespace App\Framework\Database\BaseRepositories\Traits;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Trait CrudTraits
 *
 * Provides a set of common CRUD (Create, Read, Update, Delete) operations for managing database records.
 */
trait CrudTraits
{
	/**
	 * Inserts a new record into the database and returns the new insert id.
	 *
	 * internally creates a prepared statement
	 * @param array<string, mixed> $fields
	 * @throws Exception
	 */
	public function insert(array $fields): int|string
	{
		$this->connection->insert($this->getTable(), $fields);
		return $this->connection->lastInsertId();
	}


	/**
	 * Updates a record in the database by ID and returns the affected rows.
	 * internally creates a prepared statement
	 *
	 * @param array<string,mixed> $fields
	 * @throws Exception
	 */
	public function update(int|string $id, array $fields): int
	{
		return (int) $this->connection->update($this->getTable(), $fields, [$this->getIdField() => $id]);
	}

	/**
	 * Updates records in the database with a custom WHERE clause.
	 *
	 * @param array<string,mixed> $fields
	 * @param array<string,mixed> $conditions
	 * @return int Number of affected rows
	 * @throws Exception
	 */
	public function updateWithWhere(array $fields, array $conditions): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->getTable());

		foreach ($fields as $field => $value)
		{
			$queryBuilder->set($field, ":set_$field");
			$queryBuilder->setParameter("set_$field", $value);
		}

		foreach ($conditions as $field => $value)
		{
			$queryBuilder->andWhere("$field = :$field");
			$queryBuilder->setParameter($field, $value);
		}

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * Deletes a record from the database by ID and returns the affected rows.
	 *
	 * @throws Exception
	 */
	public function delete(int|string $id): int
	{
		return $this->deleteByField($this->getIdField(), $id);
	}

	/**
	 * Deletes records from the database by a specific field and a value.
	 * Returns the affected rows
	 *
	 * @throws Exception
	 */
	public function deleteByField(string $field, mixed $value): int
	{
		return (int) $this->connection->delete($this->getTable(), [$field => $value]);
	}

	/**
	 * Deletes records from the database with a custom WHERE clause.
	 * @param array<string,mixed> $conditions
	 * @throws Exception
	 */
	public function deleteBy(array $conditions): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->delete($this->getTable());

		$this->determineConditions($queryBuilder, $conditions);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @param array<string,mixed> $conditions
	 */
	protected function determineConditions(QueryBuilder $queryBuilder, array $conditions): void
	{
		foreach ($conditions as $field => $parameter)
		{
			$value    = $parameter['value'] ?? $parameter;
			$compare  = $parameter['compare'] ?? '=';
			$logic    = $parameter['logic'] ?? 'AND';

			$govno = str_replace('.', '', $field); // because DBAl do not accept SQL dots like table.field
			if ($compare === 'IN')
				$placeholder = "(:$govno)";
			else
				$placeholder = ":$govno";

			switch ($logic)
			{
				case 'OR':
					$queryBuilder->orWhere("$field $compare $placeholder");
					break;
				case 'AND':
				default:
					$queryBuilder->andWhere("$field $compare $placeholder");
			}

			if ($compare === 'IN')
				$queryBuilder->setParameter($govno, explode(',', $value), $parameter['type']);
			else
				$queryBuilder->setParameter($govno, $value);
		}
	}

	/**
	 * Secure that the return value will be an array
	 *
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	protected function fetchAssociative(QueryBuilder $queryBuilder): array
	{
		$result =  $queryBuilder->executeQuery()->fetchAssociative();

		if ($result === false)
			return [];

		return $result;
	}

	/**
	 * @return string[]
	 */
	protected function secureExplode(string $data): array
	{
		if (empty($data))
			return [];

		return explode(',', $data);
	}

	/**
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	protected function secureUnserialize(?string $data): array
	{
		if (empty($data))
			return [];

		$ar = @unserialize($data);
		if (!is_array($ar))
			return [];

		return $ar;
	}

	/**
	 * @param list<array<string,mixed>>|array<string,mixed> $data
	 */
	protected function secureImplode(array $data): string
	{
		return implode(',', $data);
	}

	/**
	 * @param list<array<string,mixed>>|array<string,mixed> $data
	 */
	protected function secureSerialize(array $data): string
	{
		return  @serialize($data);
	}
}