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

namespace Tests\Integration\Framework\Database\BaseRepositories;

use App\Framework\Database\BaseRepositories\Transactions;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The transactions of the services. They are nested in the application: a service starts one and
 * calls another service which starts its own.
 */
#[Group('db')]
class TransactionsTest extends RepositoryTestCase
{
	private Transactions $transactions;

	protected function setUp(): void
	{
		parent::setUp();
		$this->transactions = new Transactions($this->connection);
	}

	private function countTemplates(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates');
	}

	private function insertTemplate(string $name): void
	{
		$this->connection->executeStatement('INSERT INTO templates (UID, name) VALUES (1, ?)', [$name]);
	}

	/**
	 * @throws Exception
	 */
	public function testCommitKeepsTheChanges(): void
	{
		static::assertFalse($this->transactions->isActive());
		$this->transactions->begin();
		static::assertTrue($this->transactions->isActive());
		$this->insertTemplate('a');

		$this->transactions->commit();

		static::assertFalse($this->transactions->isActive());
		static::assertSame(1, $this->countTemplates());
	}

	/**
	 * @throws Exception
	 */
	public function testRollBackUndoesTheChanges(): void
	{
		$this->transactions->begin();
		$this->insertTemplate('a');

		$this->transactions->rollBack();

		static::assertFalse($this->transactions->isActive());
		static::assertSame(0, $this->countTemplates());
	}

	/**
	 * @throws Exception
	 */
	public function testNestedTransactionsAreCommittedTogether(): void
	{
		$this->transactions->begin();
		$this->insertTemplate('outer');
		$this->transactions->begin();
		$this->insertTemplate('inner');

		$this->transactions->commit();
		static::assertTrue($this->transactions->isActive(), 'The outer transaction is still open.');
		$this->transactions->commit();

		static::assertFalse($this->transactions->isActive());
		static::assertSame(2, $this->countTemplates());
	}

	/**
	 * What the services do when the inner one fails: it rolls back, throws, and the outer catches and
	 * rolls back as well. Nothing stays.
	 *
	 * @throws Exception
	 */
	public function testAFailingInnerTransactionTakesTheOuterOneWithIt(): void
	{
		$this->transactions->begin();
		$this->insertTemplate('outer');
		$this->transactions->begin();
		$this->insertTemplate('inner');
		$this->transactions->rollBack();

		$this->transactions->rollBack();

		static::assertFalse($this->transactions->isActive());
		static::assertSame(0, $this->countTemplates());
	}

	/**
	 * Doctrine nests the transactions with savepoints: the rollback of an inner one undoes only
	 * its own changes. The outer one can carry on and commit what it did itself.
	 *
	 * @throws Exception
	 */
	public function testAnInnerRollbackUndoesOnlyTheInnerChanges(): void
	{
		$this->transactions->begin();
		$this->insertTemplate('outer');
		$this->transactions->begin();
		$this->insertTemplate('inner');
		$this->transactions->rollBack();

		$this->transactions->commit();

		static::assertFalse($this->transactions->isActive());
		static::assertSame(['outer'], $this->connection->fetchFirstColumn('SELECT name FROM templates'));
	}

	public function testCommitWithoutATransactionFails(): void
	{
		$this->expectException(Exception::class);
		$this->transactions->commit();
	}
}
