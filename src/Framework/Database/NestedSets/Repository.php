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

namespace App\Framework\Database\NestedSets;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Class Repository
 *
 * This class extends the functionality of SqlBase and provides additional methods
 * for handling hierarchical or tree-like structured data stored in relational databases.
 */
class Repository extends SqlBase
{
	use CrudTraits;

	public function __construct(Connection $connection, string $table, string $idField)
	{
		parent::__construct($connection, $table, $idField);
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllRootNodes(): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*, FLOOR((rgt-lft)/2) AS children')
			->from($this->table)
			->leftJoin($this->table,
				'user_main',
				'user_main',
				$this->table.'.UID = user_main.UID')
			->where('parent_id = 0')
			->orderBy('root_order', 'ASC');

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findTreeByRootId(int $rootId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();

		$queryBuilder->select('FLOOR((n.rgt-n.lft)/2) AS children, n.*, u.company_id')
			->from($this->table, 'n')
			->leftJoin('n', $this->table,'p','n.root_id = p.root_id')
			->leftJoin('n','user_name','u','n.UID = u.UID')
			->where('n.root_id = :root_id')
			->andWhere('n.lft BETWEEN p.lft AND p.rgt')
			->setParameter('root_id', $rootId)
			->groupBy('n.lft');

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @return array<string, mixed>
	 * @throws Exception|DatabaseException
	 */
	public function findNodeOwner(int $nodeId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('user_main.UID, node_id, name, company_id')
			->from($this->table)
			->leftJoin($this->table,
				'user_main',
				'user_main',
				$this->table.'.UID = user_main.UID')
			->where('node_id = :id')
			->orderBy('lft', 'ASC')
			->setParameter('id', $nodeId);

		$ret = $queryBuilder->executeQuery()->fetchAssociative();
		if ($ret === false)
			throw new DatabaseException('Node not found');

		return $ret;
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllChildNodesByParentNode(int $nodeId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('*, FLOOR((rgt-lft)/2) AS children')
			->from($this->table)
			->leftJoin($this->table,
				'user_main',
				'user_main',
				$this->table.'.UID = user_main.UID')
			->where('parent_id = :id')
			->orderBy('lft', 'ASC')
			->setParameter('id', $nodeId);

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllChildrenInTreeOfNodeId(int $nodeId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('root_id, rgt, lft')
			->from($this->table)
			->where('node_id = :node_id')
			->setParameter('node_id', $nodeId);

		$node_data   = $queryBuilder->executeQuery()->fetchAllAssociative();

		if (empty($node_data))
			return [];

		$queryBuilder2 = $this->connection->createQueryBuilder();
		$queryBuilder2->select('node_id, category_name')
			->from($this->table)
			->where('root_id = ' . (int) $node_data[0]['root_id']. ' AND (lft BETWEEN '.$node_data[0]['lft'].' AND '.$node_data[0]['rgt']);

		return $queryBuilder2->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findRootIdRgtAndLevelByNodeId(int $node_id):array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('root_id, rgt, lft')
			->from($this->table)
			->where('node_id = :node_id')
			->setParameter('node_id', $node_id);

		return $this->fetchAssociative($queryBuilder);
	}

	/**
	 * returns all node ids of a given root_id
	 *
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllSubNodeIdsByRootIdsAndPosition(int $root_id, int $node_rgt, int $node_lft): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();

		$queryBuilder->select('node_id')
			->from($this->table)
			->where('root_id = :root_id')
			->andWhere('lft >= :node_lft')
			->andWhere('rgt <= :node_rgt')
			->setParameter('root_id', $root_id)
			->setParameter('node_lft', $node_lft)
			->setParameter('node_rgt', $node_rgt);

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}


	/**
	 * @param array<string,mixed> $movedNode
	 * @param array<string,mixed> $targetNode
	 * @param array{distance:int, tmpPos:int, width:int} $calculated
	 * @throws Exception
	 */
	public function moveSubTree(array $movedNode, array $targetNode, array $calculated, int $diffLevel):
	int
	{
		$queryBuilder = $this->connection->createQueryBuilder();

		$queryBuilder->update($this->table)
			->set('lft', 'lft + :distance')
			->set('rgt', 'rgt + :distance')
			->set('level', 'level + :diff_level')
			->set('root_id', ':target_root_id')
			->where('root_id = :moved_root_id')
			->andWhere('lft >= :tmpPos')
			->andWhere('rgt < :tmpPos + :width')
			->setParameter('distance', $calculated['distance'])
			->setParameter('diff_level', $diffLevel)
			->setParameter('target_root_id', $targetNode['root_id'])
			->setParameter('moved_root_id', $movedNode['root_id'])
			->setParameter('tmpPos', $calculated['tmpPos'])
			->setParameter('width', $calculated['width'])
		;

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * moves existing nodes out of the way for inserting new nodes
	 *
	 * @throws Exception
	 */
	public function moveNodesToRightForInsert(int $rootId, int $position, int $width = 2): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('lft', 'lft + :steps')
			->where('root_id = :root_id')
			->andWhere('lft >= :position')
			->setParameter('steps', $width)
			->setParameter('root_id', $rootId)
			->setParameter('position', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function moveNodesToLeftForInsert(int $rootId, int $position, int $width = 2): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('rgt', 'rgt + :steps')
			->where('root_id = :root_id')
			->andWhere('rgt >= :position')
			->setParameter('steps', $width)
			->setParameter('root_id', $rootId)
			->setParameter('position', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function moveNodesToLeftForDeletion(int $rootId, int $position, int $width = 2): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('lft', 'lft - '.$width)
			->where('root_id = :root_id')
			->andWhere('lft > :position')
			->setParameter('root_id', $rootId)
			->setParameter('position', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function moveNodesToRightForDeletion(int $rootId, int $position, int $width = 2): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->table)
			->set('rgt', 'rgt - '.$width)
			->where('root_id = :root_id')
			->andWhere('rgt > :position')
			->setParameter('root_id', $rootId)
			->setParameter('position', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deleteFullTree(int $rootId, int $posRgt, int $posLft): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->delete($this->table)
			->where('root_id = :root_id')
			->andWhere('lft between :pos_lft AND :pos_rgt')
			->setParameter('root_id', $rootId)
			->setParameter('pos_lft', $posLft)
			->setParameter('pos_rgt', $posRgt);

		return (int) $queryBuilder->executeStatement();
	}

}