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

namespace App\Framework\Utils\Forms;

/**
 * Class responsible for preparing templates for user interface forms.
 * It organizes and returns both the main layout and current layout based on provided data sections.
 */
class FormTemplatePreparer
{
	public function __construct()
	{
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array{main_layout: array<string,mixed>, this_layout: array<string,mixed>}
	 */
	public function prepareUITemplate(array $dataSections): array
	{
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $dataSections['title'],
				'additional_css'  => $dataSections['additional_css'],
				'footer_modules'   => $dataSections['footer_modules']
			],
			'this_layout' => [
				'template' => $dataSections['template_name'], // Template-name
				'data' => [
					'LANG_PAGE_HEADER' => $dataSections['title'],
					'FORM_ACTION' => $dataSections['form_action'],
					'LANG_FORM_EXPLANATION' => $dataSections['explanations'] ?? '',
					'element_hidden' => $dataSections['hidden'],
					'form_element' => $dataSections['visible'],
					'form_button' => [
						[
							'ELEMENT_BUTTON_TYPE' => 'submit',
							'ELEMENT_BUTTON_NAME' => 'standardSubmit',
							'LANG_ELEMENT_BUTTON' => $dataSections['save_button_label'],
						]
					],
					'additional_buttons' => $dataSections['additional_buttons'] ?? []
				]
			]
		];
	}
}