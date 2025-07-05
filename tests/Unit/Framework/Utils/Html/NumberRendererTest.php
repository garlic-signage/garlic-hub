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

use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\NumberRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NumberRendererTest extends TestCase
{
	private NumberRenderer $numberRenderer;
	private NumberField&MockObject $fieldMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fieldMock = $this->createMock(NumberField::class);
		$this->numberRenderer = new NumberRenderer();
	}

	#[Group('units')]
	public function testRenderCorrectHtml(): void
	{
		$this->fieldMock->method('getId')->willReturn('test_id');
		$this->fieldMock->method('getName')->willReturn('test_name');
		$this->fieldMock->method('getValue')->willReturn('123');
		$this->fieldMock->method('getAttributes')->willReturn(['class' => 'test-class']);
		$this->fieldMock->method('getValidationRules')->willReturn([]);
		$this->fieldMock->method('getTitle')->willReturn('Test Title');
		$this->fieldMock->method('getLabel')->willReturn('A Label');

		$expectedHtml = '<input type="number" name="test_name" id="test_id" value="123" title="Test Title" label="A Label" class="test-class" aria-describedby="error_test_id">';

		$actualHtml = $this->numberRenderer->render($this->fieldMock);

		static::assertSame($expectedHtml, $actualHtml);
	}
}
