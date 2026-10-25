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

use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\AutocompleteRenderer;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class AutocompleteRendererTest extends TestCase
{
	#[Group('units')]
	public function testRender(): void
	{
		$field = new AutocompleteField(['id' => 'UID', 'type' => FieldType::AUTOCOMPLETE, 'value' => '5', 'data-label' => 'Anna']);

		$expected = '<input id="UID_search" list="UID_suggestions" value="Anna" data-id="5" aria-describedby="error_UID" class="autocomplete" autocomplete="off">
		<input type="hidden" id="UID" name="UID" value="5">
		<datalist id = "UID_suggestions" ></datalist>';

		static::assertSame($expected, (new AutocompleteRenderer())->render($field));
	}

	#[Group('units')]
	public function testAFieldOfAnotherTypeRendersNothing(): void
	{
		static::assertSame('', (new AutocompleteRenderer())->render(new TextField(['id' => 'a', 'type' => FieldType::TEXT])));
	}
}
