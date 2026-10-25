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

use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\CheckboxRenderer;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class CheckboxRendererTest extends TestCase
{
	private CheckboxField $field;

	protected function setUp(): void
	{
		parent::setUp();
		$this->field = new CheckboxField(['id' => 'active', 'type' => FieldType::CHECKBOX, 'title' => 'is active']);
	}

	#[Group('units')]
	public function testRenderUnchecked(): void
	{
		static::assertSame(
			'<input type="checkbox" id="active" name="active"  value="1" aria-describedby="error_active"> is active',
			(new CheckboxRenderer())->render($this->field)
		);
	}

	#[Group('units')]
	public function testRenderChecked(): void
	{
		$this->field->setChecked(true);

		static::assertSame(
			'<input type="checkbox" id="active" name="active" checked value="1" aria-describedby="error_active"> is active',
			(new CheckboxRenderer())->render($this->field)
		);
	}

	#[Group('units')]
	public function testAFieldOfAnotherTypeRendersNothing(): void
	{
		static::assertSame('', (new CheckboxRenderer())->render(new TextField(['id' => 'a', 'type' => FieldType::TEXT])));
	}
}
