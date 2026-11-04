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

namespace App\Shared\Versioning;

use App\Framework\Database\BaseRepositories\SqlBase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class UnitVersionsRepository extends SqlBase
{
	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'unit_versions', 'id');
	}

	/**
	 * @throws Exception
	 */
	public function findLastVersion(string $module, string $unitId): int
	{
		return (int) $this->connection->createQueryBuilder()
			->select('MAX(version)')
			->from($this->table)
			->where('module = :module')
			->andWhere('unit_id = :unitId')
			->setParameter('module', $module)
			->setParameter('unitId', $unitId)
			->executeQuery()
			->fetchOne();
	}

	/**
	 * @throws Exception
	 */
	public function insertVersion(string $module, string $unitId, int $version, int $UID, string $snapshot): void
	{
		$this->connection->insert($this->table, [
			'module' => $module, 'unit_id' => $unitId, 'version' => $version, 'UID' => $UID,
			'created_at' => date('Y-m-d H:i:s'), 'snapshot' => $snapshot
		]);
	}

	/**
	 * @return list<array{version:int, created_at:string, username:string}> the newest first
	 * @throws Exception
	 */
	public function findVersions(string $module, string $unitId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select($this->table . '.version', $this->table . '.created_at', 'user_main.username')
			->from($this->table)
			->leftJoin($this->table, 'user_main', 'user_main', 'user_main.UID = ' . $this->table . '.UID')
			->where('module = :module')
			->andWhere('unit_id = :unitId')
			->setParameter('module', $module)
			->setParameter('unitId', $unitId)
			->orderBy($this->table . '.version', 'DESC');

		$versions = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			$versions[] = ['version' => (int) $row['version'], 'created_at' => (string) $row['created_at'], 'username' => (string) ($row['username'] ?? '')];
		}

		return $versions;
	}

	/**
	 * @throws Exception
	 */
	public function findSnapshot(string $module, string $unitId, int $version): ?string
	{
		$snapshot = $this->connection->createQueryBuilder()
			->select('snapshot')
			->from($this->table)
			->where('module = :module')
			->andWhere('unit_id = :unitId')
			->andWhere('version = :version')
			->setParameter('module', $module)
			->setParameter('unitId', $unitId)
			->setParameter('version', $version)
			->executeQuery()
			->fetchOne();

		return is_string($snapshot) ? $snapshot : null;
	}

	/**
	 * Keeps the newest versions of the unit.
	 *
	 * @throws Exception
	 */
	public function deleteOlderThan(string $module, string $unitId, int $version): void
	{
		$this->connection->createQueryBuilder()
			->delete($this->table)
			->where('module = :module')
			->andWhere('unit_id = :unitId')
			->andWhere('version < :version')
			->setParameter('module', $module)
			->setParameter('unitId', $unitId)
			->setParameter('version', $version)
			->executeStatement();
	}

	/**
	 * @param list<string> $unitIds
	 * @throws Exception
	 */
	public function deleteUnits(string $module, array $unitIds): void
	{
		$this->connection->createQueryBuilder()
			->delete($this->table)
			->where('module = :module')
			->andWhere('unit_id IN (:unitIds)')
			->setParameter('module', $module)
			->setParameter('unitIds', $unitIds, ArrayParameterType::STRING)
			->executeStatement();
	}
}
