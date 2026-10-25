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


namespace Tests\Unit\Framework\Utils\Forms;

use App\Framework\Utils\Forms\FormTemplatePreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class FormTemplatePreparerTest extends TestCase
{
	private FormTemplatePreparer $formTemplatePreparer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->formTemplatePreparer = new FormTemplatePreparer();
	}

	#[Group('units')]
	public function testPrepareUITemplateReturnsCorrectStructure(): void
	{
		$dataSections = [
			'title' => 'Sample Title',
			'additional_css' => ['style.css'],
			'footer_modules' => ['module.js'],
			'template_name' => 'form_template',
			'form_action' => '/submit-form',
			'hidden' => ['hidden_field' => 'value'],
			'visible' => ['field1' => 'value1', 'field2' => 'value2'],
			'save_button_label' => 'Save Changes',
		];

		$expected = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Sample Title',
				'additional_css' => ['style.css'],
				'footer_modules' => ['module.js'],
			],
			'this_layout' => [
				'template' => 'form_template',
				'data' => [
					'LANG_PAGE_HEADER' => 'Sample Title',
					'FORM_ACTION' => '/submit-form',
					'LANG_FORM_EXPLANATION' => '',
					'element_hidden' => ['hidden_field' => 'value'],
					'form_element' => ['field1' => 'value1', 'field2' => 'value2'],
					'form_button' => [
						[
							'ELEMENT_BUTTON_TYPE' => 'submit',
							'ELEMENT_BUTTON_NAME' => 'standardSubmit',
							'LANG_ELEMENT_BUTTON' => 'Save Changes',
						],
					],
					'additional_buttons' => []
				],
			],
		];

		$result = $this->formTemplatePreparer->prepareUITemplate($dataSections);

		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testPrepareUITemplateHandlesEmptyData(): void
	{
		$dataSections = [
			'title' => '',
			'additional_css' => [],
			'footer_modules' => [],
			'template_name' => '',
			'form_action' => '',
			'hidden' => [],
			'visible' => [],
			'save_button_label' => '',
		];

		$expected = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => '',
				'additional_css' => [],
				'footer_modules' => [],
			],
			'this_layout' => [
				'template' => '',
				'data' => [
					'LANG_PAGE_HEADER' => '',
					'FORM_ACTION' => '',
					'LANG_FORM_EXPLANATION' => '',
					'element_hidden' => [],
					'form_element' => [],
					'form_button' => [
						[
							'ELEMENT_BUTTON_TYPE' => 'submit',
							'ELEMENT_BUTTON_NAME' => 'standardSubmit',
							'LANG_ELEMENT_BUTTON' => '',
						],
					],
					'additional_buttons' => []
				],
			],
		];

		$result = $this->formTemplatePreparer->prepareUITemplate($dataSections);

		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testOptionalPartsAreTaken(): void
	{
		$result = $this->formTemplatePreparer->prepareUITemplate([
			'title'             => 'Edit playlist',
			'additional_css'    => [],
			'footer_modules'    => [],
			'template_name'     => 'generic/edit',
			'form_action'       => '/playlists/edit/5',
			'hidden'            => [],
			'visible'           => [],
			'save_button_label' => 'Save',
			'explanations'      => 'Change the name of the playlist.',
			'additional_buttons' => [['ADDITIONAL_BUTTON_TYPE' => 'button', 'ADDITIONAL_BUTTON_NAME' => 'cancel', 'LANG_ADDITIONAL_BUTTON' => 'Cancel']],
		]);

		static::assertSame('Change the name of the playlist.', $result['this_layout']['data']['LANG_FORM_EXPLANATION']);
		static::assertSame(
			[['ADDITIONAL_BUTTON_TYPE' => 'button', 'ADDITIONAL_BUTTON_NAME' => 'cancel', 'LANG_ADDITIONAL_BUTTON' => 'Cancel']],
			$result['this_layout']['data']['additional_buttons']
		);
	}

	/**
	 * The title is the title of the page and the header of the form.
	 */
	#[Group('units')]
	public function testTheTitleIsPageTitleAndHeader(): void
	{
		$result = $this->formTemplatePreparer->prepareUITemplate([
			'title' => 'Edit user', 'additional_css' => [], 'footer_modules' => [], 'template_name' => 'generic/edit',
			'form_action' => '/', 'hidden' => [], 'visible' => [], 'save_button_label' => 'Save',
		]);

		static::assertSame('Edit user', $result['main_layout']['LANG_PAGE_TITLE']);
		static::assertSame('Edit user', $result['this_layout']['data']['LANG_PAGE_HEADER']);
		static::assertSame('generic/edit', $result['this_layout']['template']);
	}
}
