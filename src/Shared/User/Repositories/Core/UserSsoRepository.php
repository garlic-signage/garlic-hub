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

/**
 * Which user an identity at an OpenID Connect provider is: provider and subject, see Auth\SsoLogin.
 */
class UserSsoRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'user_sso', 'UID');
	}

	/**
	 * @throws Exception
	 */
	public function findUID(int $providerId, string $subject): ?int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('UID')
			->from($this->table)
			->where('provider_id = :provider_id')
			->andWhere('subject = :subject')
			->setParameter('provider_id', $providerId)
			->setParameter('subject', $subject);

		$UID = $queryBuilder->executeQuery()->fetchOne();

		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		return is_numeric($UID) ? (int) $UID : null;
	}

	/**
	 * @throws Exception
	 */
	public function hasLink(int $UID, int $providerId): bool
	{
		return $this->connection->fetchOne('SELECT 1 FROM ' . $this->table . ' WHERE UID = ? AND provider_id = ?', [$UID, $providerId]) !== false;
	}

	/**
	 * @throws Exception
	 */
	public function link(int $UID, int $providerId, string $subject): void
	{
		$this->connection->insert($this->table, ['UID' => $UID, 'provider_id' => $providerId, 'subject' => $subject]);
	}

	/**
	 * @throws Exception
	 */
	public function removeOfProvider(int $providerId): void
	{
		$this->deleteBy(['provider_id' => $providerId]);
	}
}
