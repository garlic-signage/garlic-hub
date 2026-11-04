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

namespace App\Shared\User\Repositories\Core;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Repository class for managing VIP user data interactions with the database.
 * One row per user, role (vip_name, e.g. player_editor) and unit.
 */
class UserVipRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'user_vip', 'UID');
	}

	/**
	 * @return list<string>
	 * @throws Exception
	 */
	public function findUnitIds(int $UID, string $vipName): array
	{
		$rows = $this->findAllByWithFields(['unit_id'], ['UID' => $UID, 'vip_name' => $vipName], [], [], '', [['sort' => 'unit_id']]);

		return array_map(fn(array $row) => (string) $row['unit_id'], $rows);
	}

	/**
	 * @param list<string> $vipNames
	 * @return list<array{UID:int, vip_name:string}>
	 * @throws Exception
	 */
	public function findUsersOfUnit(array $vipNames, string $unitId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('UID', 'vip_name')
			->from($this->table)
			->where('vip_name IN (:vipNames)')
			->andWhere('unit_id = :unitId')
			->setParameter('vipNames', $vipNames, ArrayParameterType::STRING)
			->setParameter('unitId', $unitId)
			->orderBy('UID');

		$users = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			$users[] = ['UID' => (int) $row['UID'], 'vip_name' => (string) $row['vip_name']];
		}

		return $users;
	}

	/**
	 * @param list<string> $vipNames
	 * @param list<string> $unitIds
	 * @return list<int>
	 * @throws Exception
	 */
	public function findUserIdsOfUnits(array $vipNames, array $unitIds): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('DISTINCT UID')
			->from($this->table)
			->where('vip_name IN (:vipNames)')
			->andWhere('unit_id IN (:unitIds)')
			->setParameter('vipNames', $vipNames, ArrayParameterType::STRING)
			->setParameter('unitIds', $unitIds, ArrayParameterType::STRING);

		return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
	}

	/**
	 * @param list<string> $vipNames
	 * @param list<string> $unitIds
	 * @throws Exception
	 */
	public function deleteUnits(array $vipNames, array $unitIds): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->delete($this->table)
			->where('vip_name IN (:vipNames)')
			->andWhere('unit_id IN (:unitIds)')
			->setParameter('vipNames', $vipNames, ArrayParameterType::STRING)
			->setParameter('unitIds', $unitIds, ArrayParameterType::STRING);

		return (int) $queryBuilder->executeStatement();
	}
}
