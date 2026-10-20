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


namespace Tests\Unit\Framework\Database\BaseRepositories;

use App\Framework\Database\BaseRepositories\SqlBase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
class ConcreteSqlBase extends SqlBase
{

}
class SqlBaseTest extends TestCase
{
	private Connection&Stub $connection;
	private ConcreteSqlBase $sqlBase;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = $this->createStub(Connection::class);
		$this->sqlBase = new ConcreteSqlBase($this->connection, 'test', 'test_id');
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testGetterSetter(): void
	{
		static::assertSame('test', $this->sqlBase->getTable());
		static::assertSame('test_id', $this->sqlBase->getIdField());

		$this->sqlBase->setTable('new');
		$this->sqlBase->setIdField('new_id');

		static::assertSame('new', $this->sqlBase->getTable());
		static::assertSame('new_id', $this->sqlBase->getIdField());

	}

}
