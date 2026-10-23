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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;
use Tests\Integration\Support\TraitsTestRepository;

/**
 * The helpers every repository uses, on the table templates. Fixtures: 1 admin Vorlage (UID 1, public),
 * 2 Annas Vorlage (UID 2, private), 3 Bernds Vorlage (UID 3, private), plus 4 "Annas zweite" (UID 2, private).
 */
#[Group('db')]
class TraitsTest extends RepositoryTestCase
{
	private TraitsTestRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->connection->executeStatement("INSERT INTO templates (template_id, UID, visibility, name) VALUES (4, 2, 'private', 'Annas zweite')");

		$this->repository = new TraitsTestRepository($this->connection);
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<int>
	 */
	private function ids(array $rows): array
	{
		return array_map('intval', array_column($rows, 'template_id'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdAndFindFirstById(): void
	{
		static::assertSame('Annas Vorlage', $this->repository->findFirstById(2)['name']);
		static::assertCount(1, $this->repository->findById(2));
		static::assertSame([], $this->repository->findById(999));
		static::assertSame([], $this->repository->findFirstById(999));
	}

	/**
	 * @throws Exception
	 */
	public function testFindFirstBy(): void
	{
		static::assertSame(3, (int) $this->repository->findFirstBy(['UID' => 3])['template_id']);
		static::assertSame(1, (int) $this->repository->findFirstBy()['template_id']);
		static::assertSame([], $this->repository->findFirstBy(['UID' => 99]));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByConditionsWithOperators(): void
	{
		static::assertSame([2, 4], $this->ids($this->repository->findAllBy(['UID' => 2])));
		static::assertSame([3, 4], $this->ids($this->repository->findAllBy(['template_id' => $this->repository->generateWhereClause(2, '>')])));
		static::assertSame([2, 4], $this->ids($this->repository->findAllBy(['name' => $this->repository->generateWhereClause('Annas%', 'LIKE')])));
		static::assertSame([1, 2, 3, 4], $this->ids($this->repository->findAllBy()));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByWithTwoConditionsNeedsBoth(): void
	{
		$rows = $this->repository->findAllBy(['UID' => 2, 'visibility' => 'private', 'name' => 'Annas zweite']);

		static::assertSame([4], $this->ids($rows));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByWithInOperator(): void
	{
		$byIds   = $this->repository->findAllBy(['template_id' => $this->repository->generateWhereClause('1,3', 'IN')]);
		$byNames = $this->repository->findAllBy([
			'name' => $this->repository->generateWhereClause('Admin Vorlage,Bernds Vorlage', 'IN', 'AND', ArrayParameterType::STRING)
		]);

		static::assertSame([1, 3], $this->ids($byIds));
		static::assertSame([1, 3], $this->ids($byNames));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByWithOrCombinesTheConditions(): void
	{
		$rows = $this->repository->findAllBy([
			'UID'          => $this->repository->generateWhereClause(1, '=', 'OR'),
			'template_id'  => $this->repository->generateWhereClause(3, '=', 'OR'),
		]);

		static::assertSame([1, 3], $this->ids($rows));
	}

	/**
	 * A condition with AND before an OR must stay a filter: the user sees only his own rows.
	 *
	 * @throws Exception
	 */
	public function testAnAndConditionIsNotBypassedByAnOrCondition(): void
	{
		$rows = $this->repository->findAllBy([
			'UID'         => 2,
			'template_id' => $this->repository->generateWhereClause(1, '=', 'OR'),
		]);

		// the rows of UID 2 plus the row 1, as the query says: (UID = 2) OR (template_id = 1)
		static::assertSame([1, 2, 4], $this->ids($rows));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByWithJoinAndOrderAndLimit(): void
	{
		$rows = $this->repository->findAllByWithFields(
			['templates.template_id', 'user_main.username'],
			['user_main.username' => $this->repository->generateWhereClause('anna', '=')],
			['user_main' => 'user_main.UID = templates.UID'],
			$this->repository->determineLimit(1, 1),
			'',
			[['sort' => 'templates.template_id', 'order' => 'DESC']]
		);

		static::assertSame([['template_id' => 4, 'username' => 'anna']], array_map(static fn(array $r): array => ['template_id' => (int) $r['template_id'], 'username' => $r['username']], $rows));
	}

	/**
	 * @throws Exception
	 */
	public function testDetermineLimitCountsThePagesFromOne(): void
	{
		static::assertSame(['first' => 0, 'max' => 2], $this->repository->determineLimit(0, 2));
		static::assertSame(['first' => 0, 'max' => 2], $this->repository->determineLimit(1, 2));
		static::assertSame(['first' => 2, 'max' => 2], $this->repository->determineLimit(2, 2));
		static::assertSame([], $this->repository->determineLimit(3, 0));

		static::assertSame([3, 4], $this->ids($this->repository->findAllBy([], [], $this->repository->determineLimit(2, 2))));
		static::assertSame([1, 2], $this->ids($this->repository->findAllBy([], [], $this->repository->determineLimit(1, 2))));
	}

	/**
	 * @throws Exception
	 */
	public function testFindAllByWithLimitsAndOrder(): void
	{
		$rows = $this->repository->findAllByWithLimits(
			['first' => 1, 'max' => 2],
			[['sort' => 'template_id', 'order' => 'DESC']],
			['visibility' => 'private']
		);

		static::assertSame([3, 2], $this->ids($rows));
	}

	/**
	 * @throws Exception
	 */
	public function testOrderByTwoColumns(): void
	{
		$rows = $this->repository->findAllBy([], [], [], '', [['sort' => 'visibility', 'order' => 'ASC'], ['sort' => 'template_id', 'order' => 'DESC']]);

		static::assertSame([4, 3, 2, 1], $this->ids($rows));
	}

	/**
	 * @throws Exception
	 */
	public function testCounting(): void
	{
		static::assertSame(4, $this->repository->countAll());
		static::assertSame(2, $this->repository->countAllBy(['UID' => 2]));
		static::assertSame(0, $this->repository->countAllBy(['UID' => 99]));
		static::assertSame(2, $this->repository->countAllBy(['visibility' => 'private'], [], 'UID'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindOneValueBy(): void
	{
		static::assertSame('Annas Vorlage', $this->repository->findOneValueBy('name', ['template_id' => 2]));
		static::assertSame('', $this->repository->findOneValueBy('name', ['template_id' => 999]));
	}

	/**
	 * @throws Exception
	 */
	public function testInsertReturnsTheNewId(): void
	{
		static::assertSame('5', (string) $this->repository->insert(['UID' => 3, 'name' => 'Neu']));
		static::assertSame('Neu', $this->repository->findFirstById(5)['name']);
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateChangesOnlyTheGivenRow(): void
	{
		static::assertSame(1, $this->repository->update(2, ['name' => 'Umbenannt']));
		static::assertSame(0, $this->repository->update(999, ['name' => 'x']));

		static::assertSame('Umbenannt', $this->repository->findFirstById(2)['name']);
		static::assertSame('Bernds Vorlage', $this->repository->findFirstById(3)['name']);
	}

	/**
	 * @throws Exception
	 */
	public function testUpdateWithWhere(): void
	{
		$affected = $this->repository->updateWithWhere(['visibility' => 'public'], ['UID' => 2, 'visibility' => 'private']);

		static::assertSame(2, $affected);
		static::assertSame('public', $this->repository->findFirstById(4)['visibility']);
		static::assertSame('private', $this->repository->findFirstById(3)['visibility']);
		static::assertSame(0, $this->repository->updateWithWhere(['visibility' => 'x'], ['UID' => 99]));
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteVariants(): void
	{
		static::assertSame(1, $this->repository->delete(1));
		static::assertSame(0, $this->repository->delete(1));
		static::assertSame(2, $this->repository->deleteByField('UID', 2));
		static::assertSame(1, $this->repository->deleteBy(['template_id' => $this->repository->generateWhereClause('3,9', 'IN')]));

		static::assertSame(0, $this->repository->countAll());
	}

	/**
	 * @throws Exception
	 */
	public function testDeleteByWithoutAMatchDeletesNothing(): void
	{
		static::assertSame(0, $this->repository->deleteBy(['UID' => 99]));
		static::assertSame(4, $this->repository->countAll());
	}

	public function testTheConversionHelpers(): void
	{
		$repository = $this->repository;

		static::assertSame([], $repository->explode(''));
		static::assertSame(['a', 'b'], $repository->explode('a,b'));
		static::assertSame('a,b', $repository->implode(['a', 'b']));
		static::assertSame(['x' => 1], $repository->unserialize($repository->serialize(['x' => 1])));
		static::assertSame([], $repository->unserialize(null));
		static::assertSame([], $repository->unserialize('garbage'));
	}

	public function testTheTableAndTheIdFieldCanBeChanged(): void
	{
		$this->repository->setTable('user_main')->setIdField('UID');

		static::assertSame('user_main', $this->repository->getTable());
		static::assertSame('UID', $this->repository->getIdField());
		static::assertSame('anna', $this->repository->findFirstById(2)['username']);
	}
}
