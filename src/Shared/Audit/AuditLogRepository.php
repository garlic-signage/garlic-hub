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

namespace App\Shared\Audit;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class AuditLogRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'audit_log', 'id');
	}

	/**
	 * The names of the users an entry was done to, for the list.
	 *
	 * @param list<int> $UIDs
	 * @return array<int,string> UID => username
	 * @throws Exception
	 */
	public function findUsernames(array $UIDs): array
	{
		if ($UIDs === [])
			return [];

		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('UID', 'username')
			->from('user_main')
			->where('UID IN (:UIDs)')
			->setParameter('UIDs', $UIDs, ArrayParameterType::INTEGER);

		$usernames = [];
		foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row)
		{
			$usernames[(int) $row['UID']] = (string) $row['username'];
		}

		return $usernames;
	}

	/**
	 * @throws Exception
	 */
	public function deleteOlderThan(DateTimeImmutable $time): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->delete($this->table)
			->where('created_at < :time')
			->setParameter('time', $time->format('Y-m-d H:i:s'));

		return (int) $queryBuilder->executeStatement();
	}
}
