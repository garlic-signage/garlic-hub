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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Database\NestedSets\Repository;
use App\Framework\Database\NestedSets\Service;
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The failure paths of the Service which a real database can not reach: the repository reports that
 * nothing was written. The success paths and the real tree are in tests/Integration/Framework/Database/NestedSets.
 *
 * Transactions is real (on an empty in-memory database). Doctrine throws when it rolls back without an open
 * transaction, so the expected FrameworkException also shows that the Service began a transaction and rolled it back.
 */
class ServiceTest extends TestCase
{
	private Connection $connection;
	private Transactions $transactions;
	private Repository&Stub $repositoryStub;
	private Service $service;

	protected function setUp(): void
	{
		parent::setUp();

		$this->connection     = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
		$this->transactions   = new Transactions($this->connection);
		$this->repositoryStub = $this->createStub(Repository::class);
		$this->service        = new Service($this->repositoryStub, new Calculator(), $this->transactions);
	}

	protected function tearDown(): void
	{
		$this->connection->close();
		parent::tearDown();
	}

	#[Group('units')]
	public function testAddRootNodeFailsWhenTheInsertWritesNothing(): void
	{
		$this->repositoryStub->method('insert')->willReturn(0);

		$this->assertFailsAndRollsBack('Insert new node failed', fn() => $this->service->addRootNode(1, 'Root'));
	}

	#[Group('units')]
	public function testAddRootNodeFailsWhenTheRootIdCanNotBeSet(): void
	{
		$this->repositoryStub->method('insert')->willReturn(5);
		$this->repositoryStub->method('update')->willReturn(0);

		$this->assertFailsAndRollsBack('Update root node failed', fn() => $this->service->addRootNode(1, 'Root'));
	}

	#[Group('units')]
	public function testAddSubNodeFailsWhenTheInsertWritesNothing(): void
	{
		$this->repositoryStub->method('insert')->willReturn(0);
		$parent = ['node_id' => 1, 'root_id' => 1, 'lft' => 1, 'rgt' => 2, 'level' => 1];

		$this->assertFailsAndRollsBack('Insert new sub node failed.', fn() => $this->service->addSubNode(1, 'Child', $parent));
	}

	#[Group('units')]
	public function testDeleteTreeFailsWhenNothingWasDeleted(): void
	{
		$this->repositoryStub->method('deleteFullTree')->willReturn(0);
		$node = ['node_id' => 2, 'root_id' => 1, 'lft' => 2, 'rgt' => 5];

		$this->assertFailsAndRollsBack('Node not exists.', fn() => $this->service->deleteTree($node));
	}

	#[Group('units')]
	public function testMoveNodeFailsWhenTheSubTreeCanNotBeMoved(): void
	{
		$this->repositoryStub->method('moveSubTree')->willReturn(0);
		$moved  = ['node_id' => 3, 'root_id' => 1, 'parent_id' => 1, 'name' => 'B', 'lft' => 2, 'rgt' => 3, 'level' => 2];
		$target = ['node_id' => 4, 'root_id' => 1, 'parent_id' => 1, 'name' => 'C', 'lft' => 4, 'rgt' => 5, 'level' => 2];

		$this->assertFailsAndRollsBack(
			'B cannot be moved via after of C',
			fn() => $this->service->moveNode($moved, $target, Calculator::REGION_AFTER)
		);
	}

	private function assertFailsAndRollsBack(string $message, callable $call): void
	{
		try
		{
			$call();
		}
		catch (Throwable $e)
		{
			static::assertInstanceOf(FrameworkException::class, $e);
			static::assertSame($message, $e->getMessage());
			static::assertFalse($this->transactions->isActive(), 'The transaction must be rolled back.');

			return;
		}

		static::fail('Expected a FrameworkException.');
	}
}
