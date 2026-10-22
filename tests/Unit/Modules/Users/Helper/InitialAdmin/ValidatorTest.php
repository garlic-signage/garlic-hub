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

use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Helper\InitialAdmin\Parameters;
use App\Modules\Users\Helper\InitialAdmin\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private Parameters&MockObject $parametersMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private Validator $validator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock = $this->createMock(Translator::class);
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->csrfTokenMock  = $this->createMock(CsrfToken::class);

		$this->validator = new Validator($this->translatorMock, $this->parametersMock, $this->csrfTokenMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithValidData(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->expects($this->exactly(4))->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'secure!Password123'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'secure!Password123'],
		]);

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertEmpty($errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithMissingAdminName(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, ''],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'securePassword123'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'securePassword123'],
		]);

		$this->translatorMock->method('translate')->with('no_username', 'users')->willReturn('No username provided.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame('No username provided.', $errors[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithInvalidEmail(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'invalid-email'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'securePassword123'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'securePassword123'],
		]);

		$this->translatorMock->method('translate')->with('no_email', 'users')->willReturn('No email provided.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame('No email provided.', $errors[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithMissingPassword(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, ''],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'password123'],
		]);

		$this->translatorMock->expects($this->exactly(3))->method('translate')
			->willReturnMap([
				['no_password', 'users', [], 'No password.'],
				['no_passwords_match', 'users', [], 'No match.'],
				['password_explanation', 'users', [], 'Explain.'],
			]);

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(3, $errors);
		static::assertSame(['No password.', 'No match.', 'Explain.'], $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidatePasswordNoConfirm(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'securePassword5443'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, ''],
		]);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['no_password_confirm', 'users', [], 'No confirm.'],
				['no_passwords_match', 'users', [], 'No match.']
			]);

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(2, $errors);
		static::assertSame(['No confirm.', 'No match.'], $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithUnmatchedPasswords(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'password-Secure-123'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'differentPassword'],
		]);

		$this->translatorMock->method('translate')->with('no_passwords_match', 'users')->willReturn('Passwords do not match.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame('Passwords do not match.', $errors[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithInvalidPassword(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_ADMIN_NAME, 'Admin'],
			[Parameters::PARAMETER_ADMIN_EMAIL, 'admin@example.com'],
			[Parameters::PARAMETER_ADMIN_PASSWORD, 'short'],
			[Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM, 'short'],
		]);

		$this->translatorMock->method('translate')->with('password_explanation', 'users')->willReturn('Invalid password.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame('Invalid password.', $errors[0]);
	}

	private function checkCsrfTokenTrue(): void
	{
		$this->parametersMock->expects($this->once())->method('getCsrfToken')
			->willReturn('test');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')
			->with('test')
			->willReturn(true);
	}
}
