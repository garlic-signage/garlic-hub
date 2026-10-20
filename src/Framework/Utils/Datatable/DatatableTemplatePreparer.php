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

namespace App\Framework\Utils\Datatable;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;

/**
 * Class DatatableTemplatePreparer
 *
 * This class is responsible for formatting data into a structured template for use in a datatable functionality.
 * It processes various settings and properties of the $datalistSections array and transforms them into
 * layout-specific arrays suitable for rendering UI elements.
 *
 * @see templates/generic/datatable.mustache
 */
class DatatableTemplatePreparer
{

	private Translator $translator;

	public function __construct(Translator $translator)
	{
		$this->translator = $translator;
	}

	/**
	 * @param array<string,mixed> $dataSections
	 * @return array{main_layout: array<string,mixed>, this_layout: array<string,mixed>}
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function preparerUITemplate(array $dataSections): array
	{
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $dataSections['title'],
				'additional_css'  => $dataSections['additional_css'],
				'footer_modules'   => $dataSections['footer_modules']
			],
			'this_layout' => [
				'template' => $dataSections['template_name'],
				'data' => [
					'LANG_PAGE_HEADER'     => $dataSections['title'],
					'FORM_ACTION'          => '/'. $dataSections['module_name'],
					'element_hidden'       => $dataSections['filter_elements']['hidden'],
					'form_element'         => $dataSections['filter_elements']['visible'],
					'LANG_ELEMENTS_FILTER' => $this->translator->translate('filter', 'main'),
					'SORT_COLUMN'          => $dataSections['sort']['column'],
					'SORT_ORDER'           => $dataSections['sort']['order'],
					'ELEMENTS_PAGE'        => $dataSections['page']['current'],
					'ELEMENTS_PER_PAGE'    => $dataSections['page']['num_elements'],
					'elements_per_page'    => $dataSections['pagination_dropdown'],
					'form_button' => [
						[
							'ELEMENT_BUTTON_TYPE' => 'submit',
							'ELEMENT_BUTTON_NAME' => 'submit',
						]
					],
					'has_add'                     => $dataSections['has_add'],
					'LANG_ELEMENTS_PER_PAGE'      => $this->translator->translate('elements_per_page', 'main'),
					'LANG_COUNT_SEARCH_RESULTS'   => sprintf($this->translator->translateWithPlural('count_search_results', $dataSections['module_name'], $dataSections['results_count']), $dataSections['results_count']),
					'elements_pager'              => $dataSections['pagination_links'],
					'elements_result_header'      => $dataSections['results_header'],
					'elements_results'            => $dataSections['results_list']
				]
			]
		];
	}
}