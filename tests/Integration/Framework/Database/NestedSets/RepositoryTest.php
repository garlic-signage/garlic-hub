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

namespace Tests\Integration\Framework\Database\NestedSets;

use App\Framework\Exceptions\DatabaseException;
use PHPUnit\Framework\Attributes\Group;

#[Group('db')]
class RepositoryTest extends NestedSetsTestCase
{
	/**
	 * @var array<string,int>
	 */
	private array $ids = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->ids = $this->buildTree();
	}

	public function testFindAllRootNodesIsSortedByRootOrder(): void
	{
		$roots = $this->repository->findAllRootNodes();

		static::assertSame([1, $this->ids['A']], array_map('intval', array_column($roots, 'node_id')));
		static::assertSame(['public', 'A'], array_column($roots, 'name'));
	}

	public function testFindAllRootNodesAddsTheOwnerAndTheChildren(): void
	{
		$roots = $this->repository->findAllRootNodes();

		static::assertSame('admin', $roots[0]['username']);
		static::assertSame('anna', $roots[1]['username']);
		// FLOOR((rgt - lft) / 2): the number of all nodes below
		static::assertSame(5, (int) $roots[0]['children']);
		static::assertSame(5, (int) $roots[1]['children']);
	}

	public function testFindAllChildNodesByParentNodeIsSortedByLeftValue(): void
	{
		$children = $this->repository->findAllChildNodesByParentNode($this->ids['A']);

		static::assertSame(['B', 'C', 'D'], array_column($children, 'name'));
		// B has two children, the others none
		static::assertSame([2, 0, 0], array_map('intval', array_column($children, 'children')));
		static::assertSame(['B1', 'B2'], array_column($this->repository->findAllChildNodesByParentNode($this->ids['B']), 'name'));
		static::assertSame([], $this->repository->findAllChildNodesByParentNode($this->ids['C']));
		static::assertSame([], $this->repository->findAllChildNodesByParentNode(999));
	}

	public function testFindNodeOwner(): void
	{
		$node = $this->repository->findNodeOwner($this->ids['B1']);

		static::assertSame(2, (int) $node['UID']);
		static::assertSame('B1', $node['name']);
		static::assertSame(1, (int) $node['company_id']);
	}

	public function testFindNodeOwnerOfAnUnknownNodeThrows(): void
	{
		$this->expectException(DatabaseException::class);
		$this->repository->findNodeOwner(999);
	}

	public function testFindRootIdRgtAndLevelByNodeId(): void
	{
		$data = $this->repository->findRootIdRgtAndLevelByNodeId($this->ids['B']);

		static::assertSame($this->ids['A'], (int) $data['root_id']);
		static::assertSame(2, (int) $data['lft']);
		static::assertSame(7, (int) $data['rgt']);
		static::assertSame([], $this->repository->findRootIdRgtAndLevelByNodeId(999));
	}

	public function testFindAllSubNodeIdsByRootIdsAndPosition(): void
	{
		$node = $this->node($this->ids['B']);

		$ids = $this->repository->findAllSubNodeIdsByRootIdsAndPosition((int) $node['root_id'], (int) $node['rgt'], (int) $node['lft']);

		static::assertEqualsCanonicalizing(
			[$this->ids['B'], $this->ids['B1'], $this->ids['B2']],
			array_map('intval', array_column($ids, 'node_id'))
		);
	}

	public function testFindAllSubNodeIdsOfALeafIsOnlyTheLeaf(): void
	{
		$node = $this->node($this->ids['C']);

		$ids = $this->repository->findAllSubNodeIdsByRootIdsAndPosition((int) $node['root_id'], (int) $node['rgt'], (int) $node['lft']);

		static::assertSame([$this->ids['C']], array_map('intval', array_column($ids, 'node_id')));
	}

	public function testDeleteFullTreeRemovesOnlyTheGivenPartOfTheTree(): void
	{
		$node = $this->node($this->ids['B']);

		static::assertSame(3, $this->repository->deleteFullTree((int) $node['root_id'], (int) $node['rgt'], (int) $node['lft']));

		// the numbers are not closed here, this is the job of the service
		static::assertSame(['A', 'C', 'D'], $this->connection->fetchFirstColumn('SELECT name FROM mediapool_nodes WHERE root_id = ? ORDER BY lft', [$this->ids['A']]));
		static::assertSame(6, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE root_id = 1'));
	}

	public function testTheServicePassesTheFindersOnToTheRepository(): void
	{
		$node = $this->node($this->ids['B']);

		static::assertSame($this->repository->findAllRootNodes(), $this->service->findAllRootNodes());
		static::assertSame($this->repository->findNodeOwner($this->ids['B1']), $this->service->findNodeOwner($this->ids['B1']));
		static::assertSame(
			$this->repository->findAllChildNodesByParentNode($this->ids['A']),
			$this->service->findAllChildNodesByParentNode($this->ids['A'])
		);
		static::assertSame(
			$this->repository->findRootIdRgtAndLevelByNodeId($this->ids['B']),
			$this->service->findRootIdRgtAndLevelByNodeId($this->ids['B'])
		);
		static::assertSame(
			$this->repository->findAllSubNodeIdsByRootIdsAndPosition((int) $node['root_id'], (int) $node['rgt'], (int) $node['lft']),
			$this->service->findAllSubNodeIdsByRootIdsAndPosition((int) $node['root_id'], (int) $node['rgt'], (int) $node['lft'])
		);
	}

	public function testTheServiceCanWorkOnAnotherTable(): void
	{
		$this->service->initRepository('templates', 'template_id');

		static::assertSame('templates', $this->repository->getTable());
		static::assertSame('template_id', $this->repository->getIdField());
	}

	/**
	 * findTreeByRootId() joins a table user_name, findAllChildrenInTreeOfNodeId() selects a column
	 * category_name and has an unbalanced bracket in its where clause. Nothing calls them, so
	 * nobody noticed. The tests say what is broken until the methods are repaired or removed.
	 */
	public function testFindTreeByRootIdIsBroken(): void
	{
		static::markTestSkipped('Known bug: findTreeByRootId() joins the table "user_name", which does not exist (user_main). Not used anywhere.');
	}

	public function testFindAllChildrenInTreeOfNodeIdIsBroken(): void
	{
		static::markTestSkipped('Known bug: findAllChildrenInTreeOfNodeId() has an unbalanced bracket and selects "category_name", which does not exist. Not used anywhere.');
	}
}
