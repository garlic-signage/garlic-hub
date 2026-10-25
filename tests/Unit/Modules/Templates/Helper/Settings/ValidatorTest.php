<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
	private Parameters&Stub $parametersStub;
	private CsrfToken&MockObject $csrfTokenMock;
	private Validator $validator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')->willReturnMap([
			['csrf_token_mismatch', 'security', [], 'CSRF token mismatch.'],
			['name_noexists', 'templates', [], 'Name is missing.'],
			['template_type_noexists', 'templates', [], 'Type is not valid.'],
		]);
		$translatorStub->method('translateArrayForOptions')->willReturnMap([['types_selects', 'templates', ['canvas' => 'Canvas']]]);
		$this->parametersStub = $this->createStub(Parameters::class);
		$this->csrfTokenMock  = $this->createMock(CsrfToken::class);

		$this->validator = new Validator($translatorStub, $this->parametersStub, $this->csrfTokenMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateEditInputPasses(): void
	{
		$this->mockInput('token', 'My Template', 'canvas');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('token')->willReturn(true);

		static::assertSame([], $this->validator->validateEditInput());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateEditInputWithoutCsrfTokenAndName(): void
	{
		$this->mockInput('', '', 'canvas');
		$this->csrfTokenMock->expects($this->never())->method('validateToken');

		static::assertSame(['CSRF token mismatch.', 'Name is missing.'], $this->validator->validateEditInput());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateEditInputWithInvalidCsrfToken(): void
	{
		$this->mockInput('wrong', 'My Template', 'canvas');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('wrong')->willReturn(false);

		static::assertSame(['CSRF token mismatch.'], $this->validator->validateEditInput());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateCreateInputPasses(): void
	{
		$this->mockInput('token', 'My Template', 'canvas');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		static::assertSame([], $this->validator->validateCreateInput());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateCreateInputWithoutNameAndType(): void
	{
		$this->mockInput('token', '', '');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		static::assertSame(['Name is missing.', 'Type is not valid.'], $this->validator->validateCreateInput());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateCreateInputWithATypeWhichIsNotInTheList(): void
	{
		$this->mockInput('token', 'My Template', 'fabric.js');
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		static::assertSame(['Type is not valid.'], $this->validator->validateCreateInput());
	}

	private function mockInput(string $csrfToken, string $name, string $type): void
	{
		$this->parametersStub->method('getCsrfToken')->willReturn($csrfToken);
		$this->parametersStub->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_NAME, $name],
			[Parameters::PARAMETER_TYPE, $type],
		]);
	}
}
