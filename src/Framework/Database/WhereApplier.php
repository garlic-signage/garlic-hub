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

namespace App\Framework\Database;

use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;

/**
 * Writes the conditions into a query. They are joined with AND, every value gets a parameter with a name of its
 * own, so two conditions on the same field do not overwrite each other. A group (Where::anyOf, Where::allOf)
 * is written in brackets with its own operator.
 */
final readonly class WhereApplier
{
	/**
	 * @param array<int|string, int|string|Where> $conditions a Where, or field => value for equality
	 */
	public function apply(QueryBuilder $queryBuilder, array $conditions): void
	{
		foreach ($conditions as $field => $condition)
		{
			$queryBuilder->andWhere($this->write($queryBuilder, $this->toWhere($field, $condition)));
		}
	}

	private function write(QueryBuilder $queryBuilder, Where $where): string
	{
		if ($where->isGroup())
		{
			$parts = array_map(fn(Where $condition): string => $this->write($queryBuilder, $condition), $where->conditions);

			return '(' . implode(' ' . $where->operator . ' ', $parts) . ')';
		}

		$parameter = 'where' . count($queryBuilder->getParameters());
		if (!$where->hasParameter())
			return $where->sql($parameter);

		if ($where->listType !== null)
			$queryBuilder->setParameter($parameter, $where->value, $where->listType);
		else
			$queryBuilder->setParameter($parameter, $where->value);

		return $where->sql($parameter);
	}

	private function toWhere(int|string $field, mixed $condition): Where
	{
		if ($condition instanceof Where)
			return $condition;

		if (is_string($field) && (is_int($condition) || is_string($condition)))
			return Where::equals($field, $condition);

		throw new InvalidArgumentException('No condition for ' . $field . ': use a Where or field => int|string.');
	}
}
