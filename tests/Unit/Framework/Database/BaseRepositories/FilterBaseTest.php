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

use App\Framework\Database\BaseRepositories\FilterBase;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConcreteFilterBase extends FilterBase
{
	protected function prepareJoin(): array
	{
		return ['user_main' => 'test.UID = user_main.UID'];
	}

	protected function prepareSelectFiltered(): array
	{
		return ['selected_filtered'];
	}

	protected function prepareSelectFilteredForUser(): array
	{
		return ['selected_filtered_user'];
	}

	protected function prepareWhereForFiltering(array $filterFields): array
	{
		$where = [];
		foreach ($filterFields as $key => $parameter)
		{
			$clause = $this->determineWhereForFiltering($key, $parameter);
			if (!empty($clause))
				$where = array_merge($where, $clause);
		}
		return $where;
	}

	protected function prepareUserJoin(): array
	{
		return [];
	}
}
class FilterBaseTest extends TestCase
{
	private QueryBuilder&MockObject $queryBuilderMock;
	private Result&MockObject $resultMock;
	private ConcreteFilterBase $FilterBase;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$connectionMock = $this->createMock(Connection::class);
		$this->queryBuilderMock = $this->createMock(QueryBuilder::class);
		$this->resultMock       = $this->createMock(Result::class);

		$this->FilterBase = new ConcreteFilterBase($connectionMock, 'table', 'id');

		$connectionMock->method('createQueryBuilder')->willReturn($this->queryBuilderMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredReturnsCorrectCount(): void
	{
		$fields = [
			'username' => ['value' => 'john'],
			'company_id' => ['value' => 123],
			'randomfield' => ['value' => 'randomvalue']
		];

		$this->setStandardMocksForCount();

		$this->queryBuilderMock->expects($this->exactly(3))->method('andWhere')
			->willReturnMap([
					['user_main.username LIKE :user_mainusername', $this->queryBuilderMock],
					['user_main.company_id = :user_maincompany_id', $this->queryBuilderMock],
					['table.randomfield LIKE :tablerandomfield', $this->queryBuilderMock]
				]
			);
		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
					['user_mainusername', '%john%', $this->queryBuilderMock],
					['user_maincompany_id', 123, $this->queryBuilderMock],
					['tablerandomfield', '%randomvalue%', $this->queryBuilderMock]
				]
			);


		$expectedCount = 42;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->FilterBase->countAllFiltered($fields);
		static::assertSame($expectedCount, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredReturnsWithNoWhere(): void
	{
		$fields = [
			'username' => ['value' => ''],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE=> ['value' => 12],
			'randomfield' => ['value' => '']
		];

		$this->setStandardMocksForCount();

		$this->queryBuilderMock->expects($this->never())->method('andWhere');
		$this->queryBuilderMock->expects($this->never())->method('setParameter');

		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);

		$expectedCount = 333;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);
		static::assertSame($expectedCount,  $this->FilterBase->countAllFiltered($fields));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredReturnsAllEmpty(): void
	{
		$fields = [];

		$this->setStandardMocksForCount();

		$expectedCount = 888;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);
		static::assertSame($expectedCount,  $this->FilterBase->countAllFiltered($fields));
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllFilteredSortByUsername(): void
	{
		$fields = [
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => ['value' => 0],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => ['value' => 10],
			BaseFilterParametersInterface::PARAMETER_SORT_COLUMN => ['value' => 'username'],
			BaseFilterParametersInterface::PARAMETER_SORT_ORDER  => ['value' => 'DESC']
		];

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('selected_filtered_user, \'moduleadmin\'')->willReturnSelf();

		$this->setStandardMocks();

		$this->queryBuilderMock->expects($this->once())->method('addOrderBy')
			->with('user_main.username', 'DESC');

		$expectedResults = [
			['id' => 1, 'name' => 'John Doe'],
			['id' => 2, 'name' => 'Jane Doe']
		];

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expectedResults);

