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

use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldRenderInterface;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\TextRenderer;
use App\Framework\Utils\Html\UrlField;
use Closure;
use Defuse\Crypto\Key;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

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
	 * @return array<string, array{Closure(): FieldInterface, string}> field and the start of the HTML its renderer makes
	 */
	public static function fieldProvider(): array
	{
		$id = ['id' => 'f'];

		return [
			'text'           => [static fn() => new TextField($id + ['type' => FieldType::TEXT]), '<input type="text" '],
			'number'         => [static fn() => new NumberField($id + ['type' => FieldType::NUMBER]), '<input type="number" '],
			'email'          => [static fn() => new EmailField($id + ['type' => FieldType::EMAIL]), '<input type="email" '],
			'password'       => [static fn() => new PasswordField($id + ['type' => FieldType::PASSWORD]), '<div class="password-container">'],
			'hidden'         => [static fn() => new HiddenField($id + ['type' => FieldType::HIDDEN]), '<input type="hidden" '],
			'url'            => [static fn() => new UrlField($id + ['type' => FieldType::URL])->setPattern('.*')->setPlaceholder(''), '<input type="text" '],
			'checkbox'       => [static fn() => new CheckboxField($id + ['type' => FieldType::CHECKBOX]), '<input type="checkbox" '],
			'clipboard text' => [static fn() => new ClipboardTextField($id + ['type' => FieldType::CLIPBOARD_TEXT]), '<input type="text" id="f" class="verification-link"'],
			'autocomplete'   => [static fn() => new AutocompleteField($id + ['type' => FieldType::AUTOCOMPLETE, 'data-label' => 'l']), '<input id="f_search" list="f_suggestions"'],
			'dropdown'       => [static fn() => new DropdownField($id + ['type' => FieldType::DROPDOWN, 'options' => []]), '<select id="f"'],
			'csrf'           => [static fn() => new CsrfTokenField($id + ['type' => FieldType::CSRF], new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession())), '<input type="hidden" '],
		];
	}

	/**
	 * @param Closure(): FieldInterface $createField
	 * @param non-empty-string $expectedStart
	 */
	#[Group('units')]
	#[DataProvider('fieldProvider')]
	public function testEachFieldIsRenderedByItsRenderer(Closure $createField, string $expectedStart): void
	{
		$html = $this->fieldsRenderFactory->getRenderer($createField());

		static::assertStringStartsWith($expectedStart, $html);
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

	#[Group('units')]
	public function testTheRendererIsCreatedOnceAndUsedAgain(): void
	{
		$factory = new FieldsRenderFactoryInherited();

		$first = $factory->callGetCachedRenderer(TextRenderer::class);

		static::assertInstanceOf(TextRenderer::class, $first);
		static::assertSame($first, $factory->callGetCachedRenderer(TextRenderer::class));
	}

	#[Group('units')]
	public function testTwoFieldsOfTheSameTypeDoNotShareTheirAttributes(): void
	{
		$first  = $this->fieldsRenderFactory->getRenderer(new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'attributes' => ['class' => 'one']]));
		$second = $this->fieldsRenderFactory->getRenderer(new TextField(['id' => 'b', 'type' => FieldType::TEXT]));

		static::assertStringContainsString('class="one"', $first);
		static::assertSame('<input type="text" name="b" id="b" value="" aria-describedby="error_b">', $second);
	}

	#[Group('units')]
	public function testGetCachedRendererThrowsExceptionIfRendererDoesNotImplementInterface(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(sprintf('Renderer class "%s" must implement "%s".', NonImplementingRenderer::class, FieldRenderInterface::class));

		(new FieldsRenderFactoryInherited())->callGetCachedRenderer(NonImplementingRenderer::class);
	}
}
