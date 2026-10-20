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

use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldRenderInterface;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\TextRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;


class NonImplementingRenderer
{
	public function someMethod(): string
	{
		return 'I do not implement FieldRenderInterface!';
	}
}

class FieldsRenderFactoryInherited extends FieldsRenderFactory
{
	public function callGetCachedRenderer(string $rendererClass): FieldRenderInterface
	{
		return $this->getCachedRenderer($rendererClass);
	}
}

class FieldsRenderFactoryTest extends TestCase
{
	private FieldsRenderFactory $fieldsRenderFactory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fieldsRenderFactory = new FieldsRenderFactory();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetRendererForUnsupportedField(): void
	{
		$fieldStub = $this->createStub(FieldInterface::class);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unsupported field type: ');
		$this->fieldsRenderFactory->getRenderer($fieldStub);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetRendererForSupportedField(): void
	{
		$textFieldStub = $this->createStub(TextField::class);
		$textRenderFieldStub = $this->createStub(TextRenderer::class);

		$renderedText = '<input type="text" name="" id="" value="0" aria-describedby="error_">';
		$textRenderFieldStub->method('render');

		static::assertSame($renderedText,  $this->fieldsRenderFactory->getRenderer($textFieldStub));
	}

	#[Group('units')]
	public function testGetCachedRendererThrowsExceptionIfRendererDoesNotImplementInterface(): void
	{

		$testFactory = new FieldsRenderFactoryInherited();
		$this->expectException(InvalidArgumentException::class);


/*		$this->expectExceptionMessage(sprintf(
			'Renderer class "NonImplementingRenderer" must implement "render()".',
			$invalidRendererClass,
			FieldRenderInterface::class
		));
*/
		$invalidRendererClass = NonImplementingRenderer::class;
		$testFactory->callGetCachedRenderer($invalidRendererClass);

	}
}
