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
use Doctrine\DBAL\Connection;

/**
 * The units of a module are rows of the table of its repository, every row has the UID of its owner.
 * The company comes from the owner, so one provider fits every module.
 */
class TableUnitProvider implements AclUnitProvider
{
	public function __construct(
		private readonly Connection $connection,
		private readonly SqlBase $repository
	) {}

	public function findUnit(int|string $unitId): array
	{
		$table        = $this->repository->getTable();
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('user_main.UID', 'user_main.company_id')
			->from($table)
			->innerJoin($table, 'user_main', 'user_main', 'user_main.UID = ' . $table . '.UID')
			->where($table . '.' . $this->repository->getIdField() . ' = :id')
			->setParameter('id', $unitId);

		$unit = $queryBuilder->executeQuery()->fetchAssociative();
		if ($unit === false)
			return [];

		return ['UID' => (int) $unit['UID'], 'company_id' => (int) $unit['company_id']];
	}
}
