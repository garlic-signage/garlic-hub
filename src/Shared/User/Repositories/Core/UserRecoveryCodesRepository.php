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

namespace App\Shared\User\Repositories\Core;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;

/**
 * The hashes of the recovery codes of a user, see TwoFactorService.
 */
class UserRecoveryCodesRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'user_recovery_codes', 'UID');
	}

	/**
	 * Uses a code once: the update finds it only while it is unused, so two requests do not both get in.
	 *
	 * @throws Exception
	 */
	public function useCode(int $UID, string $codeHash): bool
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('used_at', ':used_at')
			->where('UID = :uid')
			->andWhere('code_hash = :code_hash')
			->andWhere('used_at IS NULL')
			->setParameter('used_at', date('Y-m-d H:i:s'))
			->setParameter('uid', $UID, ParameterType::INTEGER)
			->setParameter('code_hash', $codeHash);

		return $queryBuilder->executeStatement() === 1;
	}

	/**
	 * @throws Exception
	 */
	public function countUnused(int $UID): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('COUNT(*)')
			->from($this->table)
			->where('UID = :uid')
			->andWhere('used_at IS NULL')
			->setParameter('uid', $UID, ParameterType::INTEGER);

		return (int) $queryBuilder->executeQuery()->fetchOne();
	}
}
