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

namespace Tests\Unit\Framework\Validation;

use App\Framework\Validation\BaseValidator;
use App\Framework\Security\CsrfToken;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BaseValidatorTest extends TestCase
{
	private Translator $translatorStub;
	private CsrfToken&MockObject $csrfTokenMock;
	private BaseValidator $baseValidator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorStub = $this->createStub(Translator::class);
		$this->csrfTokenMock = $this->createMock(CsrfToken::class);

		$this->baseValidator = new BaseValidator($this->translatorStub, $this->csrfTokenMock);
	}

	#[Group('units')]
	public function testIsEmailValid(): void
	{
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		static::assertTrue($this->baseValidator->isEmail('test@example.com'));
	}

	#[Group('units')]
	public function testIsEmailInvalid(): void
	{
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		static::assertFalse($this->baseValidator->isEmail('invalid-email'));
	}

	#[Group('units')]
	public function testIsJsonValid(): void
	{
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		static::assertTrue($this->baseValidator->isJson('{"key": "value"}'));
	}

	#[Group('units')]
	public function testIsJsonInvalid(): void
	{
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		static::assertFalse($this->baseValidator->isJson('invalid-json'));
	}

	#[Group('units')]
	public function testIsJsonEmpty(): void
	{
		$this->csrfTokenMock->expects($this->never())->method($this->anything());
		static::assertFalse($this->baseValidator->isJson(''));
	}
	#[Group('units')]
	public function testValidateCsrfTokenValid(): void
	{
		$token = 'validCsrfToken';

		$this->csrfTokenMock
			->expects($this->once())
			->method('validateToken')
			->with($token)
			->willReturn(true);

		static::assertTrue($this->baseValidator->validateCsrfToken($token));
	}

	#[Group('units')]
	public function testValidateCsrfTokenInvalid(): void
	{
		$token = 'invalidCsrfToken';

		$this->csrfTokenMock->expects($this->once())->method('validateToken')
			->with($token)
			->willReturn(false);

		static::assertFalse($this->baseValidator->validateCsrfToken($token));
	}
}
