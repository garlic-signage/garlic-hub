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
use App\Framework\Utils\Html\AutocompleteRenderer;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\CheckboxRenderer;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\ClipboardTextRenderer;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\CsrfTokenRenderer;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\DropdownRenderer;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\EmailRenderer;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldRenderInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\HiddenRenderer;
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\NumberRenderer;
use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\PasswordRenderer;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\TextRenderer;
use App\Framework\Utils\Html\UrlField;
use App\Framework\Utils\Html\UrlRenderer;
use Closure;
use Defuse\Crypto\Key;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * What a user typed or what comes from the database must never become markup of the form. Every renderer
 * is given hostile values for everything it writes into HTML.
 */
class RenderersEscapingTest extends TestCase
{
	private const string HOSTILE = '"><script>alert(\'x\')</script><img src=x onerror=alert(1)> &';

	/**
	 * @return array<string, array{FieldRenderInterface, Closure(): FieldInterface}>
	 */
	public static function rendererProvider(): array
	{
		$common = static fn(FieldType $type): array => [
			'id'         => 'f' . self::HOSTILE,
			'type'       => $type,
			'name'       => 'n' . self::HOSTILE,
			'title'      => 't' . self::HOSTILE,
			'label'      => 'l' . self::HOSTILE,
			'value'      => 'v' . self::HOSTILE,
			'attributes' => ['placeholder' => 'p' . self::HOSTILE],
			'rules'      => ['pattern' => 'r' . self::HOSTILE],
		];

		return [
			'text'      => [new TextRenderer(), static fn() => new TextField($common(FieldType::TEXT))],
			'number'    => [new NumberRenderer(), static fn() => new NumberField($common(FieldType::NUMBER))],
			'email'     => [new EmailRenderer(), static fn() => new EmailField($common(FieldType::EMAIL))],
			'hidden'    => [new HiddenRenderer(), static fn() => new HiddenField($common(FieldType::HIDDEN))],
			'password'  => [new PasswordRenderer(), static fn() => new PasswordField($common(FieldType::PASSWORD))],
			'url'       => [new UrlRenderer(), static fn() => new UrlField($common(FieldType::URL))->setPattern('x' . self::HOSTILE)->setPlaceholder('y' . self::HOSTILE)],
			'checkbox'  => [new CheckboxRenderer(), static fn() => new CheckboxField($common(FieldType::CHECKBOX))],
			'clipboard' => [new ClipboardTextRenderer(), static fn() => new ClipboardTextField($common(FieldType::CLIPBOARD_TEXT))->setDeleteTitle('d' . self::HOSTILE)->setRefreshTitle('r' . self::HOSTILE)],
			'autocomplete' => [new AutocompleteRenderer(), static fn() => new AutocompleteField($common(FieldType::AUTOCOMPLETE) + ['data-label' => 'a' . self::HOSTILE])],
			'dropdown'  => [new DropdownRenderer(), static fn() => new DropdownField($common(FieldType::DROPDOWN) + ['options' => ['k' . self::HOSTILE => 'o' . self::HOSTILE]])],
			'csrf'      => [new CsrfTokenRenderer(), static fn() => new CsrfTokenField($common(FieldType::CSRF), new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession()))],
		];
	}

	/**
	 * @param Closure(): FieldInterface $createField
	 */
	#[Group('units')]
	#[DataProvider('rendererProvider')]
	public function testNoValueBecomesMarkup(FieldRenderInterface $renderer, Closure $createField): void
	{
		$html = $renderer->render($createField());

		static::assertStringNotContainsString('<script', $html);
		static::assertStringNotContainsString('<img', $html);
		static::assertStringContainsString('&lt;script&gt;', $html);

		// as a browser reads it: no script and no image element, no event handler attribute
		$document = new DOMDocument();
		libxml_use_internal_errors(true);
		$document->loadHTML('<html><body>' . $html . '</body></html>');
		libxml_clear_errors();
		static::assertSame(0, $document->getElementsByTagName('script')->length);
		static::assertSame(0, $document->getElementsByTagName('img')->length);
		foreach ($document->getElementsByTagName('*') as $element)
		{
			static::assertFalse($element->hasAttribute('onerror'), 'An event handler was injected into <' . $element->nodeName . '>.');
		}
	}
}
