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
	private PasswordField $passwordField;
	protected function setUp(): void
	{
		parent::setUp();
		$attributes = [
			'id' => 'password',
			'type' => FieldType::PASSWORD,
		];
		$this->passwordField = new PasswordField($attributes);
	}

	#[Group('units')]
	public function testSetPattern(): void
	{
		$pattern = 'complicated Pattern';
		$this->passwordField->setPattern($pattern);

		static::assertSame($pattern, $this->passwordField->getPattern());
	}
}
