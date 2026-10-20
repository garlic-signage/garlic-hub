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

use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\UrlField;
use App\Framework\Utils\Html\UrlRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UrlRendererTest extends TestCase
{
	private UrlRenderer $urlRenderer;
	private UrlField&Stub $urlFieldStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->urlFieldStub = $this->createStub(UrlField::class);
		$this->urlRenderer = new UrlRenderer();
	}

	#[Group('units')]
	public function testRenderWithValidUrlField(): void
	{
		$this->urlFieldStub->method('getPattern')->willReturn('[a-z]+://.*');
		$this->urlFieldStub->method('getPlaceholder')->willReturn('Enter URL');
		$this->urlFieldStub->method('getId')->willReturn('urlField1');

		$expectedAttributes = 'pattern="[a-z]+://.*" placeholder="Enter URL" aria-describedby="error_urlField1"';
		$result = $this->urlRenderer->render($this->urlFieldStub);

		static::assertStringContainsString('<input type="text" ', $result);
		static::assertStringContainsString($expectedAttributes, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderWithInvalidFieldType(): void
	{
		$mockField = $this->createStub(FieldInterface::class);

		$result = $this->urlRenderer->render($mockField);

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testRenderWithEmptyPatternAndPlaceholder(): void
	{
		$this->urlFieldStub->method('getPattern')->willReturn('');
		$this->urlFieldStub->method('getPlaceholder')->willReturn('');
		$this->urlFieldStub->method('getId')->willReturn('urlField1');

		$expectedAttributes = 'pattern="" placeholder="" aria-describedby="error_urlField1"';
		$result = $this->urlRenderer->render($this->urlFieldStub);

		static::assertStringContainsString('<input type="text" ', $result);
		static::assertStringContainsString($expectedAttributes, $result);
	}
}
