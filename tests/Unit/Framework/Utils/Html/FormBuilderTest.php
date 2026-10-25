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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\UrlField;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * FormBuilder with the real factories, fields and renderers and a CSRF token on a session in memory.
 */
class FormBuilderTest extends TestCase
{
	private CsrfToken $csrfToken;
	private FormBuilder $formBuilder;

	/**
	 * @throws EnvironmentIsBrokenException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->csrfToken   = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$this->formBuilder = new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), $this->csrfToken);
	}

	/**
	 * @return array<string, array{FieldType, class-string, array<string,mixed>}> type, class and the attributes it needs
	 */
	public static function fieldTypeProvider(): array
	{
		return [
			'text'           => [FieldType::TEXT, TextField::class, []],
			'number'         => [FieldType::NUMBER, NumberField::class, []],
			'dropdown'       => [FieldType::DROPDOWN, DropdownField::class, ['options' => []]],
			'url'            => [FieldType::URL, UrlField::class, []],
			'checkbox'       => [FieldType::CHECKBOX, CheckboxField::class, []],
			'autocomplete'   => [FieldType::AUTOCOMPLETE, AutocompleteField::class, ['data-label' => 'l']],
			'password'       => [FieldType::PASSWORD, PasswordField::class, []],
			'email'          => [FieldType::EMAIL, EmailField::class, []],
			'hidden'         => [FieldType::HIDDEN, HiddenField::class, []],
			'clipboard text' => [FieldType::CLIPBOARD_TEXT, ClipboardTextField::class, []],
			'csrf'           => [FieldType::CSRF, CsrfTokenField::class, []],
		];
	}

	/**
	 * @param class-string $class
	 * @param array<string,mixed> $needs
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('fieldTypeProvider')]
	public function testCreateFieldGivesTheFieldOfTheType(FieldType $type, string $class, array $needs): void
	{
		$field = $this->formBuilder->createField(['id' => 'f', 'type' => $type, 'name' => 'field_name'] + $needs);

		static::assertInstanceOf($class, $field);
		static::assertSame('f', $field->getId());
		static::assertSame('field_name', $field->getName());
		static::assertSame($type, $field->getType());
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheCsrfFieldCarriesTheTokenOfTheSession(): void
	{
		$field = $this->formBuilder->createField(['id' => 'csrf', 'type' => FieldType::CSRF, 'name' => 'csrf_token']);

		static::assertSame($this->csrfToken->getToken(), $field->getValue());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('invalidTypeProvider')]
	public function testInvalidFieldTypeThrowsException(mixed $type): void
	{
		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Invalid field type');

		$this->formBuilder->createField(['id' => 'f', 'type' => $type]);
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalidTypeProvider(): array
	{
		return ['unknown text' => ['invalid'], 'type text instead of the enum' => ['text'], 'date has no field yet' => [FieldType::DATE], 'null' => [null]];
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFormSeparatesHiddenAndVisibleFields(): void
	{
		$text = $this->formBuilder->createField(['id' => 'text_field_1', 'type' => FieldType::TEXT, 'name' => 'title', 'label' => 'Text Field', 'value' => 'abc']);
		$csrf = $this->formBuilder->createField(['id' => 'csrf', 'type' => FieldType::CSRF, 'name' => 'csrf_token']);
		$id   = $this->formBuilder->createField(['id' => 'playlist', 'type' => FieldType::HIDDEN, 'name' => 'playlist_id', 'value' => 5]);

		$result = $this->formBuilder->prepareForm(['text' => $text, 'csrf' => $csrf, 'id' => $id]);

		static::assertSame(
			[[
				'HTML_ELEMENT_ID'    => 'text_field_1',
				'LANG_ELEMENT_LABEL' => 'Text Field',
				'LANG_ELEMENT_NAME'  => 'title',
				'ELEMENT_MUST_FIELD' => '',
				'HTML_ELEMENT'       => '<input type="text" name="title" id="text_field_1" value="abc" label="Text Field" aria-describedby="error_text_field_1">',
			]],
			$result['visible']
		);
		static::assertSame(
			[
				['HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="csrf_token" id="csrf" value="' . $this->csrfToken->getToken() . '">'],
				['HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="playlist_id" id="playlist" value="5">'],
			],
			$result['hidden']
		);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFormKeepsTheOrderOfTheFields(): void
	{
		$fields = [];
		foreach (['first', 'second', 'third'] as $id)
			$fields['key_' . $id] = $this->formBuilder->createField(['id' => $id, 'type' => FieldType::TEXT]);

		$result = $this->formBuilder->prepareForm($fields);

		static::assertSame(['first', 'second', 'third'], array_column($result['visible'], 'HTML_ELEMENT_ID'));
		static::assertSame([0, 1, 2], array_keys($result['visible']), 'The result is a list, the keys of the fields are dropped.');
	}

	#[Group('units')]
	public function testPrepareFormWithoutFields(): void
	{
		static::assertSame(['hidden' => [], 'visible' => []], $this->formBuilder->prepareForm([]));
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareFormWithOnlyHiddenFields(): void
	{
		$result = $this->formBuilder->prepareForm(['a' => $this->formBuilder->createField(['id' => 'a', 'type' => FieldType::HIDDEN])]);

		static::assertSame([], $result['visible']);
		static::assertCount(1, $result['hidden']);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testRenderField(): void
	{
		$field = $this->formBuilder->createField(['id' => 'mail', 'type' => FieldType::EMAIL, 'name' => 'email', 'value' => 'a@b.c']);

		static::assertSame('<input type="email" name="email" id="mail" value="a@b.c" aria-describedby="error_mail">', $this->formBuilder->renderField($field));
	}
}
