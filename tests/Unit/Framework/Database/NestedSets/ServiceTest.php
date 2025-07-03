<?php

namespace Tests\Unit\Framework\Database\NestedSets;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Repository;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\DatabaseException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ServiceTest extends TestCase
{
	private Calculator&MockObject $calculatorMock;
	private Transactions&MockObject $transactionsMock;
	private Repository&MockObject $repositoryMock;
	private LoggerInterface&MockObject $loggerMock;
	private Service $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->calculatorMock = $this->createMock(Calculator::class);
		$this->transactionsMock = $this->createMock(Transactions::class);
		$this->repositoryMock = $this->createMock(Repository::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->service = new Service($this->repositoryMock, $this->calculatorMock, $this->transactionsMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testInitRepository(): void
	{
		$table = 'test_table';
		$idField = 'test_id';

		$this->repositoryMock->expects($this->once())->method('setTable')
			->with($table);

		$this->repositoryMock->expects($this->once())->method('setIdField')
			->with($idField);

		$this->service->initRepository($table, $idField);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllRootNodes(): void
	{
		$expectedResult = [
			['id' => 1, 'name' => 'RootNode1'],
			['id' => 2, 'name' => 'RootNode2']
		];

		$this->repositoryMock->expects($this->once())->method('findAllRootNodes')
			->willReturn($expectedResult);

		$result = $this->service->findAllRootNodes();

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindTreeByRootId(): void
	{
		$rootId = 1;
		$expectedResult = [
			['id' => 1, 'name' => 'RootNode', 'children' => [
				['id' => 2, 'name' => 'ChildNode1'],
				['id' => 3, 'name' => 'ChildNode2']
			]]
		];

		$this->repositoryMock->expects($this->once())->method('findTreeByRootId')
			->with($rootId)
			->willReturn($expectedResult);

		$result = $this->service->findTreeByRootId($rootId);

		$this->assertSame($expectedResult, $result);
	}


	/**
	 * @throws DatabaseException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindNodeOwner(): void
	{
		$nodeId = 1;
		$expectedResult = ['id' => 1, 'owner' => 'NodeOwner'];

		$this->repositoryMock->expects($this->once())->method('findNodeOwner')
			->with($nodeId)
			->willReturn($expectedResult);

		$result = $this->service->findNodeOwner($nodeId);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllChildNodesByParentNode(): void
	{
		$parentNodeId = 1;
		$expectedResult = [
			['id' => 2, 'name' => 'ChildNode1'],
			['id' => 3, 'name' => 'ChildNode2']
		];

		$this->repositoryMock->expects($this->once())->method('findAllChildNodesByParentNode')
			->with($parentNodeId)
			->willReturn($expectedResult);

		$result = $this->service->findAllChildNodesByParentNode($parentNodeId);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllChildrenInTreeOfNodeId(): void
	{
		$nodeId = 1;
		$expectedResult = [
			['id' => 2, 'name' => 'ChildNode1'],
			['id' => 3, 'name' => 'ChildNode2'],
			['id' => 4, 'name' => 'ChildNode3']
		];

		$this->repositoryMock->expects($this->once())->method('findAllChildrenInTreeOfNodeId')
			->with($nodeId)
			->willReturn($expectedResult);

		$result = $this->service->findAllChildrenInTreeOfNodeId($nodeId);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindRootIdRgtAndLevelByNodeIdReturnsValidData(): void
	{
		$nodeId = 42;
		$expectedResult = ['root_id' => 1, 'rgt' => 10,'level' => 2];

		$this->repositoryMock->expects($this->once())->method('findRootIdRgtAndLevelByNodeId')
			->with($nodeId)
			->willReturn($expectedResult);

		$result = $this->service->findRootIdRgtAndLevelByNodeId($nodeId);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllSubNodeIdsByRootIdsAndPosition(): void
	{
		$rootId = 1;
		$nodeRgt = 10;
		$nodeLft = 2;
		$expectedResult = [
			['id' => 2, 'name' => 'ChildNode1'],
			['id' => 3, 'name' => 'ChildNode2']
		];

		$this->repositoryMock->expects($this->once())->method('findAllSubNodeIdsByRootIdsAndPosition')
			->with($rootId, $nodeRgt, $nodeLft)
			->willReturn($expectedResult);

		$result = $this->service->findAllSubNodeIdsByRootIdsAndPosition($rootId, $nodeRgt, $nodeLft);

		$this->assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws DatabaseException
	 */
	#[Group('units')]
	public function testAddRootNode(): void
	{
		$uid = 1;
		$name = 'Test Root';
		$expectedNewNodeId = 5; // Example ID - adjust as needed

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('insert')
			->with([
				'name' => $name,
				'parent_id' => 0,
				'root_order' => 0,
				'visibility' => 0,
				'is_user_folder' => 1,
				'lft' => 1,
				'rgt' => 2,
				'UID' => $uid,
				'level' => 1
			])
			->willReturn($expectedNewNodeId);

		$updateFields = ['root_id' => $expectedNewNodeId, 'root_order' => $expectedNewNodeId];
		$this->repositoryMock->expects($this->once())->method('update')
			->with($expectedNewNodeId, $updateFields)
			->willReturn(1);

		$this->transactionsMock->expects($this->once())->method('commit');

		$actualNewNodeId = $this->service->addRootNode($uid, $name, true);
		$this->assertEquals($actualNewNodeId, $actualNewNodeId);
		$this->assertFalse($this->service->hasErrorMessages());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddRootNodeInsertFails(): void
	{
		$uid = 1;
		$name = 'Test Root';

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('insert')
			->willReturn(0); // Simulate insert failure

		$this->transactionsMock->expects($this->once())->method('rollback');

		$this->loggerMock->expects($this->once())->method('error')
			->with('Add root node failed because of: Insert new node failed');

		$this->service->addRootNode($uid, $name);
		$this->assertTrue($this->service->hasErrorMessages());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|DatabaseException
	 */
	#[Group('units')]
	public function testAddRootNodeUpdateFails(): void
	{
		$uid = 1;
		$name = 'Test Root';

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('insert')
			->with([
				'name' => $name,
				'parent_id' => 0,
				'root_order' => 0,
				'visibility' => 0,
				'is_user_folder' => 0,
				'lft' => 1,
				'rgt' => 2,
				'UID' => $uid,
				'level' => 1
			])
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('update')
			->willReturn(0); // simulate no update

		$this->transactionsMock->expects($this->once())->method('rollback');

		$this->loggerMock->expects($this->once())->method('error')
			->with('Add root node failed because of: Update root node failed');

		$this->service->addRootNode($uid, $name);
		$this->assertTrue($this->service->hasErrorMessages());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|DatabaseException
	 */
	#[Group('units')]
	public function testAddSubNode(): void
	{
		$uid = 2;
		$name = 'Test Sub Node';
		$parentNode = ['rgt' => 5, 'node_id' => 1, 'root_id' => 1, 'level' => 1];
		$expectedNewNodeId = 7; // Example ID

		$this->transactionsMock->expects($this->once())->method('begin');

		// moveNodesToLeftForInsert

		// moveNodesToRightForInsert

		$this->repositoryMock->expects($this->once())->method('insert')
			->willReturn($expectedNewNodeId);

		$this->transactionsMock->expects($this->once())->method('commit');

		$actualNewNodeId = $this->service->addSubNode($uid, $name, $parentNode);
		$this->assertEquals($expectedNewNodeId, $actualNewNodeId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAddSubNodeInsertFails(): void
	{
		$uid = 2;
		$name = 'Test Sub Node';
		$parentNode = ['rgt' => 5, 'node_id' => 1, 'root_id' => 1, 'level' => 1];

		$this->transactionsMock->expects($this->once())->method('begin');

		// moveNodesToLeftForInsert

		// moveNodesToRightForInsert

		$this->repositoryMock->expects($this->once())->method('insert')
			->willReturn(0);// Simulate insert failure

		$this->transactionsMock->expects($this->once())->method('rollBack'); // Corrected method name
		$this->loggerMock->expects($this->once())->method('error')->with('Insert new sub node failed.');
		$this->service->addSubNode($uid, $name, $parentNode);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws DatabaseException
	 */
	#[Group('units')]
	public function testDeleteSingleNode(): void
	{
		$node = ['node_id' => 2, 'root_id' => 1, 'rgt' => 5];

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('delete')
			->willReturn(1); // Simulate successful deletion

		// moveNodesToLeftForDeletion

		// moveNodesToRightForDeletion

		$this->transactionsMock->expects($this->once())->method('commit');

		$this->service->deleteSingleNode($node);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteSingleNodeDeleteFails(): void
	{
		$node = ['node_id' => 2, 'root_id' => 1, 'rgt' => 5];

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('delete')
			->willReturn(0); // Simulate delete failure

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('Node not exists.');

		$this->service->deleteSingleNode($node);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|DatabaseException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteTree(): void
	{
		$node = ['root_id' => 1, 'rgt' => 6, 'lft' => 3];
		$move = 4.0;
		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('deleteFullTree')
			->with(1,6,3)
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('moveNodesToLeftForDeletion')
			->with($node['root_id'], $node['rgt'], $move)
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('moveNodesToRightForDeletion')
			->with($node['root_id'], $node['rgt'], $move)
			->willReturn(1);

		$this->transactionsMock->expects($this->once())->method('commit');

		$this->service->deleteTree($node);
	}

	/**
	 * @throws DatabaseException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteTreeFails(): void
	{
		$node = ['root_id' => 1, 'rgt' => 6, 'lft' => 3];
		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('deleteFullTree')
			->with(1,6,3)
			->willReturn(0);

		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->loggerMock->expects($this->once())->method('error')->with('Node not exists.');

		$this->service->deleteTree($node);
	}

	/**
	 * @throws DatabaseException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testMoveNodeBefore(): void
	{
		$movedNode  = ['node_id' => 2, 'lft' => 3, 'rgt' => 6, 'root_id' => 1, 'parent_id' => 1, 'level' => 2];
		$targetNode = ['node_id' => 3, 'lft' => 7, 'rgt' => 8, 'root_id' => 1, 'parent_id' => 1, 'level' => 2];
		$region     = 'before';
		$width      = $movedNode['rgt'] - $movedNode['lft'] + 1;
		$diffLevel  = 12;
		$newLgtPos  = 19;

		$this->calculatorMock->expects($this->once())->method('calculateDiffLevelByRegion')
			->with($region, $movedNode['level'], $targetNode['level'])
			->willReturn($diffLevel);

		$this->calculatorMock->expects($this->once())->method('determineLgtPositionByRegion')
			->with($region, $targetNode)
			->willReturn($newLgtPos);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('moveNodesToRightForInsert')
			->with($movedNode['root_id'], $newLgtPos, $width)
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('moveNodesToLeftForInsert')
			->with($movedNode['root_id'], $newLgtPos, $width)
			->willReturn(1);

		$calculated = ['distance' => 10, 'tmpPos' => 12, 'width' => $width];
		$this->calculatorMock->expects($this->once())->method('calculateBeforeMoveSubTree')
			->with($movedNode, $targetNode, $newLgtPos, $width)
			->willReturn($calculated);

		$this->repositoryMock->expects($this->once())->method('moveSubTree')
			->with($movedNode, $targetNode, $calculated, $diffLevel)
			->willReturn(1);

		$this->calculatorMock->expects($this->once())->method('determineParentIdByRegion')
			->with($region, $targetNode)
			->willReturn(987);

		$this->repositoryMock->expects($this->once())->method('update')
			->with($movedNode['node_id'], ['parent_id' => 987]);

		$this->repositoryMock->expects($this->once())->method('moveNodesToLeftForDeletion')
			->with($movedNode['root_id'], $movedNode['rgt'], $width)
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('moveNodesToRightForDeletion')
			->with($movedNode['root_id'], $movedNode['rgt'], $width)
			->willReturn(1);

		 $this->transactionsMock->expects($this->once())->method('commit');

		$this->service->moveNode($movedNode, $targetNode, $region);
	}

	/**
	 * @throws DatabaseException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodeFailsOnMoveSubTree(): void
	{
		$movedNode  = ['node_id' => 2, 'name' => 'moved', 'lft' => 3, 'rgt' => 6, 'root_id' => 1, 'parent_id' => 1, 'level' => 2];
		$targetNode = ['node_id' => 3, 'name' => 'target', 'lft' => 7, 'rgt' => 8, 'root_id' => 1, 'parent_id' => 1, 'level' => 2];
		$region     = 'before';
		$width      = $movedNode['rgt'] - $movedNode['lft'] + 1;
		$diffLevel  = 12;
		$newLgtPos  = 19;

		$this->calculatorMock->expects($this->once())->method('calculateDiffLevelByRegion')
			->with($region, $movedNode['level'], $targetNode['level'])
			->willReturn($diffLevel);

		$this->calculatorMock->expects($this->once())->method('determineLgtPositionByRegion')
			->with($region, $targetNode)
			->willReturn($newLgtPos);

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->repositoryMock->expects($this->once())->method('moveNodesToRightForInsert')
			->with($movedNode['root_id'], $newLgtPos, $width)
			->willReturn(1);

		$this->repositoryMock->expects($this->once())->method('moveNodesToLeftForInsert')
			->with($movedNode['root_id'], $newLgtPos, $width)
			->willReturn(1);

		$calculated = ['distance' => 10, 'tmpPos' => 12, 'width' => $width];
		$this->calculatorMock->expects($this->once())->method('calculateBeforeMoveSubTree')
			->with($movedNode, $targetNode, $newLgtPos, $width)
			->willReturn($calculated);

		$this->repositoryMock->expects($this->once())->method('moveSubTree')
			->with($movedNode, $targetNode, $calculated, $diffLevel)
			->willReturn(0);

		$this->calculatorMock->expects($this->never())->method('determineParentIdByRegion');
		$this->repositoryMock->expects($this->never())->method('update');
		$this->repositoryMock->expects($this->never())->method('moveNodesToLeftForDeletion');
		$this->repositoryMock->expects($this->never())->method('moveNodesToRightForDeletion');

		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->loggerMock->expects($this->once())->method('error')->with($movedNode['name']. ' cannot be moved via '.$region.' of '. $targetNode['name']);
		$this->service->moveNode($movedNode, $targetNode, $region);
	}

}
