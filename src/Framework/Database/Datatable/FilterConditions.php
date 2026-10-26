<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Database\Datatable;

use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Database\Where;

/**
 * Stateless helper to build the where conditions of a datatable: the default handling of the filter fields.
 */
readonly class FilterConditions
{
	/**
	 * Default handling of a filter field: paging and sorting are ignored,
	 * user fields are searched in user_main, everything else with LIKE in the table.
	 *
	 * @param array<string, mixed> $parameter
	 * @return array<string, Where>
	 */
	public function forField(string $table, string $key, array $parameter): array
	{
		$where = [];
		switch ($key)
		{
			case BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE:
			case BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE:
			case BaseFilterParametersInterface::PARAMETER_SORT_COLUMN:
			case BaseFilterParametersInterface::PARAMETER_SORT_ORDER:
				break;

			case 'UID':
			case 'username':
				if ($this->isEmpty($parameter))
					break;

				// The owner field of the lists is an autocomplete: it sends the number of the user. A name is searched in the names.
				if ($key === 'UID' && ctype_digit((string) $parameter['value']) && (int) $parameter['value'] > 0)
					$where[$table . '.UID'] = Where::equals($table . '.UID', (int) $parameter['value']);
				else
					$where['user_main.username'] = Where::contains('user_main.username', (string) $parameter['value']);
				break;

			case 'company_id':
				if ((int) $parameter['value'] > 0)
					$where['user_main.'.$key] = Where::equals('user_main.'.$key, (int) $parameter['value']);
				break;

			default:
				if ($this->isEmpty($parameter))
					break;
				$where[$table.'.'.$key] = Where::contains($table.'.'.$key, (string) $parameter['value']);
		}
		return $where;
	}

	/**
	 * The text "0" is a search term. The integer 0 is the default of a select (e.g. "all") and no filter.
	 *
	 * @param array<string, mixed> $parameter
	 */
	private function isEmpty(array $parameter): bool
	{
		$value = $parameter['value'] ?? '';

		return empty($value) && $value !== '0';
	}
}
