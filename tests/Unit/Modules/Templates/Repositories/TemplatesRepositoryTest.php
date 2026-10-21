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

namespace Tests\Unit\Modules\Templates\Repositories;

use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplatesRepositoryTest extends TestCase
{
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private TemplatesRepository $repository;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$connectionStub         = $this->createStub(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock       = $this->createMock(Result::class);

		$connectionStub->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
		$this->repository = new TemplatesRepository($connectionStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindFirstWithUserName(): void
	{
		$expected = ['template_id' => 5, 'name' => 'Template', 'username' => 'admin', 'company_id' => 1];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('templates.*, user_main.username, user_main.company_id')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')->with('templates')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('templates', 'user_main', 'user_main', 'user_main.UID=templates.UID')
			->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('andWhere')->with('template_id = :template_id');
		$this->queryBuilderMock->expects($this->once())->method('setParameter')->with('template_id', 5);
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn([$expected]);

		static::assertSame($expected, $this->repository->findFirstWithUserName(5));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFindFirstWithUserNameNotFound(): void
	{
		$this->queryBuilderMock->method('select')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

		static::assertSame([], $this->repository->findFirstWithUserName(5));
	}
}
