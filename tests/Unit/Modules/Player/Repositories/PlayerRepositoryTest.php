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

namespace Tests\Unit\Modules\Player\Repositories;

use App\Modules\Player\Repositories\PlayerRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Only what the integration tests (tests/Integration) can not reach with SQLite:
 * the MariaDB branch of the dashboard query and the defensive branch for a missing result.
 * Everything else of PlayerRepository is covered there against the real schema.
 */
class PlayerRepositoryTest extends TestCase
{
	private Connection&MockObject $connectionMock;
	private PlayerRepository $repository;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock = $this->createMock(Connection::class);
		$this->repository     = new PlayerRepository($this->connectionMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardMariaDB(): void
	{
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn(new MariaDBPlatform());

		$this->connectionMock->expects($this->once())->method('fetchAssociative')
			->with(static::stringContains('DATE_SUB(NOW()'))
			->willReturn(['active' => 6, 'pending' => 7, 'inactive' => 8]);

		static::assertEquals(['active' => 6, 'pending' => 7, 'inactive' => 8], $this->repository->findAllForDashboard());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllForDashboardReturnsEmptyArrayWithoutResult(): void
	{
		$this->connectionMock->expects($this->once())
			->method('getDatabasePlatform')
			->willReturn(new PostgreSQLPlatform());

		$this->connectionMock->expects($this->once())->method('fetchAssociative')
			->willReturn(false);

		static::assertSame([], $this->repository->findAllForDashboard());
	}
}
