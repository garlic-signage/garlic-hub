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
use Doctrine\DBAL\ArrayParameterType;
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

	/**
	 * All playlists and the ones with the given release status. Without UID all playlists (module admin),
	 * with UID the own ones and the shared ones, like the list.
	 *
	 * @param list<int> $sharedPlaylistIds
	 * @return array{total:int, with_status:int}
	 * @throws Exception
	 */
	public function countForDashboard(string $releaseStatus, ?int $UID = null, array $sharedPlaylistIds = []): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('COUNT(1) AS total', 'SUM(CASE WHEN release_status = :release_status THEN 1 ELSE 0 END) AS with_status')
			->from('playlists')
			->setParameter('release_status', $releaseStatus);

		if ($UID !== null)
		{
			$scope = ['playlists.UID = :uid'];
			$queryBuilder->setParameter('uid', $UID, ParameterType::INTEGER);
			if ($sharedPlaylistIds !== [])
			{
				$scope[] = 'playlists.playlist_id IN (:shared_ids)';
				$queryBuilder->setParameter('shared_ids', $sharedPlaylistIds, ArrayParameterType::INTEGER);
			}
			$queryBuilder->where('(' . implode(' OR ', $scope) . ')');
		}

		$result = $queryBuilder->executeQuery()->fetchAssociative();

		// the sum of a table without playlists is NULL
		return ['total' => (int) ($result['total'] ?? 0), 'with_status' => (int) ($result['with_status'] ?? 0)];
	}

	/**
	 * @param list<int> $playlistIds
	 * @return list<int> the ones which exist
	 * @throws Exception
	 */
	public function findExistingIds(array $playlistIds): array
	{
		if ($playlistIds === [])
			return [];

		$existing = $this->connection->createQueryBuilder()
			->select('playlist_id')
			->from($this->table)
			->where('playlist_id IN (:playlistIds)')
			->setParameter('playlistIds', $playlistIds, ArrayParameterType::INTEGER)
			->executeQuery()
			->fetchFirstColumn();

		// @infection-ignore-all the casts are for databases which give text, ours gives numbers
		return array_map('intval', $existing);
	}

	public function delete(int|string $id): int
	{
		// the items go explicitly: SQLite cascades only with a pragma, and it ignores the pragma inside a transaction
		$this->connection->delete('playlists_items', ['playlist_id' => $id]);

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
			$queryBuilder->set($key, ':' . $key);
			$queryBuilder->setParameter($key, $value);
		}
		$queryBuilder->set('export_time', 'CURRENT_TIMESTAMP');

		$queryBuilder->where('playlist_id = :playlist_id');
		$queryBuilder->setParameter('playlist_id', $playlistId, ParameterType::INTEGER);

		return $queryBuilder->executeStatement();
	}
}
