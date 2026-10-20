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


namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Core\CsrfToken;
use App\Framework\Core\Translate\Translator;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use App\Modules\Player\Helper\NetworkSettings\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private Parameters&MockObject $parametersMock;
	private Validator $validator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->translatorMock = $this->createMock(Translator::class);
		$this->csrfTokenMock = $this->createMock(CsrfToken::class);
		$this->parametersMock = $this->createMock(Parameters::class);

		$this->validator = new Validator(
			$this->translatorMock,
			$this->parametersMock,
			$this->csrfTokenMock
		);
	}

	#[Group('units')]
	public function testIntranetNoApiEndpoint(): void
	{
		$this->checkCsrfTokenTrue();
		$this->parametersMock->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_IS_INTRANET, true],
				[Parameters::PARAMETER_API_ENDPOINT, '']
			]);

		$this->translatorMock
			->method('translate')
			->with('no_api_endpoint', 'player')
			->willReturn('No API endpoint is set.');

		$errors = $this->validator->validateUserInput();

		self::assertCount(1, $errors);
		self::assertContains('No API endpoint is set.', $errors);
	}

	#[Group('units')]
	public function testNoIntranet(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$this->checkCsrfTokenTrue();
		$this->parametersMock->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_IS_INTRANET, false],
				[Parameters::PARAMETER_API_ENDPOINT, '']
			]);

		$errors = $this->validator->validateUserInput();

		self::assertCount(0, $errors);
	}

	#[Group('units')]
	public function testIntranetWithApiEndpoint(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$this->checkCsrfTokenTrue();
		$this->parametersMock
			->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_IS_INTRANET, true],
				[Parameters::PARAMETER_API_ENDPOINT, 'http://example.com']
			]);

		$errors = $this->validator->validateUserInput();

		self::assertCount(0, $errors);
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
