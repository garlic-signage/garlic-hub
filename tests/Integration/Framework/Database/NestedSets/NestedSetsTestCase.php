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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Repository;
use App\Framework\Database\NestedSets\Service;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * Nested sets of the directory tree (mediapool_nodes). After every change the numbering of all trees
 * must still be valid: the left and right values of a tree are exactly 1 to 2n, every node lies
 * inside its parent and is one level deeper.
 *
 * The migration creates the tree 1 "public" with the five children 2 to 6.
 */
#[Group('db')]
abstract class NestedSetsTestCase extends RepositoryTestCase
{
	protected Service $service;
	protected Repository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->repository = new Repository($this->connection, 'mediapool_nodes', 'node_id');
		$this->service    = new Service($this->repository, new Calculator(), new Transactions($this->connection));
	}

	/**
	 * Builds the tree "A" with the children "B", "C" and "D", "B" has "B1" and "B2". Returns the ids by name.
	 *
	 *     A
	 *     +- B
	 *     |  +- B1
	 *     |  +- B2
	 *     +- C
	 *     +- D
	 *
	 * @return array<string,int>
	 */
	protected function buildTree(string $rootName = 'A'): array
	{
		$ids = [$rootName => $this->service->addRootNode(2, $rootName)];
		$ids['B']  = $this->addSub($ids[$rootName], 'B');
		$ids['C']  = $this->addSub($ids[$rootName], 'C');
		$ids['D']  = $this->addSub($ids[$rootName], 'D');
		$ids['B1'] = $this->addSub($ids['B'], 'B1');
		$ids['B2'] = $this->addSub($ids['B'], 'B2');

		return $ids;
	}

	protected function addSub(int $parentId, string $name, int $UID = 2): int
	{
		return $this->service->addSubNode($UID, $name, $this->node($parentId));
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function node(int $nodeId): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM mediapool_nodes WHERE node_id = ?', [$nodeId]);
		self::assertIsArray($row, 'No node ' . $nodeId);

		return $row;
	}

	/**
	 * The names of the tree in the order of the left values, indented by the level: shows the structure.
	 *
	 * @return list<string>
	 */
	protected function outline(int $rootId): array
	{
		$outline = [];
		foreach ($this->connection->fetchAllAssociative('SELECT name, level FROM mediapool_nodes WHERE root_id = ? ORDER BY lft', [$rootId]) as $row)
			$outline[] = str_repeat('-', (int) $row['level'] - 1) . $row['name'];

		return $outline;
	}

	protected function assertValidTrees(): void
	{
		$roots = $this->connection->fetchFirstColumn('SELECT DISTINCT root_id FROM mediapool_nodes ORDER BY root_id');
		foreach ($roots as $rootId)
			$this->assertValidTree((int) $rootId);
	}

	private function assertValidTree(int $rootId): void
	{
		/** @var array<int,array<string,mixed>> $nodes */
		$nodes = [];
		foreach ($this->connection->fetchAllAssociative('SELECT * FROM mediapool_nodes WHERE root_id = ? ORDER BY lft', [$rootId]) as $row)
			$nodes[(int) $row['node_id']] = $row;

		$count   = count($nodes);
		$numbers = [];
		foreach ($nodes as $node)
		{
			static::assertLessThan((int) $node['rgt'], (int) $node['lft'], 'lft must be smaller than rgt in node ' . $node['node_id'] . ' of tree ' . $rootId);
			$numbers[] = (int) $node['lft'];
			$numbers[] = (int) $node['rgt'];
		}
		sort($numbers);
		static::assertSame(range(1, 2 * $count), $numbers, 'The numbers of tree ' . $rootId . ' must be exactly 1 to ' . (2 * $count));

		foreach ($nodes as $node)
		{
			if ((int) $node['parent_id'] === 0)
			{
				static::assertSame($rootId, (int) $node['node_id'], 'Only the root has no parent in tree ' . $rootId);
				static::assertSame(1, (int) $node['lft']);
				static::assertSame(1, (int) $node['level']);
				continue;
			}

			static::assertArrayHasKey((int) $node['parent_id'], $nodes, 'The parent of node ' . $node['node_id'] . ' is not in tree ' . $rootId);
			$parent = $nodes[(int) $node['parent_id']];
			static::assertGreaterThan((int) $parent['lft'], (int) $node['lft'], 'Node ' . $node['node_id'] . ' must be inside its parent');
			static::assertLessThan((int) $parent['rgt'], (int) $node['rgt'], 'Node ' . $node['node_id'] . ' must be inside its parent');
			static::assertSame((int) $parent['level'] + 1, (int) $node['level'], 'Level of node ' . $node['node_id']);
		}
	}
}
