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

use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\PasswordField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PasswordFieldTest extends TestCase
{
	#[Group('units')]
	public function testThePatternIsAnAttributeOfTheField(): void
	{
		$field = new PasswordField(['id' => 'password', 'type' => FieldType::PASSWORD, 'attributes' => ['class' => 'pw']]);

		$result = $field->setPattern('.{8,}');

		static::assertSame($field, $result);
		static::assertSame('.{8,}', $field->getPattern());
		static::assertSame(['class' => 'pw', 'pattern' => '.{8,}'], $field->getAttributes());
	}
}
