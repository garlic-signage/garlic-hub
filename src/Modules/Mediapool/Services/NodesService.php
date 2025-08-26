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

namespace App\Modules\Mediapool\Services;

use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Repositories\NodesRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;

/**
 * Class NodesService
 * Provides services for managing nodes within a hierarchical structure.
 */
class NodesService
{
	private readonly NodesRepository $nodesRepository;
	private readonly MediaService $mediaService;
	private readonly AclValidator $aclValidator;
	private readonly Service $nestedSetsService;
	public int $UID {
		set {$this->UID = $value;}
	}

	public function __construct(NodesRepository $nodesRepository, Service $nestedSetsService, MediaService $mediaService, AclValidator $aclValidator)
	{
		$this->nodesRepository = $nodesRepository;
		$this->nestedSetsService = $nestedSetsService;
		$this->nestedSetsService->initRepository('mediapool_nodes', 'node_id');
		$this->mediaService    = $mediaService;
		$this->aclValidator    = $aclValidator;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function isModuleAdmin(int $UID): bool
	{
		return $this->aclValidator->isModuleAdmin($UID);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function isNodeUploadable(int $nodeId): bool
	{
		$node = $this->nodesRepository->getNode($nodeId);
		if ($node === [])
			throw new ModuleException('mediapool','Node Id: '.$nodeId.' not exists.');

		$rights = $this->determineRights($node);

		return $rights['create'];
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function getNodes(int $parent_id): array
	{
		if ($parent_id === 0)
			$nodes = $this->nestedSetsService->findAllRootNodes();
		else
			$nodes = $this->nestedSetsService->findAllChildNodesByParentNode($parent_id);

		$tree = [];
		/** @var array{
		 * UID:int, username:string, company_id:int, node_id:int, visibility:int, root_id:int, is_user_folder:int,
		 * parent_id:int, level:int, lft:int, rgt:int,
		 * last_updated:string, create_date:string, name:string, media_location:string,
		 * children:int
		 * } $node */
		foreach ($nodes as $node)
		{
			$rights = $this->determineRights($node);
			$tree[] = $this->prepareForWunderbaum($node, $rights);
		}

		return $tree;
	}

	/**
	 * @param int $parent_id
	 * @param string $name
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function addNode(int $parent_id, string $name): int
	{
		if ($parent_id === 0)
			$new_node_id = $this->addRootNode($name);
		else
			$new_node_id =  $this->addSubNode($parent_id, $name);

		return $new_node_id;
	}

	/**
	 * @param int $UID
	 * @param string $name
	 * @return int
	 * @throws Exception
	 */
	public function addUserDirectory(int $UID, string $name): int
	{
		return $this->nestedSetsService->addRootNode($UID, $name, true);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function deleteUserDirectory(int $UID): void
	{
		$node = $this->nodesRepository->getUserRootNode($UID);
		// if directory not exists, nothing should happen.
		if (count($node) === 0)
			return;

		/** @var array{UID:int, company_id:int, node_id:int, root_id:int} $node */
		if (!$this->aclValidator->isModuleAdmin($this->UID))
			throw new ModuleException('mediapool','No rights to delete user root node.');

		$condition = ['root_id' => $this->nodesRepository->generateWhereClause($node['root_id'])];
		$result = $this->nodesRepository->findAllBy($condition);
		foreach ($result as $node)
		{
			$this->mediaService->markDeleteMediaByNodeId($node['node_id']);
		}

		if ($this->nodesRepository->deleteBy($condition) == 0)
			throw new ModuleException('mediapool','Delete nodes by root id failed.');
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException|PhpfastcacheSimpleCacheException
	 */
	public function editNode(int $id, string $name, ?int $visibility = null): int
	{
		$node = $this->nodesRepository->getNode($id);
		if ($node === [])
			throw new ModuleException('mediapool', 'Parent node not found');

		$rights = $this->determineRights($node);
		if (!$rights['edit'])
			throw new ModuleException('mediapool', 'No rights to edit node ' . $node['name']);

		$data = ['name' => $name];
		if ($this->aclValidator->isModuleAdmin($this->UID) && !is_null($visibility))
			$data['visibility'] = $visibility;

		return $this->nodesRepository->update($id, $data);
	}

	/**
	 * @param int $movedNodeId
	 * @param int $targetNodeId
	 * @param string $region
	 * @return int
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function moveNode(int $movedNodeId, int $targetNodeId, string $region): int
	{
		$regions = ['before', 'after', 'appendChild'];
		if (!in_array($region, $regions))
			throw new ModuleException('mediapool', $region.' is not supported');

		/** @var array<string,mixed> $movedNode */
		$movedNode  = $this->nodesRepository->getNode($movedNodeId);
		/** @var array<string,mixed> $targetNode */
		$targetNode = $this->nodesRepository->getNode($targetNodeId);

		// prevent root dir handling
		if ($movedNode['parent_id'] === 0)
			throw new ModuleException('mediapool', 'Moving root node is not allowed');

		if (($region === Calculator::REGION_APPENDCHILD && $targetNodeId === 0) ||
			(($region === Calculator::REGION_BEFORE || $region === Calculator::REGION_AFTER) &&
				$targetNode['parent_id'] === 0))
			throw new ModuleException('mediapool', 'Create root node with a move is not allowed');


		$this->nestedSetsService->moveNode($movedNode, $targetNode, $region);

		return 1;
	}

	/**
	 * @param int $nodeId
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function deleteNode(int $nodeId): int
	{
		$node = $this->nodesRepository->getNode($nodeId);
		if (empty($node))
			throw new ModuleException('mediapool', 'Can not find a node for node_id ' . $nodeId);

		$rights = $this->determineRights($node);
		if (!$rights['delete'])
			throw new ModuleException('mediapool', 'No rights to delete node ' . $nodeId);

		// get all node_id of the partial tree
		$deleted_nodes = $this->nestedSetsService->findAllSubNodeIdsByRootIdsAndPosition($node['root_id'], $node['rgt'], $node['lft']);

		if ($node['children'] == 0)
			$this->nestedSetsService->deleteSingleNode($node);
		elseif ($node['children'] > 0)
			$this->nestedSetsService->deleteTree($node);

		return count($deleted_nodes);
	}

	/**
	 * @param array{
	 *      UID:int, username:string, company_id:int, node_id:int, visibility:int, root_id:int, is_user_folder:int,
	 *       parent_id:int, level:int, lft:int, rgt:int,
	 *      last_updated:string, create_date:string, name:string, media_location:string,
	 *      children:int
	 *  } $node
	 * @param array<string,bool|string> $rights
	 * @return array<string,mixed>
	 */
	private function prepareForWunderbaum(array $node, array $rights): array
	{
		return [
			'title' => $node['name'],
			'folder' => true,
			'key' => $node['node_id'],
			'lazy' => ($node['children'] > 0),
			'rights' => $rights,
			'UID' => $node['UID'],
			'visibility' => $node['visibility']
		];
	}

	/**
	 * @param string $name
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function addRootNode(string $name): int
	{
		if (!$this->aclValidator->isModuleAdmin($this->UID))
			throw new ModuleException('mediapool','No rights to add root node.');

		return $this->nestedSetsService->addRootNode($this->UID, $name);
	}


	/**
	 * @param int $parentNodeId
	 * @param string $name
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function addSubNode(int $parentNodeId, string $name): int
	{
		$parentNode = $this->nodesRepository->getNode($parentNodeId);
		if (count($parentNode) === 0)
			throw new ModuleException('mediapool', 'Parent node not found');

		$rights      = $this->determineRights($parentNode);
		if (!$rights['create'])
			throw new ModuleException('mediapool', 'No rights to add node under: ' . $parentNode['name']);

		return $this->nestedSetsService->addSubNode($this->UID, $name, $parentNode);
	}

	/**
	 * @param array{UID:int, node_id:int, parent_id:int, name:string, company_id:int, visibility:int, ...} $node
	 * @return array{create:bool, edit:bool, delete:bool, share:string}
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function determineRights(array $node): array
	{
		$rights     = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);

		if(!$rights['read'])
			return ['create' => false, 'edit' => false, 'delete' => false, 'share' => ''];

		$create = false;
		$edit = false;
		$delete = false;
		// only admin can edit and delete and root nodes
		if ($node['parent_id'] === 0)
		{
			if ($this->aclValidator->isModuleAdmin($this->UID))
				return ['create' => true, 'edit' => true, 'delete' => true, 'share' => $rights['share']];
			else if ($this->UID == $node['UID']) // only User dirs
			{
				$create = true;
			}
		}
		else
		{
			$create = $rights['create'];
			$edit = $rights['edit'];
			$delete = $rights['create'];
		}

		return ['create' => $create, 'edit' => $edit, 'delete' => $delete, 'share' => $rights['share']];
	}
}