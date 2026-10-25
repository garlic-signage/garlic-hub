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
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\NumberRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class NumberRendererTest extends TestCase
{
	private NumberRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->renderer = new NumberRenderer();
	}

	#[Group('units')]
	public function testRenderWithLimits(): void
	{
		$field = new NumberField(['id' => 'logins', 'type' => FieldType::NUMBER, 'name' => 'user_logins', 'value' => 7, 'min' => 1, 'max' => 10, 'title' => 'logins']);

		static::assertSame(
			'<input type="number" name="user_logins" id="logins" value="7" title="logins" min="1" max="10" aria-describedby="error_logins">',
			$this->renderer->render($field)
		);
	}

	#[Group('units')]
	public function testRenderWithoutValueOrLimits(): void
	{
		$field = new NumberField(['id' => 'logins', 'type' => FieldType::NUMBER]);

		static::assertSame('<input type="number" name="logins" id="logins" value="" aria-describedby="error_logins">', $this->renderer->render($field));
	}

	/**
	 * Zero is a value: a counter on 0 must show 0 and not its default.
	 */
	#[Group('units')]
	public function testZeroIsRenderedAndNotTheDefault(): void
	{
		$field = new NumberField(['id' => 'n', 'type' => FieldType::NUMBER, 'value' => 0, 'default_value' => 5]);

		static::assertSame('<input type="number" name="n" id="n" value="0" aria-describedby="error_n">', $this->renderer->render($field));
	}
}
