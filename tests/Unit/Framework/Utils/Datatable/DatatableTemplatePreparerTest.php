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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Framework\Localization\Translate\MemoryCache;
use Tests\Unit\Framework\Localization\Translate\RecordingTranslationLoader;

/**
 * The page of a datatable for the template, with the real Translator and plural formatter.
 */
class DatatableTemplatePreparerTest extends TestCase
{
	private function preparer(string $countText = '{count, plural, one {1 Template} other {# Templates}}'): DatatableTemplatePreparer
	{
		$locales = $this->createStub(Locales::class);
		$locales->method('getLanguageCode')->willReturn('en');

		$loader = new RecordingTranslationLoader(['en' => [
			'main'    => ['filter' => 'Filter', 'elements_per_page' => 'Elements per page'],
			'module1' => ['count_search_results' => $countText],
		]]);

		return new DatatableTemplatePreparer(new Translator($locales, $loader, new MessageFormatterFactory(), new MemoryCache()));
	}

	/**
	 * @return array<string,mixed>
	 */
	private function sections(int $count = 100): array
	{
		return [
			'title'               => 'Test Title',
			'additional_css'      => ['styles.css'],
			'footer_modules'      => ['footer1', 'footer2'],
			'template_name'       => 'generic/datatable',
			'module_name'         => 'module1',
			'filter_elements'     => ['hidden' => [['HIDDEN_HTML_ELEMENT' => '<input type="hidden">']], 'visible' => [['HTML_ELEMENT_ID' => 'name']]],
			'sort'                => ['column' => 'col1', 'order' => 'DESC'],
			'page'                => ['current' => 2, 'num_elements' => 10],
			'pagination_dropdown' => [['ELEMENTS_PER_PAGE_VALUE' => 10]],
			'has_add'             => ['ELEMENTS_ADD_LINK' => '/module1/add'],
			'results_count'       => $count,
			'pagination_links'    => [['ELEMENTS_PAGENAME' => '1']],
			'results_header'      => [['CONTROL_NAME' => 'name']],
			'results_list'        => [['UNIT_ID' => 4]],
		];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPreparerUITemplateReturnsTheStructureOfThePage(): void
	{
		$result = $this->preparer()->preparerUITemplate($this->sections());

		static::assertSame(
			[
				'main_layout' => [
					'LANG_PAGE_TITLE' => 'Test Title',
					'additional_css'  => ['styles.css'],
					'footer_modules'  => ['footer1', 'footer2'],
				],
				'this_layout' => [
					'template' => 'generic/datatable',
					'data'     => [
						'LANG_PAGE_HEADER'          => 'Test Title',
						'FORM_ACTION'               => '/module1',
						'element_hidden'            => [['HIDDEN_HTML_ELEMENT' => '<input type="hidden">']],
						'form_element'              => [['HTML_ELEMENT_ID' => 'name']],
						'LANG_ELEMENTS_FILTER'      => 'Filter',
						'SORT_COLUMN'               => 'col1',
						'SORT_ORDER'                => 'DESC',
						'ELEMENTS_PAGE'             => 2,
						'ELEMENTS_PER_PAGE'         => 10,
						'elements_per_page'         => [['ELEMENTS_PER_PAGE_VALUE' => 10]],
						'form_button'               => [['ELEMENT_BUTTON_TYPE' => 'submit', 'ELEMENT_BUTTON_NAME' => 'submit']],
						'has_add'                   => ['ELEMENTS_ADD_LINK' => '/module1/add'],
						'LANG_ELEMENTS_PER_PAGE'    => 'Elements per page',
						'LANG_COUNT_SEARCH_RESULTS' => '100 Templates',
						'elements_pager'            => [['ELEMENTS_PAGENAME' => '1']],
						'elements_result_header'    => [['CONTROL_NAME' => 'name']],
						'elements_results'          => [['UNIT_ID' => 4]],
					],
				],
			],
			$result
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('countProvider')]
	public function testTheCountTextFollowsThePlural(int $count, string $expected): void
	{
		$result = $this->preparer()->preparerUITemplate($this->sections($count));

		static::assertSame($expected, $result['this_layout']['data']['LANG_COUNT_SEARCH_RESULTS']);
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function countProvider(): array
	{
		return ['none' => [0, '0 Templates'], 'one' => [1, '1 Template'], 'many' => [42, '42 Templates']];
	}

	/**
	 * The number is part of the formatted text already. A sign like % in a translation is no placeholder.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAPercentSignInTheCountTextDoesNotBreakIt(): void
	{
		$preparer = $this->preparer('{count, plural, one {1 hit (100% match)} other {# hits (100% match)}}');

		$result = $preparer->preparerUITemplate($this->sections(3));

		static::assertSame('3 hits (100% match)', $result['this_layout']['data']['LANG_COUNT_SEARCH_RESULTS']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testWithoutATextForTheCountTheTextIsEmpty(): void
	{
		$preparer = new DatatableTemplatePreparer($this->translatorWithoutCountText());

		$result = $preparer->preparerUITemplate($this->sections());

		static::assertSame('', $result['this_layout']['data']['LANG_COUNT_SEARCH_RESULTS']);
	}

	private function translatorWithoutCountText(): Translator
	{
		$locales = $this->createStub(Locales::class);
		$locales->method('getLanguageCode')->willReturn('en');
		$loader = new RecordingTranslationLoader(['en' => [
			'main'    => ['filter' => 'Filter', 'elements_per_page' => 'Elements per page'],
			'module1' => ['other' => 'x'],
		]]);

		return new Translator($locales, $loader, new MessageFormatterFactory(), new MemoryCache());
	}

	/**
	 * A language file which is missing is not hidden.
	 *
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAMissingLanguageFileThrows(): void
	{
		$sections                = $this->sections();
		$sections['module_name'] = 'no_such_module';

		$this->expectException(CoreException::class);

		$this->preparer()->preparerUITemplate($sections);
	}
}
