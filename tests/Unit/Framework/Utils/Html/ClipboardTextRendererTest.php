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

use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\ClipboardTextRenderer;
use App\Framework\Utils\Html\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ClipboardTextRendererTest extends TestCase
{
	private ClipboardTextField&Stub $clipboardTextFieldStub;
	private ClipboardTextRenderer $renderer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->renderer = new ClipboardTextRenderer();
		$this->clipboardTextFieldStub = $this->createStub(ClipboardTextField::class);
	}

	#[Group('units')]
	public function testRenderWithValidClipboardTextField(): void
	{
		$this->clipboardTextFieldStub->method('getId')
			->willReturn('test-id');

		$this->clipboardTextFieldStub ->method('getTitle')
			->willReturn('Test Title');

		$this->clipboardTextFieldStub->method('getValue')
			->willReturn('Test Value');

		$this->clipboardTextFieldStub->method('getDeleteTitle')
			->willReturn('Delete Test');

		$this->clipboardTextFieldStub->method('getRefreshTitle')
			->willReturn('Refresh Test');

		$result = $this->renderer->render($this->clipboardTextFieldStub);

		static::assertStringContainsString('<input type="text"', $result);
		static::assertStringContainsString('id="test-id"', $result);
		static::assertStringContainsString('value="Test Value"', $result);
		static::assertStringContainsString('class="copy-verification-link"', $result);
		static::assertStringContainsString('title="Test Title."', $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithInvalidFieldType(): void
	{
		$invalidFieldStub = $this->createStub(FieldInterface::class);
		$invalidFieldStub
			->method('getId')
			->willReturn('invalid-id');

		$result = $this->renderer->render($invalidFieldStub);

		static::assertSame('', $result);
	}
}
