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
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\NodesRepository;
use Doctrine\DBAL\Exception;

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
	private readonly UserSession $userSession;

	public function __construct(NodesRepository $nodesRepository, Service $nestedSetsService, MediaService $mediaService, AclValidator $aclValidator, UserSession $userSession)
	{
		$this->nodesRepository = $nodesRepository;
		$this->nestedSetsService = $nestedSetsService;
		$this->nestedSetsService->initRepository('mediapool_nodes', 'node_id');
		$this->mediaService    = $mediaService;
		$this->aclValidator    = $aclValidator;
		$this->userSession     = $userSession;
	}

	/**
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
	 * @throws Exception
	 */
	public function isNodeUploadable(int $nodeId): bool
	{
		$node = $this->nodesRepository->getNode($nodeId);
		if ($node === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $nodeId]);

		$rights = $this->determineRights($node);

		return $rights['create'];
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
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
	 * @throws UserException
	 */
	public function deleteUserDirectory(int $UID): void
	{
		$node = $this->nodesRepository->getUserRootNode($UID);
		// if directory not exists, nothing should happen.
		if (count($node) === 0)
			return;

		/** @var array{UID:int, company_id:int, node_id:int, root_id:int} $node */
		if (!$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
			throw new AccessDeniedException('mediapool', 'user_directory_not_deletable', ['UID' => $UID]);

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
	 * @throws CoreException
	 * @throws UserException
	 */
	public function editNode(int $id, string $name, ?int $visibility = null): int
	{
		$node = $this->nodesRepository->getNode($id);
		if ($node === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $id]);

		$rights = $this->determineRights($node);
		if (!$rights['edit'])
			throw new AccessDeniedException('mediapool', 'node_not_editable', ['node_name' => $node['name']]);

		$data = ['name' => $name];
		if ($this->aclValidator->isModuleAdmin($this->userSession->getUID()) && !is_null($visibility))
			$data['visibility'] = $visibility;

		return $this->nodesRepository->update($id, $data);
	}

	/**
	 * @param int $movedNodeId
	 * @param int $targetNodeId
	 * @param string $region
	 * @return int
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function moveNode(int $movedNodeId, int $targetNodeId, string $region): int
	{
		$regions = ['before', 'after', 'appendChild'];
		if (!in_array($region, $regions))
			throw new ModuleException('mediapool', $region.' is not supported');

		$movedNode  = $this->nodesRepository->getNode($movedNodeId);
		if ($movedNode === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $movedNodeId]);

		// prevent root dir handling
		if ($movedNode['parent_id'] === 0)
			throw new TranslatableModuleException('mediapool', 'root_node_not_movable');

		if ($region === Calculator::REGION_APPENDCHILD && $targetNodeId === 0)
			throw new TranslatableModuleException('mediapool', 'root_node_not_creatable_by_move');

		$targetNode = $this->nodesRepository->getNode($targetNodeId);
		if ($targetNode === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $targetNodeId]);

		if (($region === Calculator::REGION_BEFORE || $region === Calculator::REGION_AFTER) && $targetNode['parent_id'] === 0)
			throw new TranslatableModuleException('mediapool', 'root_node_not_creatable_by_move');

		// the ids come from the request: the user must be allowed to take the directory away from
		// where it is ...
		if (!$this->determineRights($movedNode)['delete'])
			throw new AccessDeniedException('mediapool', 'node_not_editable', ['node_name' => $movedNode['name']]);

		// ... and to create directories where it is moved to: below the target or next to it,
		// which means below the parent of the target
		$newParent = $region === Calculator::REGION_APPENDCHILD ? $targetNode : $this->nodesRepository->getNode($targetNode['parent_id']);
		if ($newParent === [] || !$this->determineRights($newParent)['create'])
			throw new AccessDeniedException('mediapool', 'node_not_creatable', ['node_name' => $targetNode['name']]);

		$this->nestedSetsService->moveNode($movedNode, $targetNode, $region);

		return 1;
	}

	/**
	 * @param int $nodeId
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function deleteNode(int $nodeId): int
	{
		$node = $this->nodesRepository->getNode($nodeId);
		if ($node === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $nodeId]);

		$rights = $this->determineRights($node);
		if (!$rights['delete'])
			throw new AccessDeniedException('mediapool', 'node_not_deletable', ['node_name' => $node['name']]);

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
	 * @throws UserException
	 */
	private function addRootNode(string $name): int
	{
		if (!$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
			throw new AccessDeniedException('mediapool', 'root_node_not_creatable');

		return $this->nestedSetsService->addRootNode($this->userSession->getUID(), $name);
	}


	/**
	 * @param int $parentNodeId
	 * @param string $name
	 * @return int
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function addSubNode(int $parentNodeId, string $name): int
	{
		$parentNode = $this->nodesRepository->getNode($parentNodeId);
		if ($parentNode === [])
			throw new NotFoundException('mediapool', 'node_not_found', ['node_id' => $parentNodeId]);

		$rights      = $this->determineRights($parentNode);
		if (!$rights['create'])
			throw new AccessDeniedException('mediapool', 'node_not_creatable', ['node_name' => $parentNode['name']]);

		return $this->nestedSetsService->addSubNode($this->userSession->getUID(), $name, $parentNode);
	}

	/**
	 * @param array{UID:int, node_id:int, parent_id:int, name:string, company_id:int, visibility:int, ...} $node
	 * @return array{create:bool, edit:bool, delete:bool, share:string}
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function determineRights(array $node): array
	{
		$UID        = $this->userSession->getUID();
		$rights     = $this->aclValidator->checkDirectoryPermissions($UID, $node);

		if(!$rights['read'])
			return ['create' => false, 'edit' => false, 'delete' => false, 'share' => ''];

		$create = false;
		$edit = false;
		$delete = false;
		// only admin can edit and delete and root nodes
		if ($node['parent_id'] === 0)
		{
			if ($this->aclValidator->isModuleAdmin($UID))
				return ['create' => true, 'edit' => true, 'delete' => true, 'share' => $rights['share']];
			else if ($UID == $node['UID']) // only User dirs
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