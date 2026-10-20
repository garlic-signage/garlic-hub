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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\Settings\FormInputHandler;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
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
		$post  = ['username' => 'admin', 'password' => 'secret'];
		$calls = [];
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with($post)
			->willReturnCallback(function () use (&$calls) { $calls[] = 'set'; return $this->parametersMock; });
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters')
			->willReturnCallback(function () use (&$calls) { $calls[] = 'parse'; return $this->parametersMock; });
		$this->validatorMock->expects($this->once())->method('validateUserInput')->with($post)
			->willReturnCallback(function () use (&$calls) { $calls[] = 'validate'; return ['No email.']; });

		static::assertSame(['No email.'], $this->handler->validate($post));
		static::assertSame(['set', 'parse', 'validate'], $calls);
	}

	#[Group('units')]
	public function testGetParsed(): void
	{
		$this->parametersMock->expects($this->once())->method('getInputParametersKeys')
			->willReturn(['username', 'email']);
		$this->parametersMock->expects($this->once())->method('getInputValuesArray')
			->willReturn(['admin', 'admin@example.com']);
		$this->validatorMock->expects($this->never())->method($this->anything());

		static::assertSame(['username' => 'admin', 'email' => 'admin@example.com'], $this->handler->getParsed());
	}
}
