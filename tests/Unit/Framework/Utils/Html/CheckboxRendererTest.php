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
use App\Framework\Utils\Html\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckboxRendererTest extends TestCase
{
	private CheckboxField&MockObject $checkboxFieldMock;
	private CheckboxRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->checkboxFieldMock = $this->createMock(CheckboxField::class);
		$this->renderer = new CheckboxRenderer();
	}

	#[Group('units')]
	public function testRenderReturnsEmptyString(): void
	{
		$this->checkboxFieldMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(FieldInterface::class);
		$result = $this->renderer->render($fieldStub);
		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testRenderReturnsCheckedCheckbox(): void
	{
		$this->checkboxFieldMock->expects($this->once())->method('getId')
			->willReturn('checkbox_1');

		$this->checkboxFieldMock->expects($this->once())->method('isChecked')
			->willReturn(true);

		$this->checkboxFieldMock->expects($this->once())->method('getTitle')
			->willReturn('Accept Terms');

		$result = $this->renderer->render($this->checkboxFieldMock);
		static::assertSame('<input type="checkbox" id="checkbox_1" name="checkbox_1" checked value="1" aria-describedby="error_checkbox_1"> Accept Terms', $result);
	}

	#[Group('units')]
	public function testRenderReturnsUncheckedCheckbox(): void
	{
		$map = [
			['getId', [], 'checkbox_1'],
			['isChecked', [], false],
			['getTitle', [], 'Subscribe to newsletter']
		];

		$this->checkboxFieldMock->expects($this->once())->method('getId')
			->willReturn('checkbox_1');

		$this->checkboxFieldMock->expects($this->once())->method('isChecked')
			->willReturn(false);

		$this->checkboxFieldMock->expects($this->once())->method('getTitle')
			->willReturn('Subscribe to newsletter');

		$result = $this->renderer->render($this->checkboxFieldMock);
		static::assertSame('<input type="checkbox" id="checkbox_1" name="checkbox_1"  value="1" aria-describedby="error_checkbox_1"> Subscribe to newsletter', $result);
	}
}
