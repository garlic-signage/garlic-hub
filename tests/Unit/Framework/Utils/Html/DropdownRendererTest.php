<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
use App\Framework\Utils\Html\DropdownRenderer;
use App\Framework\Utils\Html\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DropdownRendererTest extends TestCase
{
	private DropdownField&Stub $dropdownFieldStub;
	private DropdownRenderer $renderer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->dropdownFieldStub = $this->createStub(DropdownField::class);
		$this->renderer = new DropdownRenderer();
	}

	#[Group('units')]
	public function testRenderGeneratesValidHtml(): void
	{
		$this->dropdownFieldStub->method('getId')->willReturn('test-id');
		$this->dropdownFieldStub->method('getName')->willReturn('test-name');
		$this->dropdownFieldStub->method('getOptions')->willReturn(['1' => 'Option 1', '2' => 'Option 2']);
		$this->dropdownFieldStub->method('getValue')->willReturn('');
		$this->dropdownFieldStub->method('isOptionsZero')->willReturn(true);

		$html = $this->renderer->render($this->dropdownFieldStub);

		$expectedHtml = '<select id="test-id" name= "test-name" aria-describedby="error_test-id">'
			. '<option value="">-</option>'
			. '<option value="1">Option 1</option>'
			. '<option value="2">Option 2</option>'
			. '</select>';

		static::assertSame($expectedHtml, $html);
	}

	#[Group('units')]
	public function testRenderWithSelectedValue(): void
	{
		$this->dropdownFieldStub->method('getId')->willReturn('test-id');
		$this->dropdownFieldStub->method('getName')->willReturn('test-name');
		$this->dropdownFieldStub->method('getOptions')->willReturn(['1' => 'Option 1', '2' => 'Option 2']);
		$this->dropdownFieldStub->method('getValue')->willReturn('2');
		$this->dropdownFieldStub->method('isOptionsZero')->willReturn(false);

		$html = $this->renderer->render($this->dropdownFieldStub);

		$expectedHtml = '<select id="test-id" name= "test-name" aria-describedby="error_test-id">'
			. '<option value="1">Option 1</option>'
			. '<option value="2" selected>Option 2</option>'
			. '</select>';

		static::assertSame($expectedHtml, $html);
	}

	#[Group('units')]
	public function testRenderWithEmptyOptions(): void
	{
		$this->dropdownFieldStub->method('getId')->willReturn('test-id');
		$this->dropdownFieldStub->method('getName')->willReturn('test-name');
		$this->dropdownFieldStub->method('getOptions')->willReturn([]);
		$this->dropdownFieldStub->method('getValue')->willReturn('');
		$this->dropdownFieldStub->method('isOptionsZero')->willReturn(true);

		$html = $this->renderer->render($this->dropdownFieldStub);

		$expectedHtml = '<select id="test-id" name= "test-name" aria-describedby="error_test-id">'
			. '<option value="">-</option>'
			. '</select>';

		static::assertSame($expectedHtml, $html);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderGeneratesNothing(): void
	{
		$fieldStub = $this->createStub(FieldInterface::class);
		$renderer = new DropdownRenderer();

		$expectedHtml = '';

		$result = $renderer->render($fieldStub);

		static::assertSame($expectedHtml, $result);
	}

}
