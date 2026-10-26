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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Database\Where;
use App\Framework\Database\Datatable\FilterConditions;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TemplatesFilterDefinitionTest extends TestCase
{
	private TemplatesFilterDefinition $definition;

	protected function setUp(): void
	{
		parent::setUp();
		$this->definition = new TemplatesFilterDefinition(new FilterConditions());
	}

	#[Group('units')]
	public function testTableJoinsAndSelects(): void
	{
		static::assertSame('templates', $this->definition->table());
		static::assertSame(['user_main' => 'user_main.UID=templates.UID'], $this->definition->joins());
		static::assertSame([], $this->definition->userJoins());
		static::assertSame(['templates.*'], $this->definition->selects());
		static::assertSame(['templates.*', 'user_main.username', 'user_main.company_id'], $this->definition->selectsWithUser());
	}

	#[Group('units')]
	public function testWhereUsesLikeForNameAndType(): void
	{
		$fields = [
			'elements_page' => ['value' => 1],
			'sort_column'   => ['value' => 'template_id'],
			'name'          => ['value' => 'my*'],
			'type'          => ['value' => 'canvas'],
			'company_id'    => ['value' => 0]
		];

		$expected = [
			'templates.name' => Where::contains('templates.name', 'my*'),
			'templates.type' => Where::contains('templates.type', 'canvas')
		];
		static::assertEquals($expected, $this->definition->whereForFiltering($fields));
	}

	#[Group('units')]
	public function testWhereIgnoresEmptyValues(): void
	{
		static::assertSame([], $this->definition->whereForFiltering(['name' => ['value' => ''], 'username' => ['value' => '']]));
	}
}
