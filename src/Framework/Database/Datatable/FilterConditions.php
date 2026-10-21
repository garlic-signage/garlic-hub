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
use Doctrine\DBAL\ArrayParameterType;

/**
 * Stateless helpers to build the where conditions of a datatable.
 */
readonly class FilterConditions
{
	/**
	 * @return array{value: int|string, compare: string, logic: string, type: ArrayParameterType}
	 */
	public function whereClause(int|string $value, string $compare = '=', string $logic = 'AND', ArrayParameterType $type = ArrayParameterType::INTEGER): array
	{
		return ['value' => $value, 'compare' => $compare, 'logic' => $logic, 'type' => $type];
	}

	/**
	 * Raw SQL condition without bound parameter, e.g. for column arithmetics.
	 * Never pass user input here.
	 *
	 * @return array{expression: string}
	 */
	public function expression(string $sql): array
	{
		return ['expression' => $sql];
	}

	/**
	 * Default handling of a filter field: paging and sorting are ignored,
	 * user fields are searched in user_main, everything else with LIKE in the table.
	 *
	 * @param array<string, mixed> $parameter
	 * @return array<string, mixed>
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
				if (empty($parameter['value']))
					break;

				$value = '%'.str_replace('*', '%', $parameter['value']).'%';
				$where['user_main.username'] = $this->whereClause($value, 'LIKE');
				break;

			case 'company_id':
				if ((int) $parameter['value'] > 0)
					$where['user_main.'.$key] = $this->whereClause($parameter['value']);
				break;

			default:
				if (empty($parameter['value']))
					break;
				$value = '%'.str_replace('*', '%', (string) $parameter['value']).'%';
				$where[$table.'.'.$key] = $this->whereClause($value, 'LIKE');
		}
		return $where;
	}
}
