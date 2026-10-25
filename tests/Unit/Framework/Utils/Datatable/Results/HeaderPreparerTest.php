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

namespace Tests\Unit\Framework\Utils\Datatable\Results;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\Results\Builder;
use App\Framework\Utils\Datatable\Results\HeaderPreparer;
use App\Framework\Utils\Datatable\Results\TranslatorManager;
use App\Framework\Utils\Datatable\UrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Utils\Datatable\FilterParametersFake;

/**
 * The header of a datatable with the real URL builder and the real translator manager. Only the Translator,
 * which needs locales and files, answers "<key>@<module>" for the modules it knows.
 */
class HeaderPreparerTest extends TestCase
{
	private HeaderPreparer $headerPreparer;

	protected function setUp(): void
	{
		parent::setUp();

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(
			static function (string $key, string $module): string
			{
				if ($module === 'unknown')
					throw new FrameworkException('no translation');

				return $key . '@' . $module;
			}
		);

		$this->headerPreparer = new HeaderPreparer(new TranslatorManager($translator), new UrlBuilder());
	}

	/**
	 * @return list<\App\Framework\Utils\Datatable\Results\HeaderField>
	 */
	private function fields(): array
	{
		$builder = new Builder();
		$builder->createField('name', true);
		$builder->createField('created', true);
		$builder->createField('actions', false);

		return $builder->getHeaderFields();
	}

	/**
	 * @param array<string,mixed> $input
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function header(array $input): array
	{
		$this->headerPreparer->configure(new FilterParametersFake($input), '/playlists', ['playlists', 'main']);

		return $this->headerPreparer->prepareTableHeader($this->fields());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAColumnWhichIsNotSortedOffersAscending(): void
	{
		$header = $this->header(['sort_column' => 'name', 'sort_order' => 'ASC']);

		static::assertSame(
			[
				'SORTABLE_ORDER'          => '◆',
				'SORT_CONTROL_NAME'       => 'created',
				'LINK_CONTROL_SORT_ORDER' => '/playlists?elements_page=1&sort_column=created&sort_order=asc&elements_per_page=10',
				'LANG_CONTROL_NAME'       => 'created@playlists',
			],
			$header[1]['if_sortable']
		);
	}

	/**
	 * The column which is sorted ascending offers descending, and the other way round: a click turns the order.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('sortedColumnProvider')]
	public function testTheSortedColumnOffersTheOtherOrder(string $current, string $arrow, string $offered): void
	{
		$header = $this->header(['sort_column' => 'name', 'sort_order' => $current]);

		static::assertSame($arrow, $header[0]['if_sortable']['SORTABLE_ORDER']);
		static::assertSame('/playlists?elements_page=1&sort_column=name&sort_order=' . $offered . '&elements_per_page=10', $header[0]['if_sortable']['LINK_CONTROL_SORT_ORDER']);
	}

	/**
	 * @return array<string, array{string, string, string}> order of the request, arrow, order of the link
	 */
	public static function sortedColumnProvider(): array
	{
		return [
			'ascending'  => ['ASC', '▼', 'desc'],
			'descending' => ['DESC', '▲', 'asc'],
			'lower case' => ['desc', '▲', 'asc'],
		];
	}

	/**
	 * Preparing the header only reads the parameters: the filter form which follows needs the real sort order.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('orderProvider')]
	public function testThePrepareOfTheHeaderDoesNotChangeTheSortOrder(string $order): void
	{
		$parameters = new FilterParametersFake(['sort_column' => 'name', 'sort_order' => $order]);
		$this->headerPreparer->configure($parameters, '/playlists', ['playlists']);

		$this->headerPreparer->prepareTableHeader($this->fields());

		static::assertSame($order, $parameters->getValueOfParameter('sort_order'));
		static::assertSame('name', $parameters->getValueOfParameter('sort_column'));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function orderProvider(): array
	{
		return ['ascending' => ['ASC'], 'descending' => ['DESC']];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheLinksKeepPageAndElementsPerPage(): void
	{
		$header = $this->header(['elements_page' => '3', 'elements_per_page' => '25', 'sort_column' => 'created', 'sort_order' => 'DESC']);

		static::assertSame(
			'/playlists?elements_page=3&sort_column=name&sort_order=asc&elements_per_page=25',
			$header[0]['if_sortable']['LINK_CONTROL_SORT_ORDER']
		);
		static::assertSame(
			'/playlists?elements_page=3&sort_column=created&sort_order=asc&elements_per_page=25',
			$header[1]['if_sortable']['LINK_CONTROL_SORT_ORDER']
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAFieldWhichIsNotSortableHasOnlyAName(): void
	{
		$header = $this->header([]);

		static::assertSame(['CONTROL_NAME' => 'actions', 'LANG_CONTROL_NAME_2' => 'actions@playlists'], $header[2]);
		static::assertArrayNotHasKey('if_sortable', $header[2]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testEveryFieldKeepsItsPlaceAndName(): void
	{
		$header = $this->header([]);

		static::assertSame(['name', 'created', 'actions'], array_column($header, 'CONTROL_NAME'));
		static::assertSame(['name', 'created'], array_column(array_column($header, 'if_sortable'), 'SORT_CONTROL_NAME'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheTextsAreSearchedInTheModulesInTheGivenOrder(): void
	{
		$this->headerPreparer->configure(new FilterParametersFake([]), '/playlists', ['unknown', 'main', 'playlists']);

		$header = $this->headerPreparer->prepareTableHeader($this->fields());

		static::assertSame('name@main', $header[0]['if_sortable']['LANG_CONTROL_NAME']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutFieldsThereIsNoHeader(): void
	{
		$this->headerPreparer->configure(new FilterParametersFake([]), '/playlists', ['playlists']);

		static::assertSame([], $this->headerPreparer->prepareTableHeader([]));
	}

	/**
	 * The page is configured for one datatable, a second configure() replaces the site and the parameters.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testConfigureAgainSwitchesToTheOtherSite(): void
	{
		$this->header([]);

		$this->headerPreparer->configure(new FilterParametersFake(['elements_per_page' => '50']), '/users', ['main']);
		$header = $this->headerPreparer->prepareTableHeader($this->fields());

		static::assertSame('/users?elements_page=1&sort_column=created&sort_order=asc&elements_per_page=50', $header[1]['if_sortable']['LINK_CONTROL_SORT_ORDER']);
	}

	/**
	 * A sortable field needs the parameters, so configure() has to come first.
	 *
	 * @throws CoreException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testASortableHeaderCanNotBePreparedBeforeConfigure(): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('HeaderPreparer::configure() has to be called before the sortable header is prepared.');

		$this->headerPreparer->prepareTableHeader($this->fields());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testFieldsWhichAreNotSortableNeedNoParameters(): void
	{
		$builder = new Builder();
		$builder->createField('actions', false);

		$header = $this->headerPreparer->prepareTableHeader($builder->getHeaderFields());

		static::assertSame('actions', $header[0]['CONTROL_NAME']);
	}
}
