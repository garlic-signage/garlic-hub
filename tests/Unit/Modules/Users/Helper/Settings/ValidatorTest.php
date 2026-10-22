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


namespace Tests\Unit\Modules\Users\Helper\Settings;

use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\Validator;
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
	public function testValidateSucceed(): void
	{
		$this->checkCsrfTokenTrue();

		$this->parametersMock->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USER_NAME, 'Valid Name'],
				[Parameters::PARAMETER_USER_EMAIL, 'valid@em.ail']
			]);

		$this->translatorMock->expects($this->never())->method('translate');

		static::assertEmpty($this->validator->validateUserInput());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateFailsEmpty(): void
	{
		$this->checkCsrfTokenTrue();

		$this->parametersMock->expects($this->exactly(2))->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USER_NAME, ''],
				[Parameters::PARAMETER_USER_EMAIL, '']
			]);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['no_username', 'users', [], 'No Username'],
				['no_email', 'users', [], 'No Email'],
			]);

		static::assertNotEmpty($this->validator->validateUserInput());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testValidateFailsWrongMail(): void
	{
		$this->checkCsrfTokenTrue();

		$this->parametersMock->expects($this->exactly(2))->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USER_NAME, 'Valid Name'],
				[Parameters::PARAMETER_USER_EMAIL, 'invalid+email']
			]);

		$this->translatorMock->expects($this->once())->method('translate')
			->willReturnMap([
				['no_email', 'users', [], 'No Email'],
			]);

		static::assertNotEmpty($this->validator->validateUserInput());
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
