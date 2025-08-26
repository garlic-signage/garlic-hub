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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Services\AbstractBaseService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service class that extends AbstractBaseService to handle various operations
 * for managing hierarchical nodes within a repository.
 * Provides functionality for retrieving, adding, deleting, and moving nodes and trees.
 */
class Service extends AbstractBaseService
{
	private readonly Repository $repository;
	private readonly Calculator $calculator;
	private readonly Transactions $transactions;

	public function __construct(Repository $repository, Calculator $calculator, Transactions $transactions, LoggerInterface $logger)
	{
		$this->repository   = $repository;
		$this->calculator   = $calculator;
		$this->transactions = $transactions;

		parent::__construct($logger);
	}

	public function initRepository(string $table, string $id): void
	{
		$this->repository->setTable($table);
		$this->repository->setIdField($id);
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllRootNodes(): array
	{
		return $this->repository->findAllRootNodes();
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findTreeByRootId(int $rootId): array
	{
		return $this->repository->findTreeByRootId($rootId);
	}

	/**
	 * @return array<string, mixed>
	 * @throws Exception
	 * @throws DatabaseException
	 */
	public function findNodeOwner(int $nodeId): array
	{
		return $this->repository->findNodeOwner($nodeId);
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllChildNodesByParentNode(int $nodeId): array
	{
		return $this->repository->findAllChildNodesByParentNode($nodeId);
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllChildrenInTreeOfNodeId(int $nodeId): array
	{
		return $this->repository->findAllChildrenInTreeOfNodeId($nodeId);
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function findRootIdRgtAndLevelByNodeId(int $node_id):array
	{
		return $this->repository->findRootIdRgtAndLevelByNodeId($node_id);
	}

	/**
	 * returns all node ids of a given root_id
	 *
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllSubNodeIdsByRootIdsAndPosition(int $root_id, int $node_rgt, int $node_lft): array
	{
		return $this->repository->findAllSubNodeIdsByRootIdsAndPosition($root_id, $node_rgt, $node_lft);
	}

	/**
	 * @throws Exception
	 */
	public function addRootNode(int $UID, string $name, bool $isUserFolder = false): int
	{
		try
		{
			$this->transactions->begin();

			$nodeData = [
				'name'              => $name,
				'parent_id'         => 0,
				'root_order'        => 0,
				'visibility'	    => 0,
				'is_user_folder'    => (!$isUserFolder) ? 0 : 1,
				'lft'               => 1,
				'rgt'               => 2,
				'UID'               => $UID,
				'level'             => 1
			];

			$newNodeId = (int) $this->repository->insert($nodeData);
			if ($newNodeId == 0)
				throw new FrameworkException('Insert new node failed');

			$update_fields = ['root_id' => $newNodeId, 'root_order' => $newNodeId];

			if ($this->repository->update($newNodeId, $update_fields) === 0)
				throw new FrameworkException('Update root node failed');


			$this->transactions->commit();

			return $newNodeId;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error('Add root node failed because of: '. $e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return 0;
		}
	}

	/**
	 * @param array<string,mixed> $parentNode
	 * @throws Exception
	 */
	public function addSubNode(int $UID, string $name, array $parentNode): int
	{
		try
		{
			$this->transactions->begin();

			$fields = [
				'lft' => $parentNode['rgt'],
				'rgt' => $parentNode['rgt'] + 1,
				'parent_id' => $parentNode['node_id'],
				'root_order' => 0,
				'visibility' => 0,
				'root_id' => $parentNode['root_id'],
				'level' => $parentNode['level'] + 1,
				'name' => $name,
				'UID' => $UID
			];

			$this->repository->moveNodesToLeftForInsert($parentNode['root_id'], $parentNode['rgt']);
			$this->repository->moveNodesToRightForInsert($parentNode['root_id'], $parentNode['rgt']);

			$newNodeId = (int)$this->repository->insert($fields);
			if ($newNodeId == 0)
			{
				throw new FrameworkException('Insert new sub node failed.');
			}

			$this->transactions->commit();

			return $newNodeId;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return 0;
		}
	}

	/**
	 * @param array<string,mixed> $node
	 * @return bool
	 * @throws Exception
	 */
	public function deleteSingleNode(array $node): bool
	{
		try
		{
			$this->transactions->begin();

			if ($this->repository->delete($node['node_id']) === 0 )
				throw new FrameworkException('Node not exists.');

			$this->repository->moveNodesToLeftForDeletion($node['root_id'], $node['rgt']);
			$this->repository->moveNodesToRightForDeletion($node['root_id'], $node['rgt']);

			$this->transactions->commit();
			return true;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return false;
		}
	}

	/**
	 * @param array<string,mixed> $node
	 * @throws Exception
	 */
	public function deleteTree(array $node): bool
	{
		try
		{
			$this->transactions->begin();

			if ($this->repository->deleteFullTree($node['root_id'], $node['rgt'], $node['lft']) === 0)
				throw new FrameworkException('Node not exists.');

			// remove other nodes to create some space
			$move = (int) floor(($node['rgt'] - $node['lft']) / 2);
			$move = 2 * (1 + $move);

			$this->repository->moveNodesToLeftForDeletion($node['root_id'], $node['rgt'], $move);
			$this->repository->moveNodesToRightForDeletion($node['root_id'], $node['rgt'], $move);

			$this->transactions->commit();
			return true;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return false;
		}
	}

	/**
	 * @param array<string,mixed> $movedNode
	 * @param array<string,mixed> $targetNode
	 * @throws Exception
	 */
	public function moveNode(array $movedNode, array $targetNode, string $region): bool
	{
		try
		{
			$this->transactions->begin();

			$diffLevel = $this->calculator->calculateDiffLevelByRegion($region, $movedNode['level'], $targetNode['level']);
			$newLgtPos = $this->calculator->determineLgtPositionByRegion($region, $targetNode);
			$width     = $movedNode['rgt'] - $movedNode['lft'] + 1;

			// https://rogerkeays.com/how-to-move-a-node-in-nested-sets-with-sql
			// enhanced for including multiple trees with different root_id's in datatable

			// create some space in a target
			$this->repository->moveNodesToRightForInsert($targetNode['root_id'], $newLgtPos, $width);
			$this->repository->moveNodesToLeftForInsert($targetNode['root_id'], $newLgtPos, $width);

			$calculated = $this->calculator->calculateBeforeMoveSubTree($movedNode, $targetNode, $newLgtPos, $width);
			$i = $this->repository->moveSubTree($movedNode, $targetNode, $calculated, $diffLevel);
			if ($i === 0)
				throw new FrameworkException($movedNode['name']. ' cannot be moved via '.$region.' of '. $targetNode['name']);

			$this->repository->update(
				$movedNode['node_id'],
				['parent_id' => $this->calculator->determineParentIdByRegion($region, $targetNode)]
			);

			// close source space if not a root node
			if ($movedNode['parent_id'] !== 0)
			{
				$this->repository->moveNodesToRightForDeletion($movedNode['root_id'], $movedNode['rgt'], $width);
				$this->repository->moveNodesToLeftForDeletion($movedNode['root_id'], $movedNode['rgt'], $width);
			}

			$this->transactions->commit();
			return true;
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return false;
		}
	}
}