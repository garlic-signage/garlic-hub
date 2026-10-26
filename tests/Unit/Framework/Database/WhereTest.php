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

namespace Tests\Unit\Framework\Database;

use App\Framework\Database\Compare;
use App\Framework\Database\Where;
use App\Framework\Database\WhereApplier;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Which SQL a condition writes and that nothing else than a field name and a fixed comparison gets into it.
 * The queries with a real database are tested in tests/Integration/Framework/Database/BaseRepositories/TraitsTest.php.
 */
class WhereTest extends TestCase
{
	private Connection $connection;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
	}

	/**
	 * @param array<int|string, int|string|Where> $conditions
	 */
	private function sql(array $conditions): string
	{
		$queryBuilder = $this->connection->createQueryBuilder()->select('*')->from('t');
		new WhereApplier()->apply($queryBuilder, $conditions);

		return $queryBuilder->getSQL();
	}

	#[Group('units')]
	public function testEveryConditionHasItsOwnParameterAndAllAreJoinedWithAnd(): void
	{
		$sql = $this->sql([
			'UID'  => 2,
			Where::compare('t.id', Compare::GreaterOrEqual, 2),
			Where::compare('t.id', Compare::Less, 9),
			Where::in('mode', ['a', 'b'], ArrayParameterType::STRING),
			Where::startsWith('mimetype', 'image'),
		]);

		static::assertSame(
			"SELECT * FROM t WHERE (UID = :where0) AND (t.id >= :where1) AND (t.id < :where2) AND (mode IN (:where3)) AND (mimetype LIKE :where4 ESCAPE '!')",
			$sql
		);
	}

	#[Group('units')]
	public function testTheValuesAreParameters(): void
	{
		$queryBuilder = $this->connection->createQueryBuilder()->select('*')->from('t');
		new WhereApplier()->apply($queryBuilder, ['name' => "x' OR 1=1 --", Where::in('id', [1, 2]), Where::startsWith('mimetype', '50%_!')]);

		static::assertSame(['where0' => "x' OR 1=1 --", 'where1' => [1, 2], 'where2' => '50!%!_!!%'], $queryBuilder->getParameters());
		static::assertSame(ArrayParameterType::INTEGER, $queryBuilder->getParameterTypes()['where1']);
	}

	#[Group('units')]
	public function testAnEmptyListMatchesNothing(): void
	{
		static::assertSame('SELECT * FROM t WHERE 1 = 0', $this->sql([Where::in('id', [])]));
	}

	#[Group('units')]
	public function testWithoutConditionsThereIsNoWhere(): void
	{
		static::assertSame('SELECT * FROM t', $this->sql([]));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function noFieldNames(): array
	{
		return [
			'empty'            => [''],
			'an expression'    => ['id = 1 OR 1'],
			'a comment'        => ['id--'],
			'a quote'          => ["id'"],
			'three parts'      => ['db.t.id'],
			'a leading digit'  => ['1id'],
			'a space'          => ['user id'],
			'a placeholder'    => ['id:x'],
			'a percent sign'   => ['id%s'],
		];
	}

	#[Group('units')]
	#[DataProvider('noFieldNames')]
	public function testAFieldNameWhichIsNoFieldNameIsRefused(string $field): void
	{
		$this->expectException(InvalidArgumentException::class);
		Where::equals($field, 1);
	}

	#[Group('units')]
	#[DataProvider('noFieldNames')]
	public function testTheShortFormChecksTheFieldNameAsWell(string $field): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->sql([$field => 1]);
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function oldConditions(): array
	{
		return [
			'with operator' => [['checksum' => ['value' => 'abc', 'operator' => '=']]],
			'with compare'  => [['expires_at' => ['value' => '2026-01-01', 'compare' => '<=']]],
		];
	}

	/**
	 * The old form ['field' => ['value' => .., 'compare' => ..]] is no condition any more: it would have been
	 * read with defaults for every key which is missing or misspelt.
	 */
	#[Group('units')]
	#[DataProvider('oldConditions')]
	public function testAnArrayAsValueIsRefused(mixed $conditions): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->sql($conditions);
	}

	#[Group('units')]
	public function testAValueWithoutAFieldIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->sql([5]);
	}

	#[Group('units')]
	public function testTheEscapeOfLikeKeepsTheWildcardsAsText(): void
	{
		static::assertSame('100!%', Where::escapeLike('100%'));
		static::assertSame('a!_b', Where::escapeLike('a_b'));
		static::assertSame('x!!y', Where::escapeLike('x!y'));
	}
}
