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
use Doctrine\DBAL\ArrayParameterType;
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
	public function testWhereClauseDefaults(): void
	{
		$expected = ['value' => 5, 'compare' => '=', 'logic' => 'AND', 'type' => ArrayParameterType::INTEGER];

		static::assertSame($expected, $this->conditions->whereClause(5));
	}

	#[Group('units')]
	public function testWhereClauseCustom(): void
	{
		$expected = ['value' => 'a,b', 'compare' => 'IN', 'logic' => 'OR', 'type' => ArrayParameterType::STRING];

		static::assertSame($expected, $this->conditions->whereClause('a,b', 'IN', 'OR', ArrayParameterType::STRING));
	}

	#[Group('units')]
	public function testExpression(): void
	{
		static::assertSame(['expression' => 'a.b < c.d'], $this->conditions->expression('a.b < c.d'));
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

		static::assertSame(['user_main.username' => $this->conditions->whereClause('%jo%hn%', 'LIKE')], $result);
	}

	#[Group('units')]
	public function testForFieldUIDIsTreatedAsUsername(): void
	{
		$result = $this->conditions->forField('table', 'UID', ['value' => 'john']);

		static::assertSame(['user_main.username' => $this->conditions->whereClause('%john%', 'LIKE')], $result);
	}

	#[Group('units')]
	public function testForFieldEmptyUsernameIsIgnored(): void
	{
		static::assertSame([], $this->conditions->forField('table', 'username', ['value' => '']));
	}

	#[Group('units')]
	public function testForFieldCompanyId(): void
	{
		$result = $this->conditions->forField('table', 'company_id', ['value' => 123]);

		static::assertSame(['user_main.company_id' => $this->conditions->whereClause(123)], $result);
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

		static::assertSame(['table.name' => $this->conditions->whereClause('%abc%', 'LIKE')], $result);
	}

	#[Group('units')]
	public function testForFieldDefaultEmptyIsIgnored(): void
	{
		static::assertSame([], $this->conditions->forField('table', 'name', ['value' => '']));
	}
}
