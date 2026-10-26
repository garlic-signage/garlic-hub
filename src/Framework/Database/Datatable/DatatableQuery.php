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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Executes the count and find queries of a datatable for the given FilterDefinition.
 *
 * Stateless, the definition is passed with every call, so one instance serves all modules.
 */
readonly class DatatableQuery
{
	public function __construct(private Connection $connection, private FilterConditions $conditions)
	{
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @throws Exception
	 */
	public function countForModuleAdmin(FilterDefinition $definition, array $fields): int
	{
		$queryBuilder = $this->buildQuery('COUNT(1)', $definition->table(), $definition->whereForFiltering($fields), $definition->joins());

		return $this->count($queryBuilder);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findForModuleAdmin(FilterDefinition $definition, array $fields): array
	{
		$selects   = $definition->selectsWithUser();
		$selects[] = "'moduleadmin'";
		$queryBuilder = $this->buildQuery(implode(', ', $selects), $definition->table(), $definition->whereForFiltering($fields), $definition->joins());

		return $this->find($queryBuilder, $definition->table(), $fields);
	}

	/**
	 * Prepared for reseller ability of the CMS.
	 * Shows the own records plus the records of the managed companies.
	 *
	 * @param int[] $companyIds
	 * @param array<string, array<string, mixed>> $fields
	 * @throws Exception
	 */
	public function countForReseller(FilterDefinition $definition, array $companyIds, array $fields, int $UID): int
	{
		$queryBuilder = $this->buildQuery('COUNT(1)', $definition->table(), $definition->whereForFiltering($fields), $definition->joins());
		$this->applyResellerScope($queryBuilder, $definition->table(), $companyIds, $UID);

		return $this->count($queryBuilder);
	}

	/**
	 * Prepared for reseller ability of the CMS.
	 *
	 * @param int[] $companyIds
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findForReseller(FilterDefinition $definition, array $companyIds, array $fields, int $UID): array
	{
		$selects   = $definition->selects();
		$selects[] = "'subadmin'";
		$queryBuilder = $this->buildQuery(implode(', ', $selects), $definition->table(), $definition->whereForFiltering($fields), $definition->joins());
		$this->applyResellerScope($queryBuilder, $definition->table(), $companyIds, $UID);

		return $this->find($queryBuilder, $definition->table(), $fields);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @throws Exception
	 */
	public function countForUser(FilterDefinition $definition, array $fields, int $UID): int
	{
		$queryBuilder = $this->buildQuery('COUNT(1)', $definition->table(), $this->buildUserWhere($definition, $fields, $UID), $definition->userJoins());

		return $this->count($queryBuilder);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findForUser(FilterDefinition $definition, array $fields, int $UID): array
	{
		$selects   = $definition->selects();
		$selects[] = "'user'";
		$queryBuilder = $this->buildQuery(implode(', ', $selects), $definition->table(), $this->buildUserWhere($definition, $fields, $UID), $definition->userJoins());

		return $this->find($queryBuilder, $definition->table(), $fields);
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	private function buildUserWhere(FilterDefinition $definition, array $fields, int $UID): array
	{
		$where = $definition->whereForFiltering($fields);
		$where[$definition->table().'.UID'] = $this->conditions->whereClause($UID);

		return $where;
	}

	/**
	 * The conditions are grouped in brackets. Otherwise, the OR would bypass the filter conditions:
	 * filters AND (UID = x OR company_id IN (...))
	 *
	 * @param int[] $companyIds
	 */
	private function applyResellerScope(QueryBuilder $queryBuilder, string $table, array $companyIds, int $UID): void
	{
		$parameterName = str_replace('.', '', $table).'UID';
		$scope         = $table.'.UID = :'.$parameterName;
		$queryBuilder->setParameter($parameterName, $UID);

		if (!empty($companyIds))
		{
			$scope = '('.$scope.' OR user_main.company_id IN (:user_maincompany_id))';
			$queryBuilder->setParameter('user_maincompany_id', $companyIds, ArrayParameterType::INTEGER);
		}

		$queryBuilder->andWhere($scope);
	}

	/**
	 * @throws Exception
	 */
	private function count(QueryBuilder $queryBuilder): int
	{
		return (int) $queryBuilder->executeQuery()->fetchOne();
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	private function find(QueryBuilder $queryBuilder, string $table, array $fields): array
	{
		$orderBy = $this->determineOrderBy($table, $fields);
		if (!empty($orderBy))
			$queryBuilder->addOrderBy($orderBy['sort'], $orderBy['order']);

		$limit = $this->determineLimit(
			(int) ($fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE]['value'] ?? 0),
			(int) ($fields[BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE]['value'] ?? 0)
		);
		if (!empty($limit))
			$queryBuilder->setFirstResult($limit['first'])->setMaxResults($limit['max']);

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @param array<string, mixed> $where
	 * @param array<string, string> $joins
	 */
	private function buildQuery(string $select, string $table, array $where, array $joins): QueryBuilder
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select($select)->from($table);

		foreach ($joins as $joinTable => $onCondition)
		{
			$queryBuilder->leftJoin($table, $joinTable, $joinTable, $onCondition);
		}

		$this->applyConditions($queryBuilder, $where);

		return $queryBuilder;
	}

	/**
	 * Todo: the repositories use Where and the WhereApplier already, the datatables follow (FilterConditions).
	 *
	 * @param array<string, mixed> $conditions
	 */
	private function applyConditions(QueryBuilder $queryBuilder, array $conditions): void
	{
		foreach ($conditions as $field => $parameter)
		{
			if (is_array($parameter) && isset($parameter['expression']))
			{
				$queryBuilder->andWhere($parameter['expression']);
				continue;
			}

			$value   = $parameter['value'] ?? $parameter;
			$compare = $parameter['compare'] ?? '=';
			$logic   = $parameter['logic'] ?? 'AND';

			$parameterName = str_replace('.', '', $field); // because DBAL do not accept SQL dots like table.field
			$placeholder   = ($compare === 'IN') ? "(:$parameterName)" : ":$parameterName";

			// only the one escape sign of FilterConditions::likeClause() is written into the SQL, never a given text
			$escape = (is_array($parameter) && ($parameter['escape'] ?? null) === FilterConditions::LIKE_ESCAPE)
				? " ESCAPE '" . FilterConditions::LIKE_ESCAPE . "'"
				: '';

			if ($logic === 'OR')
				$queryBuilder->orWhere("$field $compare $placeholder$escape");
			else
				$queryBuilder->andWhere("$field $compare $placeholder$escape");

			if ($compare === 'IN')
				$queryBuilder->setParameter($parameterName, explode(',', $value), $parameter['type']);
			else
				$queryBuilder->setParameter($parameterName, $value);
		}
	}

	/**
	 * @param array<string, array<string, mixed>> $fields
	 * @return array{sort: string, order: string}|array{}
	 */
	private function determineOrderBy(string $table, array $fields): array
	{
		// no sort column
		if (!array_key_exists(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN, $fields) ||
			!array_key_exists('value', $fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]) ||
			$fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]['value'] === '')
			return [];

		// No Ordering uses default ASC
		$sortOrder = $fields[BaseFilterParametersInterface::PARAMETER_SORT_ORDER]['value'] ?? 'ASC';

		// default when wrong order command
		if (strcasecmp($sortOrder, 'desc') !== 0 && strcasecmp($sortOrder, 'asc') !== 0)
			$sortOrder = 'ASC';

		// sort by user
		$sortColumn = $fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]['value'];
		if ($sortColumn === 'UID' || $sortColumn === 'username')
			return ['sort' => 'user_main.username', 'order' => $sortOrder];

		$column = $this->determineSortColumn($fields[BaseFilterParametersInterface::PARAMETER_SORT_COLUMN]);
		if ($column === '')
			return [];

		// ORDER BY cannot use bound parameters, so the identifier is validated and quoted instead.
		$sort = $this->connection->quoteSingleIdentifier($table).'.'.$this->connection->quoteSingleIdentifier($column);

		return ['sort' => $sort, 'order' => $sortOrder];
	}

	/**
	 * Falls back to the default column when the requested one is not a plain identifier.
	 *
	 * @param array<string, mixed> $sortField
	 */
	private function determineSortColumn(array $sortField): string
	{
		foreach ([$sortField['value'] ?? '', $sortField['default_value'] ?? ''] as $candidate)
		{
			if (is_string($candidate) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $candidate))
				return $candidate;
		}

		return '';
	}

	/**
	 * @return array{first: int, max: int}|array{}
	 */
	private function determineLimit(int $page, int $perPage): array
	{
		if ($page === 0)
			$page = 1;

		if ($perPage > 0)
			return ['first' => ($page - 1) * $perPage, 'max' => $perPage];

		return [];
	}
}
