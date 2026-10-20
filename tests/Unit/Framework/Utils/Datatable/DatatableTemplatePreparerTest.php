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

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatatableTemplatePreparerTest extends TestCase
{
	private DatatableTemplatePreparer $preparer;
	private Translator&MockObject $translatorMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);
		$this->preparer = new DatatableTemplatePreparer($this->translatorMock);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPreparerUITemplateReturnsCorrectStructure(): void
	{
		$datalistSections = [
			'title' => 'Test Title',
			'additional_css' => ['styles.css'],
			'footer_modules' => ['footer1', 'footer2'],
			'template_name' => 'template1',
			'module_name' => 'module1',
			'filter_elements' => [
				'hidden' => ['hidden_field_1'],
				'visible' => ['visible_field_1']
			],
			'sort' => [
				'column' => 'col1',
				'order' => 'asc'
			],
			'page' => [
				'current' => 1,
				'num_elements' => 10
			],
			'pagination_dropdown' => [5, 10, 15],
			'has_add' => true,
			'results_count' => 100,
			'pagination_links' => '<a href="#">1</a>',
			'results_header' => ['header1', 'header2'],
			'results_list' => ['result1', 'result2']
		];

		$this->translatorMock->method('translate')->willReturnMap([
			['filter', 'main', [], 'Filter'],
			['elements_per_page', 'main', [], 'Elements per page']
		]);

		$this->translatorMock
			->method('translateWithPlural')
			->willReturn('100 results found.');

		$result = $this->preparer->preparerUITemplate($datalistSections);

		static::assertEquals('Test Title', $result['main_layout']['LANG_PAGE_TITLE']);
		static::assertEquals(['styles.css'], $result['main_layout']['additional_css']);
		static::assertEquals(['footer1', 'footer2'], $result['main_layout']['footer_modules']);

		static::assertEquals('template1', $result['this_layout']['template']);
		static::assertEquals('/module1', $result['this_layout']['data']['FORM_ACTION']);
		static::assertEquals('Filter', $result['this_layout']['data']['LANG_ELEMENTS_FILTER']);
		static::assertEquals('asc', $result['this_layout']['data']['SORT_ORDER']);
		static::assertEquals('100 results found.', $result['this_layout']['data']['LANG_COUNT_SEARCH_RESULTS']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPreparerUITemplateHandlesTranslationErrors(): void
	{
		$datalistSections = [
			'title' => 'Test Title',
			'additional_css' => ['styles.css'],
			'footer_modules' => ['footer1', 'footer2'],
			'template_name' => 'template1',
			'module_name' => 'module1',
			'filter_elements' => [
				'hidden' => ['hidden_field_1'],
				'visible' => ['visible_field_1']
			],
			'sort' => [
				'column' => 'col1',
				'order' => 'asc'
			],
			'page' => [
				'current' => 1,
				'num_elements' => 10
			],
			'pagination_dropdown' => [5, 10, 15],
			'has_add' => true,
			'results_count' => 100,
			'pagination_links' => '<a href="#">1</a>',
			'results_header' => ['header1', 'header2'],
			'results_list' => ['result1', 'result2']
		];

		$this->translatorMock
			->method('translate')
			->willThrowException(new \Exception('Translation Error'));

		$this->expectException(\Exception::class);

		$this->preparer->preparerUITemplate($datalistSections);
	}
}