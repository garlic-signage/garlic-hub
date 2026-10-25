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
use App\Framework\Utils\Html\AbstractInputField;
use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldType;
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

class FieldsFactoryTest extends TestCase
{
	private FieldsFactory $fieldsFactory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fieldsFactory = new FieldsFactory();
	}

	/**
	 * @return array<string, array{string, class-string, FieldType, array<string,mixed>}> create method, class, type, needed attributes
	 */
	public static function createMethodProvider(): array
	{
		return [
			'text'           => ['createTextField', TextField::class, FieldType::TEXT, []],
			'number'         => ['createNumberField', NumberField::class, FieldType::NUMBER, []],
			'autocomplete'   => ['createAutocompleteField', AutocompleteField::class, FieldType::AUTOCOMPLETE, ['data-label' => 'test-label']],
			'dropdown'       => ['createDropdownField', DropdownField::class, FieldType::DROPDOWN, ['options' => []]],
			'email'          => ['createEmailField', EmailField::class, FieldType::EMAIL, []],
			'password'       => ['createPasswordField', PasswordField::class, FieldType::PASSWORD, []],
			'clipboard text' => ['createClipboardTextField', ClipboardTextField::class, FieldType::CLIPBOARD_TEXT, []],
			'url'            => ['createUrlField', UrlField::class, FieldType::URL, []],
			'checkbox'       => ['createCheckboxField', CheckboxField::class, FieldType::CHECKBOX, []],
			'hidden'         => ['createHiddenField', HiddenField::class, FieldType::HIDDEN, []],
		];
	}

	/**
	 * @param class-string $class
	 * @param array<string,mixed> $needs
	 */
	#[Group('units')]
	#[DataProvider('createMethodProvider')]
	public function testEachMethodCreatesItsFieldWithTheAttributes(string $method, string $class, FieldType $type, array $needs): void
	{
		$field = $this->fieldsFactory->$method(['id' => 'status', 'type' => $type, 'name' => 'user_status', 'value' => '3'] + $needs);

		static::assertInstanceOf($class, $field);
		static::assertInstanceOf(AbstractInputField::class, $field);
		static::assertSame('status', $field->getId());
		static::assertSame('user_status', $field->getName());
		static::assertSame($type, $field->getType());
		static::assertSame('3', $field->getValue());
	}

	/**
	 * @throws Exception
	 * @throws EnvironmentIsBrokenException
	 */
	#[Group('units')]
	public function testCreateCsrfTokenField(): void
	{
		$csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());

		$field = $this->fieldsFactory->createCsrfTokenField(['id' => 'csrf_token', 'type' => FieldType::CSRF, 'name' => 'csrf_token_name'], $csrfToken);

		static::assertSame('csrf_token', $field->getId());
		static::assertSame('csrf_token_name', $field->getName());
		static::assertSame($csrfToken->getToken(), $field->getValue());
	}

	#[Group('units')]
	public function testEveryCallCreatesANewField(): void
	{
		$attributes = ['id' => 'a', 'type' => FieldType::TEXT];

		static::assertNotSame($this->fieldsFactory->createTextField($attributes), $this->fieldsFactory->createTextField($attributes));
	}
}
