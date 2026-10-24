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

use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Exceptions\FrameworkException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('db')]
class ServiceTest extends NestedSetsTestCase
{
	public function testTheTreeOfTheMigrationIsValid(): void
	{
		$this->assertValidTrees();
		static::assertSame(['public', '-images', '-videos', '-widgets', '-documents', '-firmware'], $this->outline(1));
	}

	public function testAddRootNodeStartsANewTree(): void
	{
		$id = $this->service->addRootNode(2, 'anna', true);

		$node = $this->node($id);
		static::assertSame($id, (int) $node['root_id']);
		static::assertSame($id, (int) $node['root_order']);
		static::assertSame(0, (int) $node['parent_id']);
		static::assertSame(1, (int) $node['is_user_folder']);
		static::assertSame(2, (int) $node['UID']);
		static::assertSame(0, (int) $node['visibility']);
		$this->assertValidTrees();
	}

	public function testAddSubNodesBuildsAValidTree(): void
	{
		$ids = $this->buildTree();

		$this->assertValidTrees();
		static::assertSame(['A', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($ids['A']));
		static::assertSame($ids['B'], (int) $this->node($ids['B1'])['parent_id']);
		static::assertSame(3, (int) $this->node($ids['B1'])['level']);
	}

	public function testAddSubNodeInTheTreeOfTheMigration(): void
	{
		$id = $this->addSub(3, 'Neu unter videos', 1);

		static::assertSame(['public', '-images', '-videos', '--Neu unter videos', '-widgets', '-documents', '-firmware'], $this->outline(1));
		static::assertSame(3, (int) $this->node($id)['parent_id']);
		$this->assertValidTrees();
	}

	public function testTreesDoNotInfluenceEachOther(): void
	{
		$a = $this->buildTree('A');
		$before = $this->outline(1);

		$b = $this->buildTree('Z');

		static::assertSame($before, $this->outline(1));
		static::assertSame(['A', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($a['A']));
		static::assertSame(['Z', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($b['Z']));
		$this->assertValidTrees();
	}

	public function testDeleteSingleNodeClosesTheGap(): void
	{
		$ids = $this->buildTree();

		$this->service->deleteSingleNode($this->node($ids['C']));

		static::assertSame(['A', '-B', '--B1', '--B2', '-D'], $this->outline($ids['A']));
		$this->assertValidTrees();
	}

	public function testDeleteSingleNodeOfTheFirstAndLastChild(): void
	{
		$ids = $this->buildTree();

		$this->service->deleteSingleNode($this->node($ids['B1']));
		$this->service->deleteSingleNode($this->node($ids['D']));

		static::assertSame(['A', '-B', '--B2', '-C'], $this->outline($ids['A']));
		$this->assertValidTrees();
	}

	public function testDeleteTreeRemovesTheNodeAndItsChildren(): void
	{
		$ids = $this->buildTree();

		$this->service->deleteTree($this->node($ids['B']));

		static::assertSame(['A', '-C', '-D'], $this->outline($ids['A']));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_nodes WHERE name LIKE ?', ['B%']));
		$this->assertValidTrees();
	}

	public function testDeleteTreeOfAWholeTree(): void
	{
		$ids = $this->buildTree();
		$other = $this->outline(1);

		$this->service->deleteTree($this->node($ids['A']));

		static::assertSame([], $this->outline($ids['A']));
		static::assertSame($other, $this->outline(1));
		$this->assertValidTrees();
	}

	public function testDeleteOfAnUnknownNodeFailsAndChangesNothing(): void
	{
		$ids = $this->buildTree();
		$ghost = ['node_id' => 9999, 'root_id' => $ids['A'], 'lft' => 20, 'rgt' => 21];

		$this->expectException(FrameworkException::class);
		try
		{
			$this->service->deleteSingleNode($ghost);
		}
		finally
		{
			static::assertSame(['A', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($ids['A']));
			$this->assertValidTrees();
		}
	}

	public function testDeleteTreeOfAnUnknownNodeFailsAndChangesNothing(): void
	{
		$ids = $this->buildTree();
		$ghost = ['node_id' => 9999, 'root_id' => $ids['A'], 'lft' => 20, 'rgt' => 21];

		$this->expectException(FrameworkException::class);
		try
		{
			$this->service->deleteTree($ghost);
		}
		finally
		{
			static::assertSame(['A', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($ids['A']));
			$this->assertValidTrees();
		}
	}

	/**
	 * @return array<string,array{string,string,string,list<string>}>
	 */
	public static function movesInOneTree(): array
	{
		return [
			'leaf after a sibling'            => ['C', 'D', Calculator::REGION_AFTER,       ['A', '-B', '--B1', '--B2', '-D', '-C']],
			'leaf before a sibling'           => ['D', 'B', Calculator::REGION_BEFORE,      ['A', '-D', '-B', '--B1', '--B2', '-C']],
			'leaf as child of another leaf'   => ['D', 'C', Calculator::REGION_APPENDCHILD, ['A', '-B', '--B1', '--B2', '-C', '--D']],
			'leaf into a subtree'             => ['D', 'B', Calculator::REGION_APPENDCHILD, ['A', '-B', '--B1', '--B2', '--D', '-C']],
			'subtree after a sibling'         => ['B', 'D', Calculator::REGION_AFTER,       ['A', '-C', '-D', '-B', '--B1', '--B2']],
			'subtree before a sibling'        => ['B', 'C', Calculator::REGION_BEFORE,      ['A', '-B', '--B1', '--B2', '-C', '-D']],
			'subtree as child of a sibling'   => ['B', 'C', Calculator::REGION_APPENDCHILD, ['A', '-C', '--B', '---B1', '---B2', '-D']],
			'child of a subtree to the top'   => ['B1', 'A', Calculator::REGION_APPENDCHILD, ['A', '-B', '--B2', '-C', '-D', '-B1']],
			'child of a subtree next to it'   => ['B1', 'B', Calculator::REGION_AFTER,      ['A', '-B', '--B2', '-B1', '-C', '-D']],
			'last sibling to the first place' => ['D', 'B1', Calculator::REGION_BEFORE,     ['A', '-B', '--D', '--B1', '--B2', '-C']],
		];
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('movesInOneTree')]
	public function testMoveNodeInsideOneTree(string $moved, string $target, string $region, array $expected): void
	{
		$ids = $this->buildTree();

		$this->service->moveNode($this->node($ids[$moved]), $this->node($ids[$target]), $region);

		static::assertSame($expected, $this->outline($ids['A']));
		$this->assertValidTrees();
	}

	public function testMoveNodeToAnotherTree(): void
	{
		$a = $this->buildTree('A');
		$z = $this->buildTree('Z');

		$this->service->moveNode($this->node($a['B']), $this->node($z['C']), Calculator::REGION_APPENDCHILD);

		static::assertSame(['A', '-C', '-D'], $this->outline($a['A']));
		static::assertSame(['Z', '-B', '--B1', '--B2', '-C', '--B', '---B1', '---B2', '-D'], $this->outline($z['Z']));
		static::assertSame($z['Z'], (int) $this->node($a['B'])['root_id']);
		static::assertSame($z['Z'], (int) $this->node($a['B1'])['root_id']);
		static::assertSame($z['C'], (int) $this->node($a['B'])['parent_id']);
		$this->assertValidTrees();
	}

	public function testMoveNodeToAnotherTreeBeforeAndAfter(): void
	{
		$a = $this->buildTree('A');
		$z = $this->buildTree('Z');

		$this->service->moveNode($this->node($a['C']), $this->node($z['B1']), Calculator::REGION_BEFORE);
		$this->service->moveNode($this->node($a['D']), $this->node($z['D']), Calculator::REGION_AFTER);

		static::assertSame(['A', '-B', '--B1', '--B2'], $this->outline($a['A']));
		static::assertSame(['Z', '-B', '--C', '--B1', '--B2', '-C', '-D', '-D'], $this->outline($z['Z']));
		$this->assertValidTrees();
	}

	public function testMoveNodeIntoTheTreeOfTheMigration(): void
	{
		$a = $this->buildTree('A');

		$this->service->moveNode($this->node($a['B']), $this->node(4), Calculator::REGION_APPENDCHILD);

		static::assertSame(['public', '-images', '-videos', '-widgets', '--B', '---B1', '---B2', '-documents', '-firmware'], $this->outline(1));
		static::assertSame(['A', '-C', '-D'], $this->outline($a['A']));
		$this->assertValidTrees();
	}

	public function testSeveralMovesKeepTheTreeValid(): void
	{
		$ids = $this->buildTree();

		$this->service->moveNode($this->node($ids['D']), $this->node($ids['B1']), Calculator::REGION_BEFORE);
		$this->service->moveNode($this->node($ids['B']), $this->node($ids['C']), Calculator::REGION_APPENDCHILD);
		$this->service->moveNode($this->node($ids['B2']), $this->node($ids['A']), Calculator::REGION_APPENDCHILD);
		$this->service->moveNode($this->node($ids['C']), $this->node($ids['B2']), Calculator::REGION_BEFORE);

		$this->assertValidTrees();
		static::assertCount(6, $this->outline($ids['A']));
	}

	public function testMoveNodeWithAnUnknownRegionChangesNothing(): void
	{
		$ids = $this->buildTree();

		try
		{
			$this->service->moveNode($this->node($ids['C']), $this->node($ids['D']), 'sideways');
			static::fail('Expected an exception');
		}
		catch (\Throwable)
		{
			static::assertSame(['A', '-B', '--B1', '--B2', '-C', '-D'], $this->outline($ids['A']));
			$this->assertValidTrees();
		}
	}
}
