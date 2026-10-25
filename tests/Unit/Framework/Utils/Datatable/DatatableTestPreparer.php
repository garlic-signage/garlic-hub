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

use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\FormParameters\BaseFilterParameters;

/**
 * A datatable preparer of a module: the cells of the rows, made with the body preparer.
 */
class DatatableTestPreparer extends AbstractDatatablePreparer
{
	public function __construct(PrepareService $prepareService, BaseFilterParameters $parameters, Translator $translator)
	{
		parent::__construct('testmodule', $prepareService, $parameters, $translator);
	}

	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach ($currentFilterResults as $row)
		{
			$list = ['UNIT_ID' => $row['template_id']];
			foreach ($fields as $field)
			{
				$key      = $field->getName();
				$element  = ['CONTROL_NAME_BODY' => $key];
				if ($key === 'name')
					$element['is_link'] = $this->prepareService->getBodyPreparer()->formatLink((string) $row['name'], 'Open: ' . $row['name'], '/templates/' . $row['template_id'], (string) $row['template_id']);
				else
					$element['is_text'] = $this->prepareService->getBodyPreparer()->formatText((string) ($row[$key] ?? ''));

				$list['elements_result_element'][] = $element;
			}
			$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction('Edit', 'edit', (string) $row['template_id'], 'pencil');
			$body[]               = $list;
		}

		return $body;
	}
}
