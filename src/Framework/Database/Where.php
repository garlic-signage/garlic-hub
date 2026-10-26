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

use Doctrine\DBAL\ArrayParameterType;
use InvalidArgumentException;

/**
 * One condition of a query. The field name is checked and the comparison comes from a fixed set, so neither
 * can carry SQL; the value is always a parameter. The WhereApplier joins all conditions of a query with AND.
 */
final readonly class Where
{
	/**
	 * Escape sign in LIKE. A backslash would have to be written differently in MySQL and SQLite, this sign is
	 * the same in both.
	 */
	public const string LIKE_ESCAPE = '!';

	/** a column or table.column */
	private const string FIELD_PATTERN = '/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/';

	/**
	 * @param int|string|list<int|string>|null $value
	 */
	private function __construct(
		private string $sql,
		public int|string|array|null $value = null,
		public ?ArrayParameterType $listType = null
	) {}

	public static function equals(string $field, int|string $value): self
	{
		return self::compare($field, Compare::Equal, $value);
	}

	public static function compare(string $field, Compare $compare, int|string $value): self
	{
		return new self(self::checkedField($field) . ' ' . $compare->value . ' :%s', $value);
	}

	/**
	 * An empty list matches nothing, like IN () would mean.
	 *
	 * @param list<int|string> $values
	 */
	public static function in(string $field, array $values, ArrayParameterType $type = ArrayParameterType::INTEGER): self
	{
		$field = self::checkedField($field);
		if ($values === [])
			return new self('1 = 0');

		return new self($field . ' IN (:%s)', array_values($values), $type);
	}

	/**
	 * The prefix is searched as it is: % and _ in it are no wildcards.
	 */
	public static function startsWith(string $field, string $prefix): self
	{
		return new self(self::checkedField($field) . " LIKE :%s ESCAPE '" . self::LIKE_ESCAPE . "'", self::escapeLike($prefix) . '%');
	}

	/**
	 * A search term of a visitor: % and _ are searched as they are, only * is a wildcard. The term is searched
	 * anywhere in the value.
	 */
	public static function contains(string $field, string $searchTerm): self
	{
		$pattern = '%' . str_replace('*', '%', self::escapeLike($searchTerm)) . '%';

		return new self(self::checkedField($field) . " LIKE :%s ESCAPE '" . self::LIKE_ESCAPE . "'", $pattern);
	}

	/**
	 * SQL without a parameter, e.g. for arithmetics with columns. Only fixed text of the code, never an input.
	 */
	public static function raw(string $sql): self
	{
		return new self($sql);
	}

	public static function escapeLike(string $text): string
	{
		return str_replace(
			[self::LIKE_ESCAPE, '%', '_'],
			[self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
			$text
		);
	}

	public function hasParameter(): bool
	{
		return $this->value !== null;
	}

	/**
	 * The condition as SQL with the name of its parameter.
	 */
	public function sql(string $parameter): string
	{
		return $this->hasParameter() ? sprintf($this->sql, $parameter) : $this->sql;
	}

	private static function checkedField(string $field): string
	{
		if (preg_match(self::FIELD_PATTERN, $field) !== 1)
			throw new InvalidArgumentException('No field name: ' . $field);

		return $field;
	}
}