		$result = $this->FilterBase->findAllFiltered($fields);
		static::assertSame($expectedResults, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredByUIDCompanyReseller(): void
	{
		$fields = ['unit_name' => ['value' => 'internal']];
		$companyId = [3, 5, 6];
		$UID = 12;
		$this->setStandardMocksForCount();

		$this->queryBuilderMock->expects($this->exactly(2))->method('andWhere')
			->willReturnMap([
					['table.unit_name LIKE :tableunit_name', $this->queryBuilderMock],
					['table.UID = :tableUID', $this->queryBuilderMock],
				]
			);

		$this->queryBuilderMock->expects($this->once())->method('orWhere')
			->with('user_main.company_id IN (:user_maincompany_id)')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->exactly(3))->method('setParameter')
			->willReturnMap([
					['tableunit_name', '%internal%', $this->queryBuilderMock],
					['tableUID', $UID, $this->queryBuilderMock],
					['user_maincompany_id', ['3','5','6'], ArrayParameterType::INTEGER, $this->queryBuilderMock]
				]
			);

		$expectedCount = 6934;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->FilterBase->countAllFilteredByUIDCompanyReseller($companyId, $fields, $UID);
		static::assertSame($expectedCount, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllFilteredByUIDCompanyReseller(): void
	{
		$fields = [
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => ['value' => 0],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => ['value' => 0],
		];
		$companyId = [3, 5, 6];
		$UID = 12;
		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('selected_filtered, \'subadmin\'')->willReturnSelf();

		$this->setStandardMocks();

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('table.UID = :tableUID')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('orWhere')
			->with('user_main.company_id IN (:user_maincompany_id)')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->exactly(2))->method('setParameter')
			->willReturnMap([
					['user_maincompany_id', ['3','5','6'], ArrayParameterType::INTEGER, $this->queryBuilderMock],
					['tableUID', $UID, ParameterType::STRING, $this->queryBuilderMock]
				]
			);

		$this->queryBuilderMock->expects($this->never())->method('addOrderBy');


		$expectedResults = [
			['id' => 1, 'name' => 'John Doe'],
			['id' => 2, 'name' => 'Jane Doe']
		];

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expectedResults);

		$result = $this->FilterBase->findAllFilteredByUIDCompanyReseller($companyId, $fields, $UID);
		static::assertSame($expectedResults, $result);

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCountAllFilteredByUID(): void
	{
		$fields = [];
		$UID = 14;

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')->willReturnSelf();

		// no left join
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('table.UID = :tableUID')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('tableUID', $UID)->willReturn($this->queryBuilderMock);

		$expectedCount = 34;
		$this->resultMock->expects($this->once())->method('fetchOne')->willReturn($expectedCount);

		$result = $this->FilterBase->countAllFilteredByUID($fields, $UID);
		static::assertSame($expectedCount, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFindAllFilteredByUIDAndFakeSortOrder(): void
	{
		$fields = [
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE => ['value' => 0],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => ['value' => 10],
			BaseFilterParametersInterface::PARAMETER_SORT_COLUMN => ['value' => 'column_name'],
			BaseFilterParametersInterface::PARAMETER_SORT_ORDER  => ['value' => 'SHOULD_RESULTIN_AN_ASC']
		];
		$UID = 14;

		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('selected_filtered, \'user\'')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')->willReturnSelf();

		// no left join
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);

		$this->queryBuilderMock->expects($this->once())->method('andWhere')
			->with('table.UID = :tableUID')
			->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('setParameter')
			->with('tableUID', $UID)->willReturn($this->queryBuilderMock);

		$this->queryBuilderMock->expects($this->once())->method('addOrderBy')->with('table.column_name', 'ASC');

		$expectedResults = [['id' => 1, 'name' => 'John Doe']];

		$this->resultMock->expects($this->once())->method('fetchAllAssociative')->willReturn($expectedResults);

		$result = $this->FilterBase->findAllFilteredByUID($fields, $UID);
		static::assertSame($expectedResults, $result);
	}

	private function setStandardMocksForCount(): void
	{
		$this->queryBuilderMock->expects($this->once())->method('select')
			->with('COUNT(1)')->willReturnSelf();

		$this->setStandardMocks();
	}

	private function setStandardMocks(): void
	{
		$this->queryBuilderMock->expects($this->once())->method('from')
			->with('table')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('leftJoin')
			->with('table', 'user_main', 'user_main', 'test.UID = user_main.UID')->willReturnSelf();
		$this->queryBuilderMock->expects($this->once())->method('executeQuery')->willReturn($this->resultMock);
	}

}
