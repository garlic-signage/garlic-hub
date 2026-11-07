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

namespace App\Shared\Llm;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The AI providers of an owner (a user or a company), see LlmSettings.
 */
abstract class LlmConnectionRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection, string $table, private readonly string $ownerField)
	{
		parent::__construct($connection, $table, 'connection_id');
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findAllOfOwner(int $ownerId): array
	{
		return $this->findAllBy([$this->ownerField => $ownerId], [], [], '', [['sort' => 'name', 'order' => 'ASC']]);
	}

	/**
	 * @return array<int|string,mixed> empty when the provider is not one of the owner
	 * @throws Exception
	 */
	public function findOfOwner(int $ownerId, int $connectionId): array
	{
		return $this->findFirstBy([$this->ownerField => $ownerId, 'connection_id' => $connectionId]);
	}

	/**
	 * @param array<string,string> $fields
	 * @return int the provider, a new one without $connectionId
	 * @throws Exception
	 */
	public function store(int $ownerId, int $connectionId, array $fields): int
	{
		if ($connectionId === 0)
			return (int) $this->insert([$this->ownerField => $ownerId] + $fields);

		$this->updateWithWhere($fields, [$this->ownerField => $ownerId, 'connection_id' => $connectionId]);

		return $connectionId;
	}

	/**
	 * @throws Exception
	 */
	public function deleteOfOwner(int $ownerId, int $connectionId): int
	{
		return $this->deleteBy([$this->ownerField => $ownerId, 'connection_id' => $connectionId]);
	}
}
