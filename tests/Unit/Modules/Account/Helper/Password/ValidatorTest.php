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

use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Account\Helper\Password\Parameters;
use App\Modules\Account\Helper\Password\Validator;
use Exception;
use PHPUnit\Framework\Attributes\Group;
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
	 * @throws \PHPUnit\Framework\MockObject\Exception
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
	public function testValidateUserInputWithMissingPasswordAndConfirm(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_PASSWORD, ''],
			[Parameters::PARAMETER_PASSWORD_CONFIRM, ''],
		]);

		$this->translatorMock->expects($this->exactly(3))->method('translate')
			->willReturnMap([
				['no_password', 'account', [], 'No password.'],
				['no_password_confirm', 'account', [], 'No confirm.'],
				['password_explanation', 'account', [], 'Explain.']
			]);

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(3, $errors);
		static::assertSame(['No password.', 'No confirm.',  'Explain.'], $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateUserInputWithInvalidPasswordPattern(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_PASSWORD, 'short'],
			[Parameters::PARAMETER_PASSWORD_CONFIRM, 'short'],
		]);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('password_explanation', 'account')
			->willReturn('Invalid password.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame(['Invalid password.'], $errors);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateNoMatch(): void
	{
		$passwordPattern = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_PASSWORD, 'securePAASword123'],
			[Parameters::PARAMETER_PASSWORD_CONFIRM, 'short'],
		]);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('no_passwords_match', 'account')
			->willReturn('No match.');

		$errors = $this->validator->validateUserInput($passwordPattern);

		static::assertCount(1, $errors);
		static::assertSame(['No match.'], $errors);
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
