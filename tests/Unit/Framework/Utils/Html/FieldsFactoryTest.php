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

use App\Framework\Core\CsrfToken;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldType;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class FieldsFactoryTest extends TestCase
{
	private FieldsFactory $fieldsFactory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fieldsFactory = new FieldsFactory();
	}

	#[Group('units')]
	public function testCreateTextField(): void
	{
		$attributes = ['id' => 'username', 'type' => FieldType::TEXT, 'name' => 'user_name'];

		$field = $this->fieldsFactory->createTextField($attributes);

		static::assertSame('username', $field->getId());
		static::assertSame('user_name', $field->getName());
	}

	#[Group('units')]
	public function testCreateNumberField(): void
	{
		$attributes = ['id' => 'status', 'type' => FieldType::NUMBER, 'name' => 'user_status'];

		$field = $this->fieldsFactory->createNumberField($attributes);

		static::assertSame('status', $field->getId());
		static::assertSame('user_status', $field->getName());
	}

	#[Group('units')]
	public function testCreateAutocompleteField(): void
	{
		$attributes = ['id' => 'UID', 'type' => FieldType::AUTOCOMPLETE, 'name' => 'username', 'data-label' => 'test-label'];

		$field = $this->fieldsFactory->createAutocompleteField($attributes);

		static::assertSame('UID', $field->getId());
		static::assertSame('username', $field->getName());
	}

	#[Group('units')]
	public function testCreateDropdownField(): void
	{
		$attributes = ['id' => 'countries', 'type' => FieldType::DROPDOWN, 'name' => 'countries_names', 'options' => []];

		$field = $this->fieldsFactory->createDropdownField($attributes);

		static::assertSame('countries', $field->getId());
		static::assertSame('countries_names', $field->getName());
	}


	#[Group('units')]
	public function testCreateEmailField(): void
	{
		$attributes = ['id' => 'email', 'type' => FieldType::EMAIL, 'name' => 'email_address'];

		$field = $this->fieldsFactory->createEmailField($attributes);

		static::assertSame('email', $field->getId());
		static::assertSame('email_address', $field->getName());
	}

	#[Group('units')]
	public function testCreatePasswordField(): void
	{
		$attributes = ['id' => 'password', 'type' => FieldType::PASSWORD, 'name' => 'user_password'];

		$field = $this->fieldsFactory->createPasswordField($attributes);

		static::assertSame('password', $field->getId());
		static::assertSame('user_password', $field->getName());
	}

	/**
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreateCsrfTokenField(): void
	{
		$attributes = ['id' => 'csrf_token', 'type' => FieldType::CSRF, 'name' => 'csrf_token_name'];

		$field = $this->fieldsFactory->createCsrfTokenField($attributes, $this->createStub(CsrfToken::class));

		static::assertSame('csrf_token', $field->getId());
		static::assertSame('csrf_token_name', $field->getName());
		static::assertEmpty($field->getValue());
	}

	#[Group('units')]
	public function testCreateClipboardTextField(): void
	{
		$attributes = ['id' => 'clipboard', 'type' => FieldType::CLIPBOARD_TEXT, 'name' => 'clipboard-text'];

		$field = $this->fieldsFactory->createClipboardTextField($attributes);

		static::assertSame('clipboard', $field->getId());
		static::assertSame('clipboard-text', $field->getName());
	}

	#[Group('units')]
	public function testCreateUrlField(): void
	{
		$attributes = ['id' => 'url', 'type' => FieldType::URL, 'name' => 'url_link'];

		$field = $this->fieldsFactory->createUrlField($attributes);

		static::assertSame('url', $field->getId());
		static::assertSame('url_link', $field->getName());
	}

	#[Group('units')]
	public function testCheckboxField(): void
	{
		$attributes = ['id' => 'check', 'type' => FieldType::URL, 'name' => 'checkbox'];

		$field = $this->fieldsFactory->createCheckboxField($attributes);

		static::assertSame('check', $field->getId());
		static::assertSame('checkbox', $field->getName());
	}

	#[Group('units')]
	public function testCreateHiddenField(): void
	{
		$attributes = ['id' => 'username', 'type' => FieldType::HIDDEN, 'name' => 'user_name'];

		$field = $this->fieldsFactory->createHiddenField($attributes);

		static::assertSame('username', $field->getId());
		static::assertSame('user_name', $field->getName());
	}

}
