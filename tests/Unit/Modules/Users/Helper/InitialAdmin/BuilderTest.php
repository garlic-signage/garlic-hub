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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Users\Helper\InitialAdmin\Builder;
use App\Modules\Users\Helper\InitialAdmin\FormElementsCreator;
use App\Modules\Users\Helper\InitialAdmin\Parameters;
use App\Modules\Users\Helper\InitialAdmin\Validator;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private Validator&MockObject $validatorMock;
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private Builder $builder;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock          = $this->createMock(Parameters::class);
		$this->validatorMock           = $this->createMock(Validator::class);
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);

		$this->builder = new Builder($this->parametersMock, $this->validatorMock, $this->formElementsCreatorMock);
	}


	/**
	 * @throws CoreException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithProvidedInput(): void
	{
		$post = [
			Parameters::PARAMETER_ADMIN_NAME => 'testUser',
			Parameters::PARAMETER_ADMIN_EMAIL => 'test@example.com',
			Parameters::PARAMETER_ADMIN_LOCALE => 'fr_FR',
		];

		$fieldInterfaceMock = $this->createMock(FieldInterface::class);
		$expectedForm = ['hidden' => [['some' => 'stuff']], 'visible' => [['some' => 'stuff']]];
		$this->formElementsCreatorMock->method('createUserNameField')
			->with('testUser')
			->willReturn($fieldInterfaceMock);
		$this->formElementsCreatorMock->method('createEmailField')
			->with('test@example.com')
			->willReturn($fieldInterfaceMock);
		$this->formElementsCreatorMock->method('createPasswordField')
			->with('', 'pattern')
			->willReturn($fieldInterfaceMock);
		$this->formElementsCreatorMock->method('createPasswordConfirmField')
			->with('')
			->willReturn($fieldInterfaceMock);
		$this->formElementsCreatorMock->method('createUserLocaleField')
			->with('fr_FR')
			->willReturn($fieldInterfaceMock);
		$this->formElementsCreatorMock->method('createCSRFTokenField')
			->willReturn($fieldInterfaceMock);

		$this->formElementsCreatorMock->method('prepareForm')
			->willReturn($expectedForm);

		$result = $this->builder->buildForm($post, 'pattern');

		static::assertSame($expectedForm, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHandleUserInputParsesAndValidatesData(): void
	{
		$passwordPattern = '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/';
		$userInput = [
			'username' => 'test_user',
			'email' => 'test@example.com',
			'password' => 'Password1',
			'password_confirm' => 'Password1'
		];

		$this->parametersMock->expects($this->once())
			->method('setUserInputs')
			->with($userInput)
			->willReturnSelf();

		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$validationResults = ['key1' => 'value1', 'key2' => 'value2'];
		$this->validatorMock->expects($this->once())->method('validateUserInput')
			->with($passwordPattern)
			->willReturn($validationResults);

		$result = $this->builder->handleUserInput($userInput, $passwordPattern);

		static::assertSame($validationResults, $result);
	}
}
