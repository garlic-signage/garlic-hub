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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Html\FieldType;
use App\Modules\Account\Helper\Password\FormElementsCreator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The creator and the FormBuilder are the real ones: the result is the html of the field.
 */
class FormElementsCreatorTest extends TestCase
{
	private PasswordFixture $fixture;
	private FormElementsCreator $creator;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(PasswordFixture::translation());
		$this->fixture = new PasswordFixture($translator);
		$this->creator = new FormElementsCreator($this->fixture->formBuilder, $translator);
	}

	/**
	 * @return array<string,string> the attributes of the (only) input of the field
	 * @throws \Exception
	 */
	private function attributesOf(\App\Framework\Utils\Html\FieldInterface $field): array
	{
		$html    = $this->fixture->formBuilder->prepareForm(['field' => $field]);
		$element = PasswordFixture::element($html['visible'][0]['HTML_ELEMENT'] ?? $html['hidden'][0]['HIDDEN_HTML_ELEMENT']);
		static::assertNotNull($element);
		$attributes = [];
		foreach ($element->attributes ?? [] as $attribute)
			$attributes[$attribute->name] = $attribute->value;

		return $attributes;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testThePasswordFieldIsRequiredHasTheMinimalLengthAndThePattern(): void
	{
		$field = $this->creator->createPasswordField('', '(?=.*\d).{8,}');

		static::assertSame(FieldType::PASSWORD, $field->getType());
		static::assertSame('password', $field->getName());
		static::assertSame('account.password', $field->getLabel());
		static::assertSame(
			['type' => 'password', 'name' => 'password', 'id' => 'password', 'value' => '', 'title' => 'account.password_explanation', 'label' => 'account.password',
			 'pattern' => '(?=.*\d).{8,}', 'required' => 'required', 'minlength' => '8', 'aria-describedby' => 'error_password'],
			$this->attributesOf($field)
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testThePasswordConfirmFieldHasNoPattern(): void
	{
		$field = $this->creator->createPasswordConfirmField('');

		static::assertSame(FieldType::PASSWORD, $field->getType());
		static::assertSame('password_confirm', $field->getName());
		static::assertSame(
			['type' => 'password', 'name' => 'password_confirm', 'id' => 'password_confirm', 'value' => '', 'title' => 'account.password_explanation', 'label' => 'account.password_confirm',
			 'required' => 'required', 'minlength' => '8', 'aria-describedby' => 'error_password_confirm'],
			$this->attributesOf($field)
		);
	}

	/**
	 * A value or a pattern with quotes must not leave the attribute.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testValueAndPatternAreEscaped(): void
	{
		$attributes = $this->attributesOf($this->creator->createPasswordField('"><script>alert(1)</script>', '.*"onfocus="alert(1)'));

		static::assertSame('"><script>alert(1)</script>', $attributes['value']);
		static::assertSame('.*"onfocus="alert(1)', $attributes['pattern']);
		static::assertArrayNotHasKey('onfocus', $attributes);
	}

	/**
	 * @throws FrameworkException
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheTokenFieldIsHiddenAndCarriesTheToken(): void
	{
		$field = $this->creator->createPasswordTokenField('abcd');

		static::assertSame(FieldType::HIDDEN, $field->getType());
		static::assertSame(['type' => 'hidden', 'name' => 'token', 'id' => 'token', 'value' => 'abcd'], $this->attributesOf($field));
	}
}
