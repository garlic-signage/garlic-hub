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

namespace Tests\Unit\Framework\Database\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Database\Where;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class FilterConditionsTest extends TestCase
{
	private FilterConditions $conditions;

	protected function setUp(): void
	{
		parent::setUp();
		$this->conditions = new FilterConditions();
	}

	#[Group('units')]
	public function testForFieldIgnoresPagingAndSorting(): void
	{
		$keys = [
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE,
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE,
			BaseFilterParametersInterface::PARAMETER_SORT_COLUMN,
			BaseFilterParametersInterface::PARAMETER_SORT_ORDER
		];
		foreach ($keys as $key)
		{
			static::assertSame([], $this->conditions->forField('table', $key, ['value' => 'something']));
		}
	}

	#[Group('units')]
	public function testForFieldUsernameUsesUserMainWithWildcards(): void
	{
		$result = $this->conditions->forField('table', 'username', ['value' => 'jo*hn']);

		static::assertSame(['user_main.username' => $this->like('user_main.username', '%jo%hn%')], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldReplacesEveryAsterisk(): void
	{
		$result = $this->conditions->forField('table', 'name', ['value' => 'a*b*c']);

		static::assertSame(['table.name' => $this->like('table.name', '%a%b%c%')], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldUIDWithAWordSearchesTheUsername(): void
	{
		$result = $this->conditions->forField('table', 'UID', ['value' => 'john']);

		static::assertSame(['user_main.username' => $this->like('user_main.username', '%john%')], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldUIDWithANumberFiltersTheOwner(): void
	{
		$result = $this->conditions->forField('table', 'UID', ['value' => 7]);

		static::assertSame(['table.UID' => ['table.UID = :p', 7]], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldUsernameWithANumberStaysASearch(): void
	{
		$result = $this->conditions->forField('table', 'username', ['value' => '7']);

		static::assertSame(['user_main.username' => $this->like('user_main.username', '%7%')], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldCompanyId(): void
	{
		$result = $this->conditions->forField('table', 'company_id', ['value' => 123]);

		static::assertSame(['user_main.company_id' => ['user_main.company_id = :p', 123]], $this->described($result));
	}

	#[Group('units')]
	public function testForFieldCompanyIdZeroIsIgnored(): void
	{
		static::assertSame([], $this->conditions->forField('table', 'company_id', ['value' => 0]));
	}

	#[Group('units')]
	public function testForFieldDefaultUsesLikeOnTable(): void
	{
		$result = $this->conditions->forField('table', 'name', ['value' => 'abc']);

		static::assertSame(['table.name' => $this->like('table.name', '%abc%')], $this->described($result));
	}

	/**
	 * "0" is typed in a text field and is a search term. The integer 0 is the default of a select ("all").
	 */
	#[Group('units')]
	#[DataProvider('searchFieldsProvider')]
	public function testForFieldTheTextZeroIsASearchTerm(string $key, string $column): void
	{
		$result = $this->conditions->forField('table', $key, ['value' => '0']);

		static::assertSame([$column => $this->like($column, '%0%')], $this->described($result));
	}

	#[Group('units')]
	#[DataProvider('searchKeysProvider')]
	public function testForFieldTheIntegerZeroIsIgnored(string $key): void
	{
		static::assertSame([], $this->conditions->forField('table', $key, ['value' => 0]));
	}

	#[Group('units')]
	#[DataProvider('searchKeysProvider')]
	public function testForFieldEmptyAndMissingValuesAreIgnored(string $key): void
	{
		static::assertSame([], $this->conditions->forField('table', $key, ['value' => '']));
		static::assertSame([], $this->conditions->forField('table', $key, ['value' => null]));
		static::assertSame([], $this->conditions->forField('table', $key, []));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function searchKeysProvider(): array
	{
		return ['username' => ['username'], 'UID' => ['UID'], 'other' => ['name']];
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function searchFieldsProvider(): array
	{
		return [
			'username' => ['username', 'user_main.username'],
			'UID'      => ['UID', 'user_main.username'],
			'other'    => ['name', 'table.name'],
		];
	}

	/**
	 * @return array{string, string} the SQL and the pattern of a search
	 */
	private function like(string $field, string $pattern): array
	{
		return [$field . " LIKE :p ESCAPE '!'", $pattern];
	}

	/**
	 * The conditions as SQL (with the parameter :p) and value, to compare them.
	 *
	 * @param array<string, Where> $where
	 * @return array<string, array{string, mixed}>
	 */
	private function described(array $where): array
	{
		return array_map(static fn(Where $condition): array => [$condition->sql('p'), $condition->value], $where);
	}
	/**
	 * The signs of LIKE in a search term are searched as they are, only * is a wildcard.
	 *
	 * @return array<string, array{string, string}> search term, pattern
	 */
	public static function searchTermProvider(): array
	{
		return [
			'plain'                  => ['anna', '%anna%'],
			'wildcard'               => ['an*a', '%an%a%'],
			'percent'                => ['50%', '%50!%%'],
			'underscore'             => ['a_b', '%a!_b%'],
			'the escape sign itself' => ['wow!', '%wow!!%'],
			'all of them'            => ['!%_*', '%!!!%!_%%'],
			'a backslash is a sign'  => ['a\\b', '%a\\b%'],
			'quotes'                 => ["it's \"x\"", "%it's \"x\"%"],
		];
	}

	#[Group('units')]
	#[DataProvider('searchTermProvider')]
	public function testASearchTermEscapesTheSignsOfLike(string $term, string $pattern): void
	{
		static::assertSame(['table.name' => $this->like('table.name', $pattern)], $this->described($this->conditions->forField('table', 'name', ['value' => $term])));
	}

	#[Group('units')]
	public function testTheFilterFieldsEscapeTheSearchTerm(): void
	{
		static::assertSame(['user_main.username' => $this->like('user_main.username', '%50!%%')], $this->described($this->conditions->forField('table', 'username', ['value' => '50%'])));
		static::assertSame(['table.name' => $this->like('table.name', '%a!_b%')], $this->described($this->conditions->forField('table', 'name', ['value' => 'a_b'])));
	}
}
