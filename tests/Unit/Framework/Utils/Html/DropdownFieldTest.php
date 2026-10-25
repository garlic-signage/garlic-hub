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

namespace Tests\Unit\Framework\Utils\Html;

use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class DropdownFieldTest extends TestCase
{
	#[Group('units')]
	public function testGetOptions(): void
	{
		$field = new DropdownField(['id' => 'country', 'type' => FieldType::DROPDOWN, 'options' => ['de' => 'Germany', 'fr' => 'France']]);

		static::assertSame(['de' => 'Germany', 'fr' => 'France'], $field->getOptions());
	}

	#[Group('units')]
	public function testTheEmptyOptionIsOnByDefault(): void
	{
		$field = new DropdownField(['id' => 'country', 'type' => FieldType::DROPDOWN, 'options' => []]);

		static::assertTrue($field->isOptionsZero());
	}

	#[Group('units')]
	public function testTheEmptyOptionCanBeSwitchedOff(): void
	{
		$field = new DropdownField(['id' => 'country', 'type' => FieldType::DROPDOWN, 'options' => [], 'options_zero' => false]);

		static::assertFalse($field->isOptionsZero());
	}
}
