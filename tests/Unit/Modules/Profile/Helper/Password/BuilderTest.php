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


namespace Tests\Unit\Modules\Profile\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Profile\Helper\Password\Builder;
use App\Modules\Profile\Helper\Password\FormElementsCreator;
use App\Modules\Profile\Helper\Password\Parameters;
use App\Modules\Profile\Helper\Password\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private Validator&MockObject $validatorMock;
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private Builder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->validatorMock = $this->createMock(Validator::class);
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->builder = new Builder($this->parametersMock, $this->validatorMock, $this->formElementsCreatorMock);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithEmptyPasswordToken(): void
	{
		$pattern = '^.{8,}$';
		$mockPasswordField = $this->createMock(FieldInterface::class);
		$mockPasswordConfirmField = $this->createMock(FieldInterface::class);
		$mockCsrfTokenField = $this->createMock(FieldInterface::class);
		$preparedForm = ['password' => $mockPasswordField, 'password_confirm' => $mockPasswordConfirmField, 'csrf_token' => $mockCsrfTokenField];

		$this->formElementsCreatorMock->expects($this->once())
			->method('createPasswordField')
			->with('', $pattern)
			->willReturn($mockPasswordField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('createPasswordConfirmField')
			->with('')
			->willReturn($mockPasswordConfirmField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('createCSRFTokenField')
			->willReturn($mockCsrfTokenField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('prepareForm')
			->with([
				'password' => $mockPasswordField,
				'password_confirm' => $mockPasswordConfirmField,
				'csrf_token' => $mockCsrfTokenField,
			])
			->willReturn($preparedForm);

		$result = $this->builder->buildForm($pattern);

		static::assertSame($preparedForm, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithPasswordToken(): void
	{
		$pattern = '^.{8,}$';
		$passwordToken = 'testToken';
		$mockPasswordField = $this->createMock(FieldInterface::class);
		$mockPasswordConfirmField = $this->createMock(FieldInterface::class);
		$mockCsrfTokenField = $this->createMock(FieldInterface::class);
		$mockPasswordTokenField = $this->createMock(FieldInterface::class);
		$preparedForm = ['password' => $mockPasswordField, 'password_confirm' => $mockPasswordConfirmField, 'csrf_token' => $mockCsrfTokenField, 'password_token' => $mockPasswordTokenField];

		$this->formElementsCreatorMock->expects($this->once())
			->method('createPasswordField')
			->with('', $pattern)
			->willReturn($mockPasswordField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('createPasswordConfirmField')
			->with('')
			->willReturn($mockPasswordConfirmField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('createCSRFTokenField')
			->willReturn($mockCsrfTokenField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('createPasswordTokenField')
			->with($passwordToken)
			->willReturn($mockPasswordTokenField);

		$this->formElementsCreatorMock->expects($this->once())
			->method('prepareForm')
			->with([
				'password' => $mockPasswordField,
				'password_confirm' => $mockPasswordConfirmField,
				'csrf_token' => $mockCsrfTokenField,
				'password_token' => $mockPasswordTokenField,
			])
			->willReturn($preparedForm);

		$result = $this->builder->buildForm($pattern, $passwordToken);

		static::assertSame($preparedForm, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHandleUserInput(): void
	{
		$userInputs = ['key1' => 'value1', 'key2' => 'value2'];
		$pattern = '/^[A-Za-z0-9]+$/';

		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with($userInputs)
			->willReturnSelf();

		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$expectedValidationResult = ['status' => 'success'];
		$this->validatorMock->expects($this->once())->method('validateUserInput')
			->with($pattern)
			->willReturn($expectedValidationResult);

		$result = $this->builder->handleUserInput($userInputs, $pattern);

		static::assertSame($expectedValidationResult, $result);
	}
}
