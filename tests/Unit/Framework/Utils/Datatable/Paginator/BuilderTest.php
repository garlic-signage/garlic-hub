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

namespace Tests\Unit\Framework\Utils\Datatable\Paginator;

use App\Framework\Utils\Datatable\Paginator\Builder;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The links of the pager. A link is written as "name:page", the page which is shown has a * behind it.
 */
class BuilderTest extends TestCase
{
	private Builder $builder;

	protected function setUp(): void
	{
		parent::setUp();
		$this->builder = new Builder();
	}

	/**
	 * @return list<string>
	 */
	private function links(): array
	{
		return array_map(
			static fn(array $link): string => $link['name'] . ':' . $link['page'] . ($link['active'] ?? false ? '*' : ''),
			$this->builder->getPagerLinks()
		);
	}

	/**
	 * @return array<string, array{int, int, int, bool, bool, list<string>}> page, items per page, total, pager, shortened, links
	 */
	public static function pagerProvider(): array
	{
		return [
			'first page with pager'          => [1, 10, 50, true, true, ['1:1*', '2:2', '3:3', '4:4', '5:5', '›:2', '»:5']],
			'middle page with pager'         => [3, 10, 50, true, false, ['«:1', '‹:2', '1:1', '2:2', '3:3*', '4:4', '5:5', '›:4', '»:5']],
			'last page with pager'           => [5, 10, 50, true, true, ['«:1', '‹:4', '1:1', '2:2', '3:3', '4:4', '5:5*']],
			'second page with pager'         => [2, 10, 50, true, false, ['«:1', '‹:1', '1:1', '2:2*', '3:3', '4:4', '5:5', '›:3', '»:5']],
			'without pager'                  => [3, 10, 50, false, false, ['1:1', '2:2', '3:3*', '4:4', '5:5']],
			'no results'                     => [1, 10, 0, true, true, ['1:1*']],
			'a single page'                  => [1, 10, 7, true, true, ['1:1*']],
			'exactly full pages'             => [2, 10, 20, false, false, ['1:1', '2:2*']],
			'one item more than a page'      => [1, 10, 11, false, false, ['1:1*', '2:2']],
			'long list, shortened, at start' => [1, 10, 200, false, true, ['1:1*', '2:2', '3:3', '18:18', '19:19', '20:20']],
			'long list, shortened, middle'   => [10, 10, 200, false, true, ['1:1', '2:2', '3:3', '8:8', '9:9', '10:10*', '11:11', '12:12', '18:18', '19:19', '20:20']],
			'long list, shortened, at end'   => [20, 10, 200, false, true, ['1:1', '2:2', '3:3', '18:18', '19:19', '20:20*']],
			'long list, not shortened'       => [4, 10, 100, false, false, ['1:1', '2:2', '3:3', '4:4*', '5:5', '6:6', '7:7', '8:8', '9:9', '10:10']],
		];
	}

	/**
	 * @param list<string> $expected
	 */
	#[Group('units')]
	#[DataProvider('pagerProvider')]
	public function testBuildPagerLinks(int $page, int $perPage, int $total, bool $usePager, bool $shortened, array $expected): void
	{
		$this->builder->configure($page, $perPage, $total, $usePager, $shortened)->buildPagerLinks();

		static::assertSame($expected, $this->links());
	}

	#[Group('units')]
	public function testTheTypesOfALink(): void
	{
		$this->builder->configure(2, 10, 30, true, false)->buildPagerLinks();

		static::assertSame(['name' => '«', 'page' => 1], $this->builder->getPagerLinks()[0]);
		static::assertSame(['name' => '2', 'page' => 2, 'active' => true], $this->builder->getPagerLinks()[3]);
		static::assertSame(['name' => '3', 'page' => 3, 'active' => false], $this->builder->getPagerLinks()[4]);
	}

	/**
	 * @return array<string, array{int, int, int, list<string>}>
	 */
	public static function invalidValuesProvider(): array
	{
		return [
			'page zero is page one'      => [0, 1, 1, ['1:1*']],
			'negative page'              => [-4, 10, 20, ['1:1*', '2:2']],
			'items per page zero'        => [1, 0, 3, ['1:1*', '2:2', '3:3']],
			'negative items per page'    => [2, -5, 2, ['1:1', '2:2*']],
			'negative total'             => [1, 10, -50, ['1:1*']],
		];
	}

	/**
	 * Values which make no sense are corrected: there is always at least one page.
	 *
	 * @param list<string> $expected
	 */
	#[Group('units')]
	#[DataProvider('invalidValuesProvider')]
	public function testInvalidValuesAreCorrected(int $page, int $perPage, int $total, array $expected): void
	{
		$this->builder->configure($page, $perPage, $total, false, false)->buildPagerLinks();

		static::assertSame($expected, $this->links());
	}

	#[Group('units')]
	public function testAPageBehindTheLastOneHasNoActiveLink(): void
	{
		$this->builder->configure(9, 10, 30, true, false)->buildPagerLinks();

		static::assertSame(['«:1', '‹:8', '1:1', '2:2', '3:3'], $this->links());
	}

	#[Group('units')]
	public function testBuildingAgainStartsANewList(): void
	{
		$this->builder->configure(1, 10, 30, false, false)->buildPagerLinks();
		$this->builder->configure(1, 10, 10, false, false)->buildPagerLinks();

		static::assertSame(['1:1*'], $this->links());
	}

	#[Group('units')]
	public function testCreateDropDown(): void
	{
		$result = $this->builder->createDropDown(10, 100, 10);

		static::assertSame($this->builder, $result);
		static::assertSame(['min' => 10, 'max' => 100, 'steps' => 10], $this->builder->getDropDownSettings());
	}

	/**
	 * Nothing is built before configure() and buildPagerLinks().
	 */
	#[Group('units')]
	public function testTheLinksAreNotThereBeforeTheyAreBuilt(): void
	{
		$this->expectException(Error::class);

		$this->builder->getPagerLinks();
	}

	#[Group('units')]
	public function testTheListCanNotBeBuiltWithoutConfiguration(): void
	{
		$this->expectException(Error::class);

		$this->builder->buildPagerLinks();
	}
}
