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

namespace App\Framework\Database\BaseRepositories;

use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use Doctrine\DBAL\Exception;

/**
 * A base class for filtering operations, extending the SQLBase class
 * and providing methods for complex data retrieval based on filtering criteria.
 */
abstract class FilterBase extends SqlBase
{
	use CrudTraits, FindOperationsTrait;

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return int
	 * @throws Exception
	 */
	public function countAllFiltered(array $fields): int
	{
		$where = $this->prepareWhereForFiltering($fields);
		$join  = $this->prepareJoin();
		return $this->countAllBy($where, $join);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findAllFiltered(array $fields): array
	{
		$selects  = $this->prepareSelectFilteredForUser();
		$where 	  = $this->prepareWhereForFiltering($fields);
		$orderBy  = [$this->prepareOrderBy($fields)];
		$join     = $this->prepareJoin();
		$limit    = $this->determineLimit(
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE]['value'],
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE]['value']
		);

		return $this->findAllByWithFields($selects, $where, $join, $limit, '', $orderBy);
	}

	/**
	 * @param int[] $companyIds
	 * @param array<string, array<string, mixed>> $fields
	 * @throws Exception
	 */
	public function countAllFilteredByUIDCompanyReseller(array $companyIds, array $fields, int $UID): int
	{
		$join  = $this->prepareJoin();
		$where = $this->buildRestrictedWhereForCountAndFindSearch($companyIds, $fields, $UID);
		return $this->countAllBy($where, $join);
	}

	/**
	 * @param int[] $companyIds
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findAllFilteredByUIDCompanyReseller(array $companyIds, array $fields, int $UID): array
	{
		$selects = $this->prepareSelectFiltered();
		$where   = $this->buildRestrictedWhereForCountAndFindSearch($companyIds,  $fields, $UID);
		$join    = $this->prepareJoin();
		$orderBy = [$this->prepareOrderBy($fields)];
		$limit    = $this->determineLimit(
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE]['value'],
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE]['value']
		);

		return $this->findAllByWithFields($selects, $where, $join, $limit, '', $orderBy);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @throws Exception
	 */
	public function countAllFilteredByUID(array $fields, int $UID): int
	{
		$where = $this->prepareWhereForFiltering($fields);
		$where[$this->table.'.UID'] = $this->generateWhereClause($UID);

		return $this->countAllBy($where);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findAllFilteredByUID(array $fields, int $UID): array
	{
		$selects = $this->prepareSelectFiltered();
		$where   = $this->prepareWhereForFiltering($fields);
		$where[$this->table.'.UID'] = $this->generateWhereClause($UID);
		$orderBy = [$this->prepareOrderBy($fields)];
		$join    = $this->prepareUserJoin();
		$limit    = $this->determineLimit(
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE]['value'],
			$fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE]['value']
		);

		return $this->findAllByWithFields($selects, $where, $join, $limit, '', $orderBy);
	}

	/**
	 * @param int[] $companyIds
	 * @param array<string, array<string, mixed>> $searchFields
	 * @return array<string,mixed>
	 */
	private function buildRestrictedWhereForCountAndFindSearch(array $companyIds, array $searchFields, int $UID): array
	{
		$where                      = $this->prepareWhereForFiltering($searchFields);
		$where[$this->table.'.UID'] = $this->generateWhereClause($UID);

		if (!empty($companyIds))
			$where['user_main.company_id'] = $this->generateWhereClause(implode(',', $companyIds), 'IN', 'OR');

		return $where;
	}

	/**
	 * @return array<string, string>
	 */
	abstract protected function prepareJoin(): array;
	/**
	 * @return array<string, string>
	 */
	abstract protected function prepareUserJoin(): array;

	/**
	 * @return string[]
	 */
	abstract protected function prepareSelectFiltered(): array;

	/**
	 * @return string[]
	 */
	abstract protected function prepareSelectFilteredForUser(): array;

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @param bool $useUserMain
	 * @return array<string,mixed>
	 */
	protected function prepareOrderBy(array $fields, bool $useUserMain = true): array
	{
		// no sort column
		if (!array_key_exists(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, $fields) ||
			empty($fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]) ||
			!array_key_exists('value', $fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]))
			return [];

		// validate
		// No Ordering uses default ASC
		$sort_order = (array_key_exists(BaseFilterParametersInterface::PARAMETER_SORT_ORDER, $fields)) ? $fields[BaseFilterParametersInterface::PARAMETER_SORT_ORDER]['value'] : 'ASC';

		// default when wrong order command
		if (strcasecmp($sort_order, 'desc') != 0 && strcasecmp($sort_order, 'asc') != 0)
			$sort_order = 'ASC';

		// sort by user
		if ($fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]['value'] == 'UID' ||
			$fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]['value'] == 'username')
		{
			$table = ($useUserMain === true) ?  'user_main.' : '';
			return ['sort' => $table . 'username', 'order' => $sort_order];
		}

		return ['sort' => $this->table.'.'.$fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]['value'], 'order' => $sort_order];
	}

	/**
	 * @param array<string, array<string, mixed>> $filterFields
	 * @return array<string,string>
	 */
	abstract protected function prepareWhereForFiltering(array $filterFields): array;

	/**
	 * @param  array<string,mixed> $parameter
	 * @return array<string,mixed>
	 */
	protected function determineWhereForFiltering(string $key, array $parameter): array
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
				$where['user_main.username'] = $this->generateWhereClause($value, 'LIKE');
				break;

			case 'company_id':
				if ((int) $parameter['value'] > 0)
					$where['user_main.'.$key] = $this->generateWhereClause($parameter['value']);
				break;

			default:
				if (empty($parameter['value']))
					break;
				$value = '%'.str_replace('*', '%', $parameter['value']).'%';
				$where[$this->table.'.'.$key] = $this->generateWhereClause($value, 'LIKE');
		}
		return $where;
	}

}