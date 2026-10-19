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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Helper\Settings\FormInputHandler;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\Validator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class FormInputHandlerTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private Validator&MockObject $validatorMock;
	private FormInputHandler $handler;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
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
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testValidateCreateParsesBeforeValidating(): void
	{
		$calls = [];
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with(['name' => 'x'])
			->willReturnCallback(function () use (&$calls) { $calls[] = 'set'; return $this->parametersMock; });
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters')
			->willReturnCallback(function () use (&$calls) { $calls[] = 'parse'; return $this->parametersMock; });
		$this->validatorMock->expects($this->once())->method('validateCreateInput')
			->willReturnCallback(function () use (&$calls) { $calls[] = 'validate'; return ['Type is missing.']; });
		$this->validatorMock->expects($this->never())->method('validateEditInput');

		static::assertSame(['Type is missing.'], $this->handler->validateCreate(['name' => 'x']));
		static::assertSame(['set', 'parse', 'validate'], $calls);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testValidateEdit(): void
	{
		$this->parametersMock->expects($this->once())->method('setUserInputs')->with(['name' => 'x']);
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');
		$this->validatorMock->expects($this->once())->method('validateEditInput')->willReturn([]);
		$this->validatorMock->expects($this->never())->method('validateCreateInput');

		static::assertSame([], $this->handler->validateEdit(['name' => 'x']));
	}

	#[Group('units')]
	public function testGetParsed(): void
	{
		$this->parametersMock->expects($this->once())->method('getInputParametersKeys')->willReturn(['csrf_token', 'name']);
		$this->parametersMock->expects($this->once())->method('getInputValuesArray')->willReturn(['token', 'Tpl']);
		$this->validatorMock->expects($this->never())->method($this->anything());

		static::assertSame(['csrf_token' => 'token', 'name' => 'Tpl'], $this->handler->getParsed());
	}
}
