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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Account\Helper\Password\FormInputHandler;
use App\Modules\Account\Helper\Password\Parameters;
use App\Modules\Account\Helper\Password\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormInputHandlerTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private Validator&MockObject $validatorMock;
	private FormInputHandler $handler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->validatorMock  = $this->createMock(Validator::class);

		$this->handler = new FormInputHandler($this->parametersMock, $this->validatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testValidateParsesBeforeValidating(): void
	{
		$post  = ['password' => 'secret', 'password_confirm' => 'secret'];
		$calls = [];
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with($post)
			->willReturnCallback(function () use (&$calls) { $calls[] = 'set'; return $this->parametersMock; });
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters')
			->willReturnCallback(function () use (&$calls) { $calls[] = 'parse'; return $this->parametersMock; });
		$this->validatorMock->expects($this->once())->method('validateUserInput')->with('pattern')
			->willReturnCallback(function () use (&$calls) { $calls[] = 'validate'; return ['No password.']; });

		static::assertSame(['No password.'], $this->handler->validate($post, 'pattern'));
		static::assertSame(['set', 'parse', 'validate'], $calls);
	}

	#[Group('units')]
	public function testGetParsed(): void
	{
		$this->parametersMock->expects($this->once())->method('getInputParametersKeys')
			->willReturn(['password', 'password_confirm']);
		$this->parametersMock->expects($this->once())->method('getInputValuesArray')
			->willReturn(['secret', 'secret']);
		$this->validatorMock->expects($this->never())->method($this->anything());

		static::assertSame(['password' => 'secret', 'password_confirm' => 'secret'], $this->handler->getParsed());
	}
}
