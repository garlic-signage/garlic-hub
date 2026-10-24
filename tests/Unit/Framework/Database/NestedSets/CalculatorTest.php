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

use App\Framework\Database\NestedSets\Calculator;
use App\Framework\Exceptions\DatabaseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
	private Calculator $calculator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->calculator = new Calculator();
	}

	/**
	 * @throws DatabaseException
	 */
	#[Group('units')]
	#[DataProvider('lftPositionProvider')]
	public function testDetermineLgtPositionByRegion(string $region, int $expected): void
	{
		static::assertSame($expected, $this->calculator->determineLgtPositionByRegion($region, ['lft' => 5, 'rgt' => 10]));
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function lftPositionProvider(): array
	{
		return [
			'before the target'        => [Calculator::REGION_BEFORE, 5],
			'as last child of target'  => [Calculator::REGION_APPENDCHILD, 10],
			'after the target'         => [Calculator::REGION_AFTER, 11],
		];
	}

	#[Group('units')]
	public function testDetermineLgtPositionByRegionThrowsExceptionForInvalidRegion(): void
	{
		$this->expectException(DatabaseException::class);
		$this->expectExceptionMessage('Unknown region: invalid');

		$this->calculator->determineLgtPositionByRegion('invalid', ['lft' => 5, 'rgt' => 10]);
	}

	#[Group('units')]
	#[DataProvider('diffLevelProvider')]
	public function testCalculateDiffLevelByRegion(string $region, int $movedLevel, int $targetLevel, int $expected): void
	{
		static::assertSame($expected, $this->calculator->calculateDiffLevelByRegion($region, $movedLevel, $targetLevel));
	}

	/**
	 * A child lives one level below its parent, so appendChild is one level deeper than a sibling move.
	 *
	 * @return array<string, array{string, int, int, int}>
	 */
	public static function diffLevelProvider(): array
	{
		return [
			'before, target deeper'        => [Calculator::REGION_BEFORE, 3, 5, 2],
			'before, same level'           => [Calculator::REGION_BEFORE, 6, 6, 0],
			'before, target higher'        => [Calculator::REGION_BEFORE, 5, 3, -2],
			'after, target deeper'         => [Calculator::REGION_AFTER, 3, 7, 4],
			'after, negative moved level'  => [Calculator::REGION_AFTER, -2, 3, 5],
			'appendChild'                  => [Calculator::REGION_APPENDCHILD, 2, 4, 3],
			'appendChild, same level'      => [Calculator::REGION_APPENDCHILD, 4, 4, 1],
			'appendChild, far apart'       => [Calculator::REGION_APPENDCHILD, 1, 1000, 1000],
		];
	}

	#[Group('units')]
	public function testDetermineParentIdByRegionForAppendChildIsTheTargetItself(): void
	{
		static::assertSame(10, $this->calculator->determineParentIdByRegion(Calculator::REGION_APPENDCHILD, ['node_id' => 10, 'parent_id' => 5]));
	}

	#[Group('units')]
	#[DataProvider('siblingRegionProvider')]
	public function testDetermineParentIdByRegionForSiblingMovesIsTheParentOfTheTarget(string $region): void
	{
		static::assertSame(5, $this->calculator->determineParentIdByRegion($region, ['node_id' => 10, 'parent_id' => 5]));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function siblingRegionProvider(): array
	{
		return ['before' => [Calculator::REGION_BEFORE], 'after' => [Calculator::REGION_AFTER]];
	}

	#[Group('units')]
	public function testCalculateBeforeMoveSubTreeWithPositiveDistance(): void
	{
		$result = $this->calculator->calculateBeforeMoveSubTree(['lft' => 3, 'root_id' => 1], ['lft' => 7, 'root_id' => 1], 10, 4);

		static::assertSame(['distance' => 7, 'tmpPos' => 3, 'width' => 4], $result);
	}

	/**
	 * Moving to the left inside one tree: the gap made for the subtree pushes it to the right,
	 * so its temporary position and the distance are corrected by its width.
	 */
	#[Group('units')]
	public function testCalculateBeforeMoveSubTreeWithNegativeDistanceSameRoot(): void
	{
		$result = $this->calculator->calculateBeforeMoveSubTree(['lft' => 10, 'root_id' => 1], ['lft' => 3, 'root_id' => 1], 5, 4);

		static::assertSame(['distance' => -9, 'tmpPos' => 14, 'width' => 4], $result);
	}

	/**
	 * Another tree has no gap in the tree of the moved node, nothing to correct.
	 */
	#[Group('units')]
	public function testCalculateBeforeMoveSubTreeWithNegativeDistanceDifferentRoot(): void
	{
		$result = $this->calculator->calculateBeforeMoveSubTree(['lft' => 8, 'root_id' => 1], ['lft' => 5, 'root_id' => 2], 4, 5);

		static::assertSame(['distance' => -4, 'tmpPos' => 8, 'width' => 5], $result);
	}

	#[Group('units')]
	public function testCalculateBeforeMoveSubTreeWithZeroDistance(): void
	{
		$result = $this->calculator->calculateBeforeMoveSubTree(['lft' => 3, 'root_id' => 1], ['lft' => 3, 'root_id' => 1], 3, 2);

		static::assertSame(['distance' => 0, 'tmpPos' => 3, 'width' => 2], $result);
	}
}
