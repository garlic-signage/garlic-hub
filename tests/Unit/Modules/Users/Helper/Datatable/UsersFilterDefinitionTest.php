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

namespace Tests\Unit\Modules\Users\Helper\Datatable;

use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Modules\Users\Helper\Datatable\UsersFilterDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class UsersFilterDefinitionTest extends TestCase
{
	private FilterConditions $conditions;
	private UsersFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->conditions = new FilterConditions();
		$this->definition = new UsersFilterDefinition($this->conditions);
	}

	#[Group('units')]
	public function testTableJoinsAndSelects(): void
	{
		static::assertSame('user_main', $this->definition->table());
		static::assertSame([], $this->definition->joins());
		static::assertSame([], $this->definition->userJoins());
		$selects = ['user_main.UID', 'user_main.company_id', 'user_main.username', 'user_main.email',
			'user_main.status', 'user_main.created_at', 'user_main.last_access'];

		static::assertSame($selects, $this->definition->selects());
		static::assertSame($selects, $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testWhereForFiltering(): void
	{
		$fields = [
			'username'    => ['value' => 'johndoe'],
			'from_status' => ['value' => 3],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PAGE     => ['value' => 0],
			BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => ['value' => 10],
		];

		$expected = [
			'user_main.username' => $this->conditions->likeClause('johndoe'),
			'user_main.status'   => $this->conditions->whereClause(3, '>=')
		];
		static::assertSame($expected, $this->definition->whereForFiltering($fields));
	}

	#[Group('units')]
	public function testWhereEmailUsesLike(): void
	{
		$where = $this->definition->whereForFiltering(['email' => ['value' => 'doe*.de'], 'status' => ['value' => 0]]);

		static::assertSame(['user_main.email' => $this->conditions->likeClause('doe*.de')], $where);
	}
}
