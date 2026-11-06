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
 * The secret of the second factor of a user, see TwoFactorService.
 */
class UserTwoFactorRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'user_two_factor', 'UID');
	}

	/**
	 * Takes the time slot of a code only when it is newer than the last one: two requests with the same code
	 * do not both get in.
	 *
	 * @throws Exception
	 */
	public function takeTimeSlot(int $UID, int $timeSlot): bool
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('last_time_slot', ':time_slot')
			->where('UID = :uid')
			->andWhere('last_time_slot < :time_slot')
			->setParameter('time_slot', $timeSlot, ParameterType::INTEGER)
			->setParameter('uid', $UID, ParameterType::INTEGER);

		return $queryBuilder->executeStatement() === 1;
	}
}
