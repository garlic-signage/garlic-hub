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

namespace App\Modules\Playlists\Repositories;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;

class PlaylistsRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'playlists', 'playlist_id');
	}

	public function delete(int|string $id): int
	{
		$platform   = $this->connection->getDatabasePlatform();
		$driverName = strtolower(str_replace('Doctrine\DBAL\Platforms\\', '', get_class($platform)));
		if ($driverName === 'sqliteplatform')
			$this->connection->executeQuery('PRAGMA foreign_keys = ON');

		return $this->deleteByField($this->getIdField(), $id);
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findFirstWithUserName(int $playlistId): array
	{
		$select = [$this->table.'.*', 'user_main.username', 'user_main.company_id'];
		$join   = ['user_main' => 'user_main.UID=' . $this->table . '.UID'];
		$where  = ['playlist_id' => $playlistId];
		return $this->getFirstDataSet($this->findAllByWithFields($select, $where, $join));
	}

	/**
	 * @param array<string, mixed> $saveData
	 * @throws Exception
	 */
	public function updateExport(int $playlistId, array $saveData): int|string
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table);
		foreach ($saveData as $key => $value)
		{
			$queryBuilder->set($key, (string)$value);
		}
		$queryBuilder->set('export_time', 'CURRENT_TIMESTAMP');

		$queryBuilder->where('playlist_id = :playlist_id');
		$queryBuilder->setParameter('playlist_id', $playlistId, ParameterType::INTEGER);

		return $queryBuilder->executeStatement();
	}
}
