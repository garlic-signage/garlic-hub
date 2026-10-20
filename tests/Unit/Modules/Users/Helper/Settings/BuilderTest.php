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

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Users\Helper\Settings\Builder;
use App\Modules\Users\Helper\Settings\FormElementsCreator;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\Validator;
use App\Modules\Users\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
	private AclValidator&MockObject $aclValidatorMock;
	private Validator&MockObject $validatorMock;
	private Parameters&MockObject $parametersMock;
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private Session&MockObject $sessionMock;
	private Builder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->aclValidatorMock        = $this->createMock(AclValidator::class);
		$this->validatorMock           = $this->createMock(Validator::class);
		$this->parametersMock          = $this->createMock(Parameters::class);
		$this->sessionMock      = $this->createMock(Session::class);

		$this->builder                 = new Builder(
			$this->aclValidatorMock, $this->parametersMock, $this->validatorMock, $this->formElementsCreatorMock);
	}

	#[Group('units')]
	public function testInit(): void
	{
		$this->expectUntouched($this->formElementsCreatorMock, $this->aclValidatorMock, $this->validatorMock, $this->parametersMock);
		$user = ['UID' => 123, 'username' => 'username'];
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn($user);
		static::assertSame($this->builder, $this->builder->init($this->sessionMock));
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigNewParameterWithSimpleAdmin(): void
	{
		$this->formElementsCreatorMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->setStandardMocks();

		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')
			->with(987)
			->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addUserName');
		$this->parametersMock->expects($this->once())->method('addUserEmail');
		$this->parametersMock->expects($this->once())->method('addUserStatus');
		$this->parametersMock->expects($this->once())->method('addUserLocale');

		$this->builder->configNewParameter();
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigNewParameterNoAdmin(): void
	{
		$this->formElementsCreatorMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->setStandardMocks();
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')
			->willReturn(false);

		$this->parametersMock->expects($this->never())->method('addUserName');
		$this->parametersMock->expects($this->never())->method('addUserEmail');
		$this->parametersMock->expects($this->never())->method('addUserStatus');
		$this->parametersMock->expects($this->never())->method('addUserLocale');

		$this->builder->configNewParameter();
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigEditParameterWithAdmin(): void
	{
		$this->formElementsCreatorMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->never())->method($this->anything());
		$this->setStandardMocks();
		$user = ['UID' => 987, 'company_id' => 1, 'username' => 'username'];
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')
			->with(987)
			->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addUserName');
		$this->parametersMock->expects($this->once())->method('addUserEmail');
		$this->parametersMock->expects($this->once())->method('addUserStatus');
		$this->parametersMock->expects($this->once())->method('addUserLocale');

		$this->builder->configEditParameter($user);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigEditParameterNoAdmin(): void
	{
		$this->formElementsCreatorMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->never())->method($this->anything());
		$user = ['UID' => 987, 'company_id' => 1, 'username' => 'username'];
		$this->setStandardMocks();
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')
			->willReturn(false);

		$this->parametersMock->expects($this->never())->method('addUserName');
		$this->parametersMock->expects($this->never())->method('addUserEmail');
		$this->parametersMock->expects($this->never())->method('addUserStatus');
		$this->parametersMock->expects($this->never())->method('addUserLocale');

		$this->builder->configEditParameter($user);
	}

	/**
	 * Tests the `buildForm` method when all parameters are available and tokens are provided.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithAllParameters(): void
	{
		$user = [
			Parameters::PARAMETER_USER_NAME => 'test_user',
			Parameters::PARAMETER_USER_EMAIL => 'user@example.com',
			Parameters::PARAMETER_USER_STATUS => 1,
			Parameters::PARAMETER_USER_LOCALE => 'fr_FR',
			Parameters::PARAMETER_USER_ID => 999,
			'tokens' => [
				['token' => 'abcd1234', 'purpose' => 'test', 'expires_at' => '2030-01-01']
			]
		];

		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->validatorMock->expects($this->never())->method($this->anything());
		// no token value in the session yet, so the clipboard field stays empty
		$this->sessionMock->expects($this->once())->method('exists')->with('test_token')->willReturn(false);
		$this->sessionMock->expects($this->never())->method('delete');

		$this->parametersMock->expects($this->exactly(4))->method('hasParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USER_NAME, true],
				[Parameters::PARAMETER_USER_EMAIL, true],
				[Parameters::PARAMETER_USER_STATUS, true],
				[Parameters::PARAMETER_USER_LOCALE, true],
			]);

		$fieldInterfaceStub = $this->createStub(FieldInterface::class);

		$this->formElementsCreatorMock->expects($this->once())->method('createUserNameField')
			->with($user[Parameters::PARAMETER_USER_NAME])
			->willReturn($fieldInterfaceStub);

		$this->formElementsCreatorMock->expects($this->once())->method('createEmailField')
			->with($user[Parameters::PARAMETER_USER_EMAIL])
			->willReturn($fieldInterfaceStub);

		$this->formElementsCreatorMock->expects($this->once())->method('createUserStatusField')
			->with((string)$user[Parameters::PARAMETER_USER_STATUS])
			->willReturn($fieldInterfaceStub);

		$this->formElementsCreatorMock->expects($this->once())->method('createUserLocaleField')
			->with($user[Parameters::PARAMETER_USER_LOCALE])
			->willReturn($fieldInterfaceStub);

		$this->formElementsCreatorMock->expects($this->once())->method('createHiddenUIDField')
			->with($user[Parameters::PARAMETER_USER_ID])
			->willReturn($fieldInterfaceStub);

		$clipboardTextFieldStub = $this->createStub(ClipboardTextField::class);
		$this->formElementsCreatorMock->expects($this->once())->method('createClipboardTextField')
			->with('', 'test', '2030-01-01')
			->willReturn($clipboardTextFieldStub);

		$this->formElementsCreatorMock->method('createCSRFTokenField')
			->willReturn($fieldInterfaceStub);

		$this->formElementsCreatorMock->expects($this->once())
			->method('prepareForm')
			->with([
				'username' => $fieldInterfaceStub,
				'email' => $fieldInterfaceStub,
				'status' => $fieldInterfaceStub,
				'locale' => $fieldInterfaceStub,
				'UID' => $fieldInterfaceStub,
				'csrf_token' => $fieldInterfaceStub,
				'token_test' => $clipboardTextFieldStub
			])
			->willReturn(['prepared' => true]);

		$result = $this->builder->buildForm($user, $this->sessionMock);

		static::assertSame(['prepared' => true], $result);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAddButtonsReturnsResetPasswordButton(): void
	{
		$this->expectUntouched($this->aclValidatorMock, $this->validatorMock, $this->parametersMock, $this->sessionMock);
		$resetButtonData = ['type' => 'button', 'label' => 'Reset Password'];

		$this->formElementsCreatorMock->expects($this->once())->method('addResetPasswordButton')
			->willReturn($resetButtonData);

		$result = $this->builder->addButtons();

		static::assertCount(1, $result);
		static::assertSame($resetButtonData, $result[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHandleUserInput(): void
	{
		$this->expectUntouched($this->formElementsCreatorMock, $this->aclValidatorMock, $this->sessionMock);
		$userInput = ['key1' => 'value1', 'key2' => 123, 'key3' => true];
		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with($userInput)
			->willReturnSelf();
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->validatorMock->expects($this->once())->method('validateUserInput')->with();

		$this->builder->handleUserInput($userInput);
	}

	private function setStandardMocks(): void
	{
		$user = ['UID' => 987, 'username' => 'username'];
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn($user);
		$this->builder->init($this->sessionMock);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
