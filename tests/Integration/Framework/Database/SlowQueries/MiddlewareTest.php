<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Framework\Database\SlowQueries;

use App\Framework\Database\SlowQueries\Middleware;
use App\Framework\Database\SlowQueries\SlowQueryLog;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The middleware with a real SQLite database in the memory.
 */
#[Group('db')]
class MiddlewareTest extends TestCase
{
	private TestHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->handler = new TestHandler();
	}

	/**
	 * Prepared statements, queries and statements of the connection are all measured.
	 */
	public function testTheMiddlewareMeasuresEveryKindOfQuery(): void
	{
		$configuration = new Configuration();
		// the threshold 0 writes every query
		$configuration->setMiddlewares([new Middleware(new SlowQueryLog(new Logger('test', [$this->handler]), 0))]);
		$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);

		$connection->executeStatement('CREATE TABLE t (id INTEGER)');
		$connection->executeStatement('INSERT INTO t (id) VALUES (?)', [7]);
		static::assertSame(7, (int) $connection->fetchOne('SELECT id FROM t WHERE id = ?', [7]));
		static::assertSame(7, (int) $connection->fetchOne('SELECT id FROM t'));

		static::assertSame(['CREATE TABLE t (id INTEGER)', 'INSERT INTO t (id) VALUES (?)', 'SELECT id FROM t WHERE id = ?', 'SELECT id FROM t'],
			array_map(static fn($record): string => $record->context['sql'], $this->handler->getRecords()));
	}
}
