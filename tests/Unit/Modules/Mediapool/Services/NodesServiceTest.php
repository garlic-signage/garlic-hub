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

namespace Tests\Unit\Modules\Mediapool\Services;

use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class NodesServiceTest extends TestCase
{
	private NodesRepository&MockObject $nodesRepositoryMock;
	private Service&MockObject $nestedSetServiceMock;
	private MediaService&MockObject $mediaServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private UserSession&Stub $userSessionStub;
	private NodesService $nodesService;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->nodesRepositoryMock = $this->createMock(NodesRepository::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->nestedSetServiceMock = $this->createMock(Service::class);
		$this->mediaServiceMock = $this->createMock(MediaService::class);
		$this->userSessionStub = $this->createStub(UserSession::class);

		$this->nodesService = new NodesService(
			$this->nodesRepositoryMock, $this->nestedSetServiceMock, $this->mediaServiceMock, $this->aclValidatorMock, $this->userSessionStub
		);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsModuleAdmin(): void
	{
		$this->expectUntouched($this->nodesRepositoryMock, $this->nestedSetServiceMock, $this->mediaServiceMock);
		$uid = 123;
		$this->aclValidatorMock->expects($this->once())
			->method('isModuleAdmin')
			->with($uid)
			->willReturn(true);

		$result = $this->nodesService->isModuleAdmin($uid);
		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsNodeUploadable(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 123;
		$node = ['node_id' => 123, 'UID' => 456, 'parent_id' => 0];
		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->userSessionStub->method('getUID')->willReturn(456);

		$this->nodesRepositoryMock->expects($this->once())->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$this->aclValidatorMock->expects($this->once())->method('checkDirectoryPermissions')
			->with(456, $node)
			->willReturn($rights);

		$result = $this->nodesService->isNodeUploadable($nodeId);
		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsNodeUploadableThrowsException(): void
	{
		$this->expectUntouched($this->aclValidatorMock, $this->nestedSetServiceMock, $this->mediaServiceMock);
		$nodeId = 123;

		$this->nodesRepositoryMock->expects($this->once())->method('getNode')
			->with($nodeId)
			->willReturn([]);

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('node_not_found');

		$this->nodesService->isNodeUploadable($nodeId);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetNodesRoot(): void
	{
		$this->nodesRepositoryMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$parentId = 0;
		$nodes = [['node_id' => 1, 'name' => 'Root Node', 'children' => 0, 'parent_id' => 0, 'UID' => 123, 'visibility' => 1]];
		$this->nestedSetServiceMock->expects($this->once())
			->method('findAllRootNodes')
			->willReturn($nodes);

		$this->nestedSetServiceMock->expects($this->never())
			->method('findAllChildNodesByParentNode');

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->userSessionStub->method('getUID')->willReturn(123);
		$result = $this->nodesService->getNodes($parentId);
		static::assertCount(1, $result);
		static::assertEquals('Root Node', $result[0]['title']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetNodesSub(): void
	{
		$this->nodesRepositoryMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$parentId = 21;
		$nodes = [['node_id' => 1, 'name' => 'Sub Node', 'children' => 0, 'parent_id' => 0, 'UID' => 123, 'visibility' => 1]];
		$this->nestedSetServiceMock->expects($this->never())
			->method('findAllRootNodes');

		$this->nestedSetServiceMock->expects($this->once())
			->method('findAllChildNodesByParentNode')
			->willReturn($nodes);

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->userSessionStub->method('getUID')->willReturn(123);
		$result = $this->nodesService->getNodes($parentId);
		static::assertCount(1, $result);
		static::assertEquals('Sub Node', $result[0]['title']);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddRootNode(): void
	{
		$this->nodesRepositoryMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$uid = 123;
		$name = 'New Root Node';
		$this->userSessionStub->method('getUID')->willReturn($uid);

		$this->aclValidatorMock->expects($this->once())
			->method('isModuleAdmin')
			->with($uid)
			->willReturn(true);

		$this->nestedSetServiceMock->expects($this->once())
			->method('addRootNode')
			->with($uid, $name)
			->willReturn(1);

		$result = $this->nodesService->addNode(0, $name);
		static::assertEquals(1, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddRootNodeFails(): void
	{
		$this->nodesRepositoryMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$uid = 123;
		$name = 'New Root Node';
		$this->userSessionStub->method('getUID')->willReturn($uid);

		$this->aclValidatorMock->expects($this->once())
			->method('isModuleAdmin')
			->with($uid)
			->willReturn(false);

		$this->nestedSetServiceMock->expects($this->never())
			->method('addRootNode');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('root_node_not_creatable');

		$result = $this->nodesService->addNode(0, $name);

		static::assertEquals(1, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddSubNode(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$uid = 123;
		$parentNodeId = 1;
		$name = 'New Sub Node';

		$this->userSessionStub->method('getUID')->willReturn($uid);

		$parentNode = ['node_id' => 1, 'name' => 'Parent Node', 'parent_id' => 1, 'children' => 0, 'UID' => 123, 'visibility' => 1];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($parentNodeId)
			->willReturn($parentNode);

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->nestedSetServiceMock->expects($this->once())
			->method('addSubNode')
			->with($uid, $name, $parentNode)
			->willReturn(2);

		$result = $this->nodesService->addNode($parentNodeId, $name);
		static::assertEquals(2, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddSubNodeFailsNopParentNode(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$uid = 123;
		$parentNodeId = 1;
		$name = 'New Sub Node';

		$this->userSessionStub->method('getUID')->willReturn($uid);

		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($parentNodeId)
			->willReturn([]);

		$this->aclValidatorMock->expects($this->never())
			->method('checkDirectoryPermissions');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('node_not_found');

		$this->nestedSetServiceMock->expects($this->never())
			->method('addSubNode');

		$this->nodesService->addNode($parentNodeId, $name);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddSubNodeFailsNoRights(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$uid = 123;
		$parentNodeId = 1;
		$name = 'New Sub Node';

		$this->userSessionStub->method('getUID')->willReturn($uid);

		$parentNode = ['node_id' => 1, 'name' => 'Parent Node', 'parent_id' => 1, 'children' => 0, 'UID' => 123, 'visibility' => 1];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($parentNodeId)
			->willReturn($parentNode);

		$rights = ['create' => false, 'read' => false, 'edit' => false, 'share' => ''];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->nestedSetServiceMock->expects($this->never())
			->method('addSubNode');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_creatable {"node_name":"Parent Node"}');

		$result = $this->nodesService->addNode($parentNodeId, $name);
		static::assertEquals(2, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditNodeSucceed(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$name = 'Updated Node Name';
		$this->userSessionStub->method('getUID')->willReturn(12);
		$visibility = 1;
		$node = ['node_id' => 1, 'name' => 'Node to Edit', 'parent_id' => 0, 'children' => 0, 'UID' => 123, 'visibility' => 0];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->aclValidatorMock->expects($this->exactly(2))
			->method('isModuleAdmin')
			->willReturn(true);

		$this->nodesRepositoryMock->expects($this->once())
			->method('update')
			->with($nodeId, ['name' => $name, 'visibility' => $visibility])
			->willReturn(1);

		$result = $this->nodesService->editNode($nodeId, $name, $visibility);
		static::assertEquals(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddUserDirectory(): void
	{
		$this->expectUntouched($this->nodesRepositoryMock, $this->aclValidatorMock, $this->mediaServiceMock);
		$UID = 1234;
		$name = 'User Directory';
		$expectedNodeId = 5678;

		$this->nestedSetServiceMock->expects($this->once())->method('addRootNode')
			->with($UID, $name, true)
			->willReturn($expectedNodeId);

		$result = $this->nodesService->addUserDirectory($UID, $name);

		static::assertEquals($expectedNodeId, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserDirectorySuccess(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;
		$this->userSessionStub->method('getUID')->willReturn($UID);
		$this->nodesRepositoryMock->expects($this->once())->method('getUserRootNode')
			->with($UID)
			->willReturn(['UID' => $UID, 'root_id' => 456, 'node_id' => 789]);

		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($UID)
			->willReturn(true);

		$condition = ['root_id' => ['test' => 2]];
		$this->nodesRepositoryMock->expects($this->once())->method('generateWhereClause')
			->with(456)
			->willReturn(['test' => 2]);

		$this->nodesRepositoryMock->expects($this->once())->method('findAllBy')
			->with ($condition)
			->willReturn([['node_id' => 1], ['node_id' => 2]]);

		$this->mediaServiceMock->expects($this->exactly(2))
			->method('markDeleteMediaByNodeId')
			->willReturnMap([[1], [2]]);

		$this->nodesRepositoryMock->expects($this->once())->method('deleteBy')
			->with($condition)
			->willReturn(1);

		$this->nodesService->deleteUserDirectory($UID);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserDirectoryNoActionWhenNotExist(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;
		$this->nodesRepositoryMock->expects($this->once())->method('getUserRootNode')
			->with($UID)
			->willReturn([]);

		$this->aclValidatorMock->expects($this->never())->method('isModuleAdmin');
		$this->nodesRepositoryMock->expects($this->never())->method('generateWhereClause');

		$this->nodesRepositoryMock->expects($this->never())
			->method('deleteBy');

		$this->nodesService->deleteUserDirectory($UID);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserDirectoryFailsByNoRights(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;
		$this->userSessionStub->method('getUID')->willReturn($UID);
		$this->nodesRepositoryMock->expects($this->once())->method('getUserRootNode')
			->with($UID)
			->willReturn(['UID' => $UID, 'root_id' => 456, 'node_id' => 789]);

		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($UID)
			->willReturn(false);

		$this->nodesRepositoryMock->expects($this->never())->method('generateWhereClause');
		$this->nodesRepositoryMock->expects($this->never())->method('findAllBy');
		$this->mediaServiceMock->expects($this->never())->method('markDeleteMediaByNodeId');
		$this->nodesRepositoryMock->expects($this->never())->method('deleteBy');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('user_directory_not_deletable');

		$this->nodesService->deleteUserDirectory($UID);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteUserDirectoryFailsByDeleteFailure(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$UID = 123;
		$this->userSessionStub->method('getUID')->willReturn($UID);
		$this->nodesRepositoryMock->expects($this->once())->method('getUserRootNode')
			->with($UID)
			->willReturn(['UID' => $UID, 'root_id' => 456, 'node_id' => 789]);

		$this->aclValidatorMock->expects($this->once())->method('isModuleAdmin')
			->with($UID)
			->willReturn(true);

		$condition = ['root_id' => ['test' => 2]];
		$this->nodesRepositoryMock->expects($this->once())
			->method('generateWhereClause')
			->with(456)
			->willReturn(['test' => 2]);

		$this->nodesRepositoryMock->expects($this->once())->method('findAllBy')
			->with($condition)
			->willReturn([
				['node_id' => 1],
				['node_id' => 2]
			]);

		$this->mediaServiceMock->expects($this->exactly(2))->method('markDeleteMediaByNodeId')
			->willReturnMap([[1], [2]]);

		$this->nodesRepositoryMock->expects($this->once())->method('deleteBy')
			->with($condition)
			->willReturn(0);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Delete nodes by root id failed.');


		$this->nodesService->deleteUserDirectory($UID);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditNodeFailsByNode(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$name = 'Updated Node Name';
		$this->userSessionStub->method('getUID')->willReturn(12);
		$visibility = 1;
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn([]);

		$this->aclValidatorMock->expects($this->never())
			->method('checkDirectoryPermissions');

		$this->aclValidatorMock->expects($this->never())
			->method('isModuleAdmin');

		$this->nodesRepositoryMock->expects($this->never())
			->method('update');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('node_not_found');

		$this->nodesService->editNode($nodeId, $name, $visibility);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testEditNodeFailsByRights(): void
	{
		$this->nestedSetServiceMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$name = 'Updated Node Name';
		$this->userSessionStub->method('getUID')->willReturn(12);
		$visibility = 1;
		$node = ['node_id' => 1, 'parent_id' => 1, 'name' => 'Node to Edit', 'children' => 0, 'UID' => 123, 'visibility' => 0];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$rights = ['create' => false, 'read' => true, 'edit' => false, 'share' => ''];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->aclValidatorMock->expects($this->never())
			->method('isModuleAdmin');

		$this->nodesRepositoryMock->expects($this->never())
			->method('update');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_editable {"node_name":"Node to Edit"}');

		$this->nodesService->editNode($nodeId, $name, $visibility);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeSucceed(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$this->userSessionStub->method('getUID')->willReturn(123);
		// the user may take the moved directory away and create in the target (his own user folder)
		$this->aclValidatorMock->expects($this->exactly(2))->method('checkDirectoryPermissions')
			->willReturn(['create' => true, 'read' => true, 'edit' => true, 'share' => 'global']);
		$movedNodeId = 1;
		$targetNodeId = 2;
		$region = Calculator::REGION_APPENDCHILD;
		$movedNode = ['node_id' => 1, 'name' => 'Moved Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 2];
		$targetNode = ['node_id' => 2, 'name' => 'Target Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 0];

		$this->nodesRepositoryMock->expects($this->exactly(2))
			->method('getNode')
			->willReturnOnConsecutiveCalls($movedNode, $targetNode);

		$this->nestedSetServiceMock->expects($this->once())
			->method('moveNode')
			->with($movedNode, $targetNode, $region);

		$result = $this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
		static::assertEquals(1, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsWithoutRightsOnTheMovedNode(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$this->userSessionStub->method('getUID')->willReturn(456);
		$movedNode  = ['node_id' => 1, 'name' => 'Foreign Node', 'children' => 0, 'UID' => 123, 'visibility' => 0, 'parent_id' => 2];
		$targetNode = ['node_id' => 3, 'name' => 'Own Node', 'children' => 0, 'UID' => 456, 'visibility' => 0, 'parent_id' => 4];

		$this->nodesRepositoryMock->expects($this->exactly(2))->method('getNode')->willReturnOnConsecutiveCalls($movedNode, $targetNode);
		$this->aclValidatorMock->expects($this->once())->method('checkDirectoryPermissions')
			->willReturn(['create' => false, 'read' => false, 'edit' => false, 'share' => '']);
		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_editable');
		$this->nodesService->moveNode(1, 3, Calculator::REGION_APPENDCHILD);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsWithoutCreateRightInTheTarget(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$this->userSessionStub->method('getUID')->willReturn(456);
		$movedNode  = ['node_id' => 1, 'name' => 'Own Node', 'children' => 0, 'UID' => 456, 'visibility' => 0, 'parent_id' => 2];
		$targetNode = ['node_id' => 3, 'name' => 'Foreign Node', 'children' => 0, 'UID' => 123, 'visibility' => 0, 'parent_id' => 4];

		$this->nodesRepositoryMock->expects($this->exactly(2))->method('getNode')->willReturnOnConsecutiveCalls($movedNode, $targetNode);
		$this->aclValidatorMock->expects($this->exactly(2))->method('checkDirectoryPermissions')->willReturnOnConsecutiveCalls(
			['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'],
			['create' => false, 'read' => true, 'edit' => false, 'share' => '']
		);
		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_creatable');
		$this->nodesService->moveNode(1, 3, Calculator::REGION_APPENDCHILD);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsByRegion(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$movedNodeId  = 1;
		$targetNodeId = 2;
		$region       = 'Bäm';

		$this->nodesRepositoryMock->expects($this->never())->method('getNode');
		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage($region.' is not supported');
		$this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsByMovedNodeRootDir(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$movedNodeId = 1;
		$targetNodeId = 2;
		$region = Calculator::REGION_APPENDCHILD;
		$movedNode = ['node_id' => 1, 'name' => 'Moved Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 0];

		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($movedNodeId)
			->willReturn($movedNode);

		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('root_node_not_movable');

		$this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsByTargetCreateRoot(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$movedNodeId = 1;
		$targetNodeId = 0;
		$region = Calculator::REGION_APPENDCHILD;
		$movedNode = ['node_id' => 1, 'name' => 'Moved Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 12];

		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($movedNodeId)
			->willReturn($movedNode);

		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('root_node_not_creatable_by_move');

		$this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsByTargetCreateRootWithBefore(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$movedNodeId = 1;
		$targetNodeId = 4;
		$region = Calculator::REGION_BEFORE;
		$movedNode = ['node_id' => 1, 'name' => 'Moved Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 12];
		$targetNode = ['node_id' => 2, 'name' => 'Target Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 0];

		$this->nodesRepositoryMock->expects($this->exactly(2))
			->method('getNode')
			->willReturnOnConsecutiveCalls($movedNode, $targetNode);

		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('root_node_not_creatable_by_move');

		$this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsByTargetCreateRootWithAfter(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$movedNodeId = 1;
		$targetNodeId = 4;
		$region = Calculator::REGION_AFTER;
		$movedNode = ['node_id' => 1, 'name' => 'Moved Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 12];
		$targetNode = ['node_id' => 2, 'name' => 'Target Node', 'children' => 0, 'UID' => 123, 'visibility' => 1, 'parent_id' => 0];

		$this->nodesRepositoryMock->expects($this->exactly(2))
			->method('getNode')
			->willReturnOnConsecutiveCalls($movedNode, $targetNode);

		$this->nestedSetServiceMock->expects($this->never())->method('moveNode');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('root_node_not_creatable_by_move');


		$this->nodesService->moveNode($movedNodeId, $targetNodeId, $region);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteOneNodeSucceed(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$this->userSessionStub->method('getUID')->willReturn(354);
		$node = ['node_id' => 1, 'name' => 'Node to Delete', 'parent_id' => 12, 'children' => 0,
			'UID' => 123, 'visibility' => 1, 'root_id' => 1, 'rgt' => 2, 'lft' => 1];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->nestedSetServiceMock->expects($this->once())
			->method('findAllSubNodeIdsByRootIdsAndPosition')
			->with($node['root_id'], $node['rgt'], $node['lft'])
			->willReturn([['hurz']]);


		$this->nestedSetServiceMock->expects($this->once())
			->method('deleteSingleNode')
			->with($node);

		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteTree');

		$result = $this->nodesService->deleteNode($nodeId);
		static::assertEquals(1, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteFailsByNode(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$this->userSessionStub->method('getUID')->willReturn(34);

		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn([]);

		$this->aclValidatorMock->expects($this->never())
			->method('checkDirectoryPermissions');
		$this->nestedSetServiceMock->expects($this->never())
			->method('findAllSubNodeIdsByRootIdsAndPosition');
		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteSingleNode');
		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteTree');

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('node_not_found');

		$this->nodesService->deleteNode($nodeId);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteFailsByRights(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$this->userSessionStub->method('getUID')->willReturn(34);
		$node = ['node_id' => 1, 'name' => 'Node to Delete', 'parent_id' => 12, 'children' => 0,
			'UID' => 123, 'visibility' => 1, 'root_id' => 1, 'rgt' => 2, 'lft' => 1];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$rights = ['create' => false, 'read' => true, 'edit' => false, 'share' => ''];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->nestedSetServiceMock->expects($this->never())
			->method('findAllSubNodeIdsByRootIdsAndPosition');
		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteSingleNode');
		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteTree');

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_deletable');

		$this->nodesService->deleteNode($nodeId);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteNodeTreeSucceed(): void
	{
		$this->mediaServiceMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$this->userSessionStub->method('getUID')->willReturn(354);
		$node = ['node_id' => 1, 'name' => 'Node to Delete', 'parent_id' => 12, 'children' => 3,
			'UID' => 123, 'visibility' => 1, 'root_id' => 1, 'rgt' => 2, 'lft' => 1];
		$this->nodesRepositoryMock->expects($this->once())
			->method('getNode')
			->with($nodeId)
			->willReturn($node);

		$rights = ['create' => true, 'read' => true, 'edit' => true, 'share' => 'global'];
		$this->aclValidatorMock->expects($this->once())
			->method('checkDirectoryPermissions')
			->willReturn($rights);

		$this->nestedSetServiceMock->expects($this->once())
			->method('findAllSubNodeIdsByRootIdsAndPosition')
			->with($node['root_id'], $node['rgt'], $node['lft'])
			->willReturn([['heidewitzka'], ['der'], ['Kapitän']]);

		$this->nestedSetServiceMock->expects($this->never())
			->method('deleteSingleNode');

		$this->nestedSetServiceMock->expects($this->once())
			->method('deleteTree')
			->with($node);

		$result = $this->nodesService->deleteNode($nodeId);
		static::assertEquals(3, $result);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
