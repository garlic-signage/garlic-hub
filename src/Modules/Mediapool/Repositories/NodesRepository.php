<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Mediapool\Repositories;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * NodesRepository is responsible for interacting with the 'mediapool_nodes' database table.
 * This class provides methods for retrieving and managing node-related data such as node details,
 * user root nodes, and node ownership information.
 */
class NodesRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'mediapool_nodes', 'node_id');
	}

	/**
	 * @return array{
	 *     UID:int, username:string, company_id:int, node_id:int, visibility:int, root_id:int, is_user_folder:int,
	 *      parent_id:int, level:int, lft:int, rgt:int,
	 *     last_updated:string, create_date:string, name:string, media_location:string,
	 *     children:int
	 * }
	 * @throws Exception
	 */
	public function getNode(int $nodeId): array
	{
		$select = [$this->table.'.UID, username, company_id, node_id, visibility, root_id, is_user_folder, parent_id, level, lft, rgt, last_updated, create_date, name, media_location, ROUND((rgt - lft - 1) / 2) AS children'];
		$where = ['node_id' => $this->generateWhereClause($nodeId)];
		$join  = ['user_main' => $this->table.'.UID = user_main.UID'];

		/** @var array{
		 * UID:int, username:string, company_id:int, node_id:int, visibility:int, root_id:int, is_user_folder:int,
		 * parent_id:int, level:int, lft:int, rgt:int,
		 * last_updated:string, create_date:string, name:string, media_location:string,
		 * children:int
		 * } $result */
		$result = $this->getFirstDataSet($this->findAllByWithFields($select, $where, $join));
		return $result;
	}

	/**
	 * @return array{UID:int, company_id:int, node_id:int, root_id:int}|array<empty,empty>
	 * @throws Exception
	 */
	public function getUserRootNode(int $UID): array
	{
		$select = [$this->table.'.UID', 'company_id', 'node_id', 'root_id'];
		$where = [
			$this->table.'.UID' => $this->generateWhereClause($UID),
			'parent_id' => $this->generateWhereClause(0),
			'is_user_folder' => $this->generateWhereClause(1)
		];
		$join  = ['user_main' => $this->table.'.UID = user_main.UID'];

		return $this->getFirstDataSet($this->findAllByWithFields($select, $where, $join));
	}

	/**
	 * @return array{UID:int, node_id:int, parent_id:int, name:string, company_id:int, visibility:int}
	 * @throws Exception|DatabaseException
	 */
	public function findNodeOwner(int $nodeId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('user_main.UID, node_id, parent_id, name, company_id, visibility')
			->from($this->table)
			->leftJoin($this->table,
				'user_main',
				'user_main',
				$this->table.'.UID = user_main.UID')
			->where('node_id = :id')
			->orderBy('lft', 'ASC')
			->setParameter('id', $nodeId);

		/** @var array{UID:int, node_id:int, parent_id:int, name:string, company_id:int, visibility:int}|false $ret */
		$ret = $queryBuilder->executeQuery()->fetchAssociative();
		if ($ret === false)
			throw new DatabaseException('Node not found');

		return $ret;
	}

}