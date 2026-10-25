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

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\Paginator\Builder;
use App\Framework\Utils\Datatable\Paginator\Preparer;
use App\Framework\Utils\Datatable\UrlBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Utils\Datatable\FilterParametersFake;

/**
 * The links and the dropdown of the pager with the real Builder, URL builder and filter parameters.
 */
class PreparerTest extends TestCase
{
	private Preparer $preparer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->preparer = new Preparer(new UrlBuilder());
	}

	/**
	 * @param array<string,mixed> $input
	 * @throws ModuleException
	 */
	private function configure(array $input): void
	{
		$this->preparer->setSite('/playlists')->setBaseFilter(new FilterParametersFake($input));
	}

	/**
	 * @return list<array{name: string, page: int, active?: bool}>
	 */
	private function links(int $page, int $perPage, int $total, bool $usePager = true, bool $shortened = false): array
	{
		return (new Builder())->configure($page, $perPage, $total, $usePager, $shortened)->buildPagerLinks()->getPagerLinks();
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testEveryLinkHasUrlNameAndNumber(): void
	{
		$this->configure(['elements_page' => '2', 'sort_column' => 'created', 'sort_order' => 'DESC', 'elements_per_page' => '10']);

		$links = $this->preparer->prepareLinks($this->links(2, 10, 30));

		static::assertSame(
			[
				'ELEMENTS_PAGELINK'    => '/playlists?elements_page=1&sort_column=created&sort_order=DESC&elements_per_page=10',
				'ELEMENTS_PAGENAME'    => '«',
				'ELEMENTS_PAGENUMBER'  => 1,
				'ELEMENTS_ACTIVE_PAGE' => '',
			],
			$links[0]
		);
		static::assertSame(['«', '‹', '1', '2', '3', '›', '»'], array_column($links, 'ELEMENTS_PAGENAME'));
		static::assertSame([1, 1, 1, 2, 3, 3, 3], array_column($links, 'ELEMENTS_PAGENUMBER'));
	}

	/**
	 * Only the page which is shown is marked, the other numbers and the arrows are not.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testOnlyTheCurrentPageIsMarkedAsActive(): void
	{
		$this->configure(['elements_page' => '2']);

		$links = $this->preparer->prepareLinks($this->links(2, 10, 60));

		$active = [];
		foreach ($links as $link)
			$active[$link['ELEMENTS_PAGENAME']] = $link['ELEMENTS_ACTIVE_PAGE'];

		static::assertSame(
			['«' => '', '‹' => '', '1' => '', '2' => 'active_page', '3' => '', '4' => '', '5' => '', '6' => '', '›' => '', '»' => ''],
			$active
		);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheLinksKeepSortingAndElementsPerPage(): void
	{
		$this->configure(['sort_column' => 'created', 'sort_order' => 'DESC', 'elements_per_page' => '25']);

		$links = $this->preparer->prepareLinks($this->links(1, 25, 60, false));

		static::assertSame(
			['/playlists?elements_page=1&sort_column=created&sort_order=DESC&elements_per_page=25', '/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=25', '/playlists?elements_page=3&sort_column=created&sort_order=DESC&elements_per_page=25'],
			array_column($links, 'ELEMENTS_PAGELINK')
		);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testNoLinksGiveNoData(): void
	{
		$this->configure([]);

		static::assertSame([], $this->preparer->prepareLinks([]));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDropdownHasAnOptionPerStep(): void
	{
		$this->configure(['elements_per_page' => '20']);

		$options = $this->preparer->prepareDropdown(['min' => 10, 'max' => 30, 'steps' => 10]);

		static::assertSame([10, 20, 30], array_column($options, 'ELEMENTS_PER_PAGE_VALUE'));
		static::assertSame([10, 20, 30], array_column($options, 'ELEMENTS_PER_PAGE_NAME'));
		static::assertSame(['', 'selected', ''], array_column($options, 'ELEMENTS_PER_PAGE_SELECTED'));
	}

	/**
	 * Every option has its own link: the page, the sorting and the number of this option.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testEveryOptionHasALinkWithItsOwnNumber(): void
	{
		$this->configure(['elements_page' => '2', 'elements_per_page' => '20', 'sort_column' => 'created', 'sort_order' => 'DESC']);

		$options = $this->preparer->prepareDropdown(['min' => 10, 'max' => 30, 'steps' => 10]);

		static::assertSame(
			[
				'/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=10',
				'/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=20',
				'/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=30',
			],
			array_column($options, 'ELEMENTS_PER_PAGE_DATA_LINK')
		);
	}

	/**
	 * The dropdown works without the links being prepared before.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDropdownNeedsNoLinksBefore(): void
	{
		$this->configure([]);

		$options = $this->preparer->prepareDropdown(['min' => 10, 'max' => 10, 'steps' => 10]);

		static::assertCount(1, $options);
		static::assertSame('selected', $options[0]['ELEMENTS_PER_PAGE_SELECTED']);
	}

	/**
	 * A step of 0 would never end.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAStepOfZeroDoesNotLoopForever(): void
	{
		$this->configure(['elements_per_page' => '10']);

		$options = $this->preparer->prepareDropdown(['min' => 10, 'max' => 12, 'steps' => 0]);

		static::assertSame([10, 11, 12], array_column($options, 'ELEMENTS_PER_PAGE_VALUE'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSettersAreChainable(): void
	{
		static::assertSame($this->preparer, $this->preparer->setSite('/x'));
		static::assertSame($this->preparer, $this->preparer->setBaseFilter(new FilterParametersFake([])));
	}
}
