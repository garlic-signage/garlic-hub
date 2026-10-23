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
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * The PlayerRepository class is responsible for managing database operations
 * related to players. The datatable filtering is done by PlayerFilterDefinition.
 */
class PlayerRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection,'player', 'player_id');
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function findAllForDashboard(): array
	{
		$platform   = $this->connection->getDatabasePlatform();
		$driverName = strtolower(str_replace('Doctrine\DBAL\Platforms\\', '', get_class($platform)));
		if ($driverName === 'sqliteplatform')
		{
			$sql = "SELECT
				SUM(CASE WHEN last_access >= DATETIME('now', '-' || (2 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS active,
				SUM(CASE WHEN last_access < DATETIME('now', '-' || (2 * refresh) || ' seconds')
						 AND last_access >= DATETIME('now', '-' || (4 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN last_access < DATETIME('now', '-' || (4 * refresh) || ' seconds') THEN 1 ELSE 0 END) AS inactive
			FROM
				player;";
		}
		else
		{
			$sql = 'SELECT
            SUM(CASE WHEN last_access >= DATE_SUB(NOW(), INTERVAL (2 * refresh) SECOND) THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN last_access < DATE_SUB(NOW(), INTERVAL (2 * refresh) SECOND)
                      AND last_access >= DATE_SUB(NOW(), INTERVAL (4 * refresh) SECOND) THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN last_access < DATE_SUB(NOW(), INTERVAL (4 * refresh) SECOND) THEN 1 ELSE 0 END) AS inactive
        FROM
            player;';
		}

		$result =  $this->connection->fetchAssociative($sql);
		if ($result === false)
			return [];

		return $result;
	}

	/**
	 * @param int[] $playlistIds
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findPlaylistIdsByPlaylistIds(array $playlistIds): array
	{
		if (empty($playlistIds))
			return [];

		$sql = 'SELECT playlist_id FROM '.$this->getTable().' WHERE playlist_id IN(?)';

		return $this->connection->executeQuery($sql, [array_map(intval(...), $playlistIds)], [ArrayParameterType::INTEGER])->fetchAllAssociative();
	}
}
