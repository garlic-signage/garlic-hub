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


namespace Tests\Unit\Framework\Database\NestedSets;

use App\Framework\Database\NestedSets\Repository;
use App\Framework\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
	private Connection&MockObject $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private Repository $repository;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock   = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock       = $this->createMock(Result::class);

		$this->repository       = new Repository($this->connectionMock, 'table', 'id');
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllRootNodes(): void
	{
		$expectedResult = [
			[
				'media_id' => 1,
				'parent_id' => 0,
				'username' => 'testuser',
				'children' => 2
			],
			[
				'media_id' => 2,
				'parent_id' => 0,
				'username' => 'another_user',
				'children' => 0
			]
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('*, FLOOR((rgt-lft)/2) AS children')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('table', 'user_main', 'user_main', 'table.UID = user_main.UID')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('parent_id = 0')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('orderBy')
			->with('root_order', 'ASC')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findAllRootNodes();
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindTreeByRootId(): void
	{
		$rootId = 1;
		$expectedResult = [
			['children' => 2, 'node_id' => 1, 'name' => 'Root Node', 'company_id' => 1],
			['children' => 0, 'node_id' => 2, 'name' => 'Child Node 1', 'company_id' => 1],
			['children' => 0, 'node_id' => 3, 'name' => 'Child Node 2', 'company_id' => 2],
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		// ... (Mock query builder methods for select, from, leftJoin, where, setParameter, groupBy)

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findTreeByRootId($rootId);
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|DatabaseException
	 */
	#[Group('units')]
	public function testFindNodeOwner(): void
	{
		$nodeId = 2;
		$expectedResult = ['UID' => 1, 'node_id' => 2, 'name' => 'Child Node 1', 'company_id' => 1];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		// ... (Mock query builder methods)

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findNodeOwner($nodeId);
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindNodeOwnerNotFound(): void
	{
		$nodeId = 999; // Non-existent node ID

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		// ... (Mock query builder methods)

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAssociative')
			->willReturn(false); // Simulate no result

		$this->expectException(DatabaseException::class);
		$this->expectExceptionMessage('Node not found');

		$this->repository->findNodeOwner($nodeId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllChildNodesByParentNode(): void
	{
		$parentId = 1;
		$expectedResult = [
			['node_id' => 2, 'name' => 'Child Node 1', 'children' => 0],
			['node_id' => 3, 'name' => 'Child Node 2', 'children' => 0],
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		// ... (Mock query builder methods)

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findAllChildNodesByParentNode($parentId);
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|Exception
	 */
	#[Group('units')]
	public function testFindAllChildrenInTreeOfNodeId(): void
	{
		$nodeId = 2;
		$nodeData = [['root_id' => 1, 'rgt' => 6, 'lft' => 3]];
		$expectedResult = [
			['node_id' => 2, 'category_name' => 'Child Node 1'],
			['node_id' => 3, 'category_name' => 'Child Node 2'],
		];

		$queryBuilderMock2 = $this->createMock(QueryBuilder::class);

		$this->connectionMock->expects($this->exactly(2))->method('createQueryBuilder')
			->willReturnOnConsecutiveCalls($this->queryBuilderMock, $queryBuilderMock2);

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('root_id, rgt, lft')
			->willReturnSelf();
		// ... other query builder method calls
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($nodeData);


		// Second query (find children)
		$queryBuilderMock2->expects($this->once())->method('select')
			->with('node_id, category_name')
			->willReturnSelf();

		$resultMock2 = $this->createMock(Result::class);

		$queryBuilderMock2->expects($this->once())->method('executeQuery')
			->willReturn($resultMock2);
		$resultMock2->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findAllChildrenInTreeOfNodeId($nodeId);
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllChildrenInTreeOfNodeIdFails(): void
	{
		$nodeId = 2;


		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('root_id, rgt, lft')
			->willReturnSelf();
		// ... other query builder method calls
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn([]);


		// The second query (find children) will not be reached
		$queryBuilderMock2 = $this->createMock(QueryBuilder::class);
		$queryBuilderMock2->expects($this->never())->method('select');


		static::assertEmpty($this->repository->findAllChildrenInTreeOfNodeId($nodeId));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindRootIdRgtAndLevelByNodeId(): void
	{
		$nodeId = 2;
		$expectedResult = ['root_id' => 1, 'rgt' => 6, 'lft' => 3];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('root_id, rgt, lft')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())
			->method('where')
			->with('node_id = :node_id')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('node_id', $nodeId)
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findRootIdRgtAndLevelByNodeId($nodeId);
		static::assertEquals($expectedResult, $actualResult);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllSubNodeIdsByRootIdsAndPosition(): void
	{
		$rootId = 1;
		$nodeRgt = 6;
		$nodeLft = 3;
		$expectedResult = [
			['node_id' => 2],
			['node_id' => 3],
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('node_id')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->exactly(2))->method('andWhere')
			->willReturnCallback(function ($condition) {
				$expectedConditions = ['lft >= :node_lft', 'rgt <= :node_rgt'];
				static::assertContains($condition, $expectedConditions);
				return $this->queryBuilderMock;
			});

		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnCallback(function ($name, $value) {
				$expectedNames = ['root_id', 'node_lft', 'node_rgt'];
				$expectedValues = [1, 6, 3];
				static::assertContains($name, $expectedNames);
				static::assertContains($value, $expectedValues);
				return $this->queryBuilderMock;
			});


		$this->queryBuilderMock->expects($this->once())->method('executeQuery')
			->willReturn($this->resultMock);

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')
			->willReturn($expectedResult);

		$actualResult = $this->repository->findAllSubNodeIdsByRootIdsAndPosition($rootId, $nodeRgt, $nodeLft);
		static::assertEquals($expectedResult, $actualResult);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveSubTree(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$movedNode  = ['node_id' => 2, 'lft' => 1, 'rgt' => 2, 'root_id' => 1, 'parent_id' => 1, 'level' => 1];
		$targetNode = ['node_id' => 3, 'lft' => 1, 'rgt' => 2, 'root_id' => 2, 'parent_id' => 2, 'level' => 1];
		$diffLevel = 0;

		// calculated values
		$calculated = ['distance' => 0, 'tmpPos' => 1, 'width' => 1];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with('table')
			->willReturnSelf();

		$this->queryBuilderMock->expects($this->exactly(4))->method('set')
			->willReturnMap([
				['lft', 'lft + :distance', $this->queryBuilderMock],
				['rgt', 'rgt + :distance', $this->queryBuilderMock],
				['level', 'level + :diff_level', $this->queryBuilderMock],
				['root_id', ':target_root_id', $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :moved_root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(2))->method('andWhere')
			->willReturnMap([
				['lft >= :tmpPos', $this->queryBuilderMock],
				['rgt < :tmpPos + :width', $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->exactly(6))->method('setParameter')
			->willReturnMap([
				['distance', $calculated['distance'], $this->queryBuilderMock],
				['diff_level', $diffLevel, $this->queryBuilderMock],
				['target_root_id', $targetNode['root_id'], $this->queryBuilderMock],
				['moved_root_id', $movedNode['root_id'], $this->queryBuilderMock],
				['tmpPos', $calculated['tmpPos'], $this->queryBuilderMock],
				['width', $calculated['width'], $this->queryBuilderMock]
			]);

		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);


		static::assertSame(1, $this->repository->moveSubTree($movedNode, $targetNode, $calculated, $diffLevel));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodesToRightForInsert(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$rootId = 2;
		$width = 6;
		$position = 3;

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with('table')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('set')
			->with('lft', 'lft + :steps')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('lft >= :position')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
				['steps', $width, $this->queryBuilderMock],
				['root_id', $rootId, $this->queryBuilderMock],
				['position', $position, $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);

		static::assertSame(1, $this->repository->moveNodesToRightForInsert($rootId, $position, $width));

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodesToLeftForInsert(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$rootId = 2;
		$width = 6;
		$position = 3;
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with('table')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('set')
			->with('rgt', 'rgt + :steps')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('rgt >= :position')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
				['steps', $width, $this->queryBuilderMock],
				['root_id', $rootId, $this->queryBuilderMock],
				['position', $position, $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);

		static::assertSame(1, $this->repository->moveNodesToLeftForInsert($rootId, $position, $width));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodesToLeftForDeletion(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$rootId = 2;
		$width = 6;
		$position = 3;
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with('table')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('set')
			->with('lft', 'lft - '.$width)
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('lft > :position')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(2))->method('setParameter')
			->willReturnMap([
				['root_id', $rootId, $this->queryBuilderMock],
				['position', $position, $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);

		static::assertSame(1, $this->repository->moveNodesToLeftForDeletion($rootId, $position, $width));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testMoveNodesToRightForDeletionMock(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$rootId = 1;
		$width  = 6;
		$position = 3;
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')
			->with('table')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('set')
			->with('rgt', 'rgt - '.$width)
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('rgt > :position')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(2))->method('setParameter')
			->willReturnMap([
				['root_id', $rootId, $this->queryBuilderMock],
				['position', $position, $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);

		static::assertSame(1, $this->repository->moveNodesToRightForDeletion($rootId, $position, $width));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testDeleteFullTree(): void
	{
		$this->resultMock->expects($this->never())->method($this->anything());
		$node = ['root_id' => 1, 'rgt' => 6, 'lft' => 3];
		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('delete')
			->with('table')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('where')
			->with('root_id = :root_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('lft between :pos_lft AND :pos_rgt')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
				['root_id', $node['root_id'], $this->queryBuilderMock],
				['pos_lft', $node['lft'], $this->queryBuilderMock],
				['pos_rgt', $node['rgt'], $this->queryBuilderMock]
			]);
		$this->queryBuilderMock->expects($this->once())->method('executeStatement')->willReturn(1);

		static::assertSame(1, $this->repository->deleteFullTree($node['root_id'], $node['rgt'], $node['lft']));
	}


}
