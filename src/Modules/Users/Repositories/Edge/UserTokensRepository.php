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

namespace App\Modules\Users\Repositories\Edge;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Class UserTokensRepository interacts with the `user_tokens` table in the database.
 * It provides methods to manage user token records, such as retrieving specific tokens,
 * finding valid tokens for a particular user, and refreshing token expiration.
 *
 * Extends the base functionality of the SqlBase class and utilizes:
 * - CrudTraits: for basic CRUD operations.
 * - FindOperationsTrait: for extending find-related operations.
 *
 * Constructor establishes the table name and the primary identifier.
 */
class UserTokensRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'user_tokens', 'token');
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findFirstByToken(string $token): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('user_tokens.*, username, status, company_id')
			->from($this->table)
			->leftJoin('user_tokens', 'user_main', '', 'user_main.UID = user_tokens.UID')
			->where('token = :token')
			->setParameter('token', $token);

		return $this->fetchAssociative($queryBuilder);
	}

	/**
	 * @return list<array{token:string, UID: int, purpose: string, expires_at: string, used_at:string|null}>|array<empty,empty>
	 * @throws Exception
	 */
	public function findValidByUID(int $UID): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('token, UID, purpose, expires_at, used_at')
			->from($this->table)
			->where('UID = :uid')
			->andWhere('used_at IS NULL')
			->setParameter('uid', $UID);

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @throws Exception
	 */
	public function refresh(string $token, string $expiresAt): int
	{
		$fields = ['expires_at' => $expiresAt];

		return $this->updateWithWhere($fields, ['token' => $token]);
	}

}