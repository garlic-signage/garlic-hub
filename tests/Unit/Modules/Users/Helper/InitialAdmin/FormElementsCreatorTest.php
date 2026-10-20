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


namespace Tests\Unit\Modules\Users\Helper\InitialAdmin;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\PasswordField;
use App\Modules\Users\Helper\InitialAdmin\FormElementsCreator;
use App\Modules\Users\Helper\InitialAdmin\Parameters;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormElementsCreatorTest extends TestCase
{
	private FormBuilder&MockObject $formBuilderMock;
	private Translator&MockObject $translatorMock;
	private FormElementsCreator $creator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->translatorMock  = $this->createMock(Translator::class);

		$this->creator = new FormElementsCreator($this->formBuilderMock, $this->translatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreateUserNameField(): void
	{
		$value = 'My Playlist';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->with(Parameters::PARAMETER_ADMIN_NAME, 'main')
			->willReturn('User name');

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::TEXT,
				'id' => Parameters::PARAMETER_ADMIN_NAME,
				'name' => Parameters::PARAMETER_ADMIN_NAME,
				'title' => 'User name',
				'label' => 'User name',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 2],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->creator->createUserNameField($value);
		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws CoreException
	 * @throws InvalidArgumentException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordField(): void
	{
		$value = 'securePassword123';
		$pattern = '(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,}';
		$expectedField = $this->createMock(PasswordField::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['password_explanation', 'profile', [], 'Please provide a secure password'],
				[Parameters::PARAMETER_ADMIN_PASSWORD, 'profile', [], 'Password']
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::PASSWORD,
				'id' => Parameters::PARAMETER_ADMIN_PASSWORD,
				'name' => Parameters::PARAMETER_ADMIN_PASSWORD,
				'title' => 'Please provide a secure password',
				'label' => 'Password',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 8],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$expectedField->expects($this->once())->method('setPattern')
			->with($pattern);

		$result = $this->creator->createPasswordField($value, $pattern);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordConfirmField(): void
	{
		$value = 'securePassword123';
		$expectedField = $this->createMock(PasswordField::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['password_explanation', 'profile', [], 'Please provide a secure password'],
				[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'profile', [], 'Confirm Password']
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::PASSWORD,
				'id' => Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM,
				'name' => Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM,
				'title' => 'Please provide a secure password',
				'label' => 'Confirm Password',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 8],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->creator->createPasswordConfirmField($value);

		static::assertSame($expectedField, $result);
	}


	/**
	 * Tests the creation of the user locale field.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreateUserLocaleField(): void
	{
		$value = 'en_US';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				[Parameters::PARAMETER_ADMIN_LOCALE, 'users', [], 'Preferred Locale'],
				[Parameters::PARAMETER_ADMIN_LOCALE, 'users', [], 'Preferred Locale']
			]);

		$this->translatorMock->expects($this->once())->method('translateArrayForOptions')
			->with('languages', 'menu')
			->willReturn(['en_US' => 'English (US)', 'de_DE' => 'German (Germany)']);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::DROPDOWN,
				'id' => Parameters::PARAMETER_ADMIN_LOCALE,
				'name' => Parameters::PARAMETER_ADMIN_LOCALE,
				'title' => 'Preferred Locale',
				'label' => 'Preferred Locale',
				'value' => $value,
				'options' => ['en_US' => 'English (US)', 'de_DE' => 'German (Germany)'],
				'options_zero' => false
			])
			->willReturn($expectedField);

		$result = $this->creator->createUserLocaleField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreateEmailField(): void
	{
		$value = 'test@example.com';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				[Parameters::PARAMETER_ADMIN_EMAIL, 'users', [], 'Email Address'],
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::EMAIL,
				'id' => Parameters::PARAMETER_ADMIN_EMAIL,
				'name' => Parameters::PARAMETER_ADMIN_EMAIL,
				'title' => 'Email Address',
				'label' => 'Email Address',
				'value' => $value,
				'rules' => ['required' => true],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->creator->createEmailField($value);

		static::assertSame($expectedField, $result);
	}
}
