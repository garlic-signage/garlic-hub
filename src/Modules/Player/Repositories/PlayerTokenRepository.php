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

namespace App\Modules\Player\Repositories;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Repository class responsible for managing player tokens in the database.
 * Provides methods to query, update, and manage tokens associated with players.
 */
class PlayerTokenRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'player_tokens', 'token_id');
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findByPlayerId(int $playerId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*')
			->from($this->table)
			->where('player_id = :player_id')
			->setParameter('player_id', $playerId);

		return $this->fetchAssociative($queryBuilder);
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findValidTokens(): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*')
			->from($this->table)
			->where('expires_at > :now')
			->setParameter('now', date('Y-m-d H:i:s'));

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findExpiredTokens(): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*')
			->from($this->table)
			->where('expires_at <= :now')
			->setParameter('now', date('Y-m-d H:i:s'));

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @param array<string,mixed> $data
	 * @throws Exception
	 */
	public function updateForPlayer(int $playerId, array $data): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->getTable());
		$queryBuilder->set('updated_at', "'".date('Y-m-d H:i:s')."'");
		$queryBuilder->where('player_id = :player_id');
		$queryBuilder->setParameter('player_id', $playerId);

		return (int) $queryBuilder->executeStatement();
	}

}