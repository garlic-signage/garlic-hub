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

namespace App\Shared\Acl;

use App\Framework\Database\BaseRepositories\SqlBase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * The units of a module are rows of the table of its repository, every row has the UID of its owner.
 * The company comes from the owner, so one provider fits every module.
 */
class TableUnitProvider implements AclUnitProvider
{
	public function __construct(
		protected readonly Connection $connection,
		private readonly SqlBase $repository,
		private readonly string $nameField
	) {}

	public function findUnit(int|string $unitId): array
	{
		$table        = $this->repository->getTable();
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select($table . '.' . $this->repository->getIdField() . ' AS id', 'user_main.UID', 'user_main.company_id')
			->from($table)
			->innerJoin($table, 'user_main', 'user_main', 'user_main.UID = ' . $table . '.UID')
			->where($table . '.' . $this->repository->getIdField() . ' = :id')
			->setParameter('id', $unitId);

		$unit = $queryBuilder->executeQuery()->fetchAssociative();
		if ($unit === false)
			return [];

		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		return ['id' => (string) $unit['id'], 'UID' => (int) $unit['UID'], 'company_id' => (int) $unit['company_id']];
	}

	public function searchUnits(string $search, int $limit): array
	{
		$queryBuilder = $this->selectUnits()
			->where($this->repository->getTable() . '.' . $this->nameField . " LIKE :search ESCAPE '!'")
			->setParameter('search', '%' . addcslashes($search, '%_!') . '%')
			->orderBy($this->repository->getTable() . '.' . $this->nameField)
			->setMaxResults($limit);

		$units = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			// @infection-ignore-all the casts are for databases which give text, ours gives numbers
			$units[] = ['id' => (string) $row['id'], 'name' => (string) $row['name'], 'company_id' => (int) $row['company_id']];
		}

		return $this->describe($units);
	}

	public function findUnitsByIds(array $unitIds): array
	{
		if ($unitIds === [])
			return [];

		$queryBuilder = $this->selectUnits()
			->where($this->repository->getTable() . '.' . $this->repository->getIdField() . ' IN (:ids)')
			->setParameter('ids', $unitIds, ArrayParameterType::STRING);

		$units = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			// @infection-ignore-all the casts are for databases which give text, ours gives numbers
			$units[] = ['id' => (string) $row['id'], 'name' => (string) $row['name'], 'company_id' => (int) $row['company_id']];
		}

		$byId = [];
		foreach ($this->describe($units) as $unit)
		{
			$byId[$unit['id']] = ['name' => $unit['name'], 'company_id' => $unit['company_id']];
		}

		return $byId;
	}

	/**
	 * The name under which a user finds the unit, the name of the row by default.
	 *
	 * @param list<array{id:string, name:string, company_id:int}> $units
	 * @return list<array{id:string, name:string, company_id:int}>
	 */
	protected function describe(array $units): array
	{
		return $units;
	}

	private function selectUnits(): QueryBuilder
	{
		$table        = $this->repository->getTable();
		$queryBuilder = $this->connection->createQueryBuilder();

		return $queryBuilder
			->select($table . '.' . $this->repository->getIdField() . ' AS id', $table . '.' . $this->nameField . ' AS name', 'user_main.company_id')
			->from($table)
			->innerJoin($table, 'user_main', 'user_main', 'user_main.UID = ' . $table . '.UID');
	}
}
