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
use App\Framework\Utils\Html\UrlField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class UrlFieldTest extends TestCase
{
	#[Group('units')]
	public function testPlaceholderAndPattern(): void
	{
		$field = new UrlField(['id' => 'homepage', 'type' => FieldType::URL]);

		$result = $field->setPlaceholder('https://example.com')->setPattern('https?://.+');

		static::assertSame($field, $result);
		static::assertSame('https://example.com', $field->getPlaceholder());
		static::assertSame('https?://.+', $field->getPattern());
	}

	#[Group('units')]
	public function testAnEmptyPatternIsKept(): void
	{
		$field = new UrlField(['id' => 'homepage', 'type' => FieldType::URL]);

		$field->setPattern('');

		static::assertSame('', $field->getPattern());
	}

	#[Group('units')]
	public function testPlaceholderAndPatternAreEmptyByDefault(): void
	{
		$field = new UrlField(['id' => 'homepage', 'type' => FieldType::URL]);

		static::assertSame('', $field->getPlaceholder());
		static::assertSame('', $field->getPattern());
	}
}
