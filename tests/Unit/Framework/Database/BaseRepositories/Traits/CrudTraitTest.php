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

namespace Tests\Unit\Framework\Database\BaseRepositories\Traits;

use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SqlConcrete extends SqlBase
{
	use CrudTraits;
	/**
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 */
	public function testFetchAssociative(QueryBuilder $queryBuilder): array
	{
		return $this->fetchAssociative($queryBuilder);
	}

	/**
	 * @return string[]
	 */
	public function testSecureExplode(string $data): array
	{
		return $this->secureExplode($data);
	}

	/**
	 * @return array<string,mixed>|list<array<string,mixed>>
	 */
	public function testSecureUnserialize(string $data): array
	{
		return $this->secureUnserialize($data);
	}

}
class CrudTraitTest extends TestCase
{
	private Connection&MockObject	 $connectionMock;
	private QueryBuilder&MockObject $queryBuilderMock;
	private SqlConcrete $repository;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->connectionMock   = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->repository       = new SqlConcrete($this->connectionMock, 'table', 'id');
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInsert(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$fields = [
			'field1' => 'field 1 value',
			'field2' => 'field 2 value'
		];
		$this->connectionMock->expects($this->once())->method('insert')
			->with('table', $fields);

		$this->connectionMock->expects($this->once())->method('lastInsertId')
			->willReturn(1);

		static::assertEquals(1, $this->repository->insert($fields));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdate(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$fields = [
			'field1' => 'field 1 value',
			'field2' => 'field 2 value'
		];
		$this->connectionMock->expects($this->once())->method('update')
			->with('table', $fields, ['id' => 34])
			->willReturn(2);

		static::assertEquals(2, $this->repository->update(34, $fields));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUpdateWithWhere(): void
	{
		$fields = [
			'field1' => 'field 1 value',
			'field2' => 'field 2 value'
		];
		$conditions = [
			'condition1' => 'condition 1 value',
			'condition2' => 'condition 2 value'
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('update')->with('table');

		$this->queryBuilderMock->expects($this->exactly(2))
			->method('set')
			->willReturnCallback(function ($field, $param) {
				$expectedFields = ['field1', 'field2'];
				$expectedParams = [':set_field1', ':set_field2'];
				static::assertContains($field, $expectedFields);
				static::assertContains($param, $expectedParams);
				return $this->queryBuilderMock;
			});

		$this->queryBuilderMock->expects($this->exactly(2))
			->method('andWhere')
			->willReturnCallback(function ($condition) {
				$expectedConditions = ['condition1 = :condition1', 'condition2 = :condition2'];
				static::assertContains($condition, $expectedConditions);
				return $this->queryBuilderMock;
			});

		$this->queryBuilderMock->expects($this->exactly(4))
			->method('setParameter')
			->willReturnCallback(function ($name, $value) {
				$expectedNames = ['set_field1', 'set_field2', 'condition1', 'condition2'];
				$expectedValues = ['field 1 value', 'field 2 value', 'condition 1 value', 'condition 2 value'];
				static::assertContains($name, $expectedNames);
				static::assertContains($value, $expectedValues);
				return $this->queryBuilderMock;
			});


		$this->queryBuilderMock->expects($this->once())->method('executeStatement')
			->willReturn(1);

		static::assertEquals(1, $this->repository->updateWithWhere($fields, $conditions));

	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDelete(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->connectionMock->expects($this->once())->method('delete')
			->with('table', ['id' => 36])
			->willReturn(17);

		static::assertEquals(17, $this->repository->delete(36));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteByField(): void
	{
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$this->connectionMock->expects($this->once())->method('delete')
			->with('table', ['field' => 'value'])
			->willReturn(94);

		static::assertEquals(94, $this->repository->deleteByField('field', 'value'));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteBy(): void
	{
		$conditions = [
			'condition1' => 'condition1_value',
			'condition2' => 'condition2_value'
		];

		$this->connectionMock->expects($this->once())->method('createQueryBuilder')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('delete')->with('table');

		$this->queryBuilderMock->expects($this->exactly(2))
			->method('andWhere')
			->willReturnCallback(function ($condition) {
				$expectedConditions = ['condition1 = :condition1', 'condition2 = :condition2'];
				static::assertContains($condition, $expectedConditions);
				return $this->queryBuilderMock;
			});

		$this->queryBuilderMock->expects($this->exactly(2))
			->method('setParameter')
			->willReturnCallback(function ($name, $value) {
				$expectedNames = ['condition1', 'condition2'];
				$expectedValues = ['condition1_value', 'condition2_value'];
				static::assertContains($name, $expectedNames);
				static::assertContains($value, $expectedValues);
				return $this->queryBuilderMock;
			});


		$this->queryBuilderMock->expects($this->once())->method('executeStatement')
			->willReturn(365);

		static::assertEquals(365, $this->repository->deleteBy($conditions));
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchAssociativeFails(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$resultStub = $this->createStub(Result::class);
		$this->queryBuilderMock->expects($this->atLeastOnce())->method('executeQuery')->willReturn($resultStub);
		$resultStub->method('fetchAssociative')->willReturn(false);

		static::assertEmpty($this->repository->testFetchAssociative($this->queryBuilderMock));
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFetchAssociativeSucceed(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$resultStub = $this->createStub(Result::class);
		$this->queryBuilderMock->expects($this->atLeastOnce())->method('executeQuery')->willReturn($resultStub);
		$expected = ['some' => 'result'];
		$resultStub->method('fetchAssociative')->willReturn($expected);

		static::assertSame($expected, $this->repository->testFetchAssociative($this->queryBuilderMock));
	}

	#[Group('units')]
	public function testSecureExplodeEmpty(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		static::assertEmpty($this->repository->testSecureExplode(''));
	}

	#[Group('units')]
	public function testSecureExplode(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$expected = ['some'];
		static::assertSame($expected, $this->repository->testSecureExplode('some'));

		$expected = ['some', 'result'];
		static::assertSame($expected, $this->repository->testSecureExplode('some,result'));
	}

	#[Group('units')]
	public function testSecureUnserializeEmpty(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		static::assertEmpty($this->repository->testSecureUnserialize(''));
	}

	#[Group('units')]
	public function testSecureUnserializeError(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		static::assertEmpty($this->repository->testSecureUnserialize('mbmb'));
	}

	#[Group('units')]
	public function testSecureUnserialize(): void
	{
		$this->connectionMock->expects($this->never())->method($this->anything());
		$this->queryBuilderMock->expects($this->never())->method($this->anything());
		$expected = ['some', 'array', 'result'];
		$result = $this->repository->testSecureUnserialize(serialize($expected));
		static::assertSame($expected, $result);
	}

}
