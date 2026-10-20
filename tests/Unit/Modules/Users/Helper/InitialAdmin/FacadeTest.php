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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Helper\InitialAdmin\Builder;
use App\Modules\Users\Helper\InitialAdmin\Facade;
use App\Modules\Users\Helper\InitialAdmin\Parameters;
use App\Modules\Users\Services\UsersAdminCreateService;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FacadeTest extends TestCase
{
	use PHPMock;
	private Builder&MockObject $settingsFormBuilderMock;
	private UsersAdminCreateService&MockObject $usersAdminCreateServiceMock;
	private Parameters&MockObject $settingsParametersMock;
	private Translator&MockObject $translatorMock;
	private Config&MockObject $configMock;
	private Facade $facade;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->settingsFormBuilderMock = $this->createMock(Builder::class);
		$this->usersAdminCreateServiceMock = $this->createMock(UsersAdminCreateService::class);
		$this->settingsParametersMock      = $this->createMock(Parameters::class);
		$this->configMock              = $this->createMock(Config::class);
		$this->translatorMock          = $this->createMock(Translator::class);

		$this->facade = new Facade(
			$this->settingsFormBuilderMock,
			$this->usersAdminCreateServiceMock,
			$this->configMock,
			$this->settingsParametersMock,
		);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testIsFunctionAllowedWithExistingLockFile(): void
	{
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(true);

		self::assertFalse($this->facade->isFunctionAllowed());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testIsFunctionAllowedWithAdminUser(): void
	{
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(false);

		$this->usersAdminCreateServiceMock->expects(self::once())->method('hasAdminUser')->willReturn(true);
		$this->usersAdminCreateServiceMock->expects(self::once())->method('creatLockfile');
		$this->usersAdminCreateServiceMock->expects(self::once())->method('logAlarm');

		self::assertFalse($this->facade->isFunctionAllowed());
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsFunctionAllowedWithoutAdminUserAndLockFile(): void
	{
		define('INSTALL_LOCK_FILE', 'INSTALL_LOCK_FILE');
		$fileExistsMock = $this->getFunctionMock('App\Modules\Users\Helper\InitialAdmin', 'file_exists');
		$fileExistsMock->expects($this->once())->with('INSTALL_LOCK_FILE')->willReturn(false);

		$this->usersAdminCreateServiceMock->expects(self::once())->method('hasAdminUser')->willReturn(false);

		self::assertTrue($this->facade->isFunctionAllowed());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigureUserFormParameterValidInput(): void
	{
		$postData = ['username' => 'testuser', 'password' => 'Test@1234'];
		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';

		$this->configMock->expects(self::once())
			->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$this->settingsFormBuilderMock->expects(self::once())
			->method('handleUserInput')
			->with($postData, $passwordPattern)
			->willReturn(['processedData' => 'some data']);

		$result = $this->facade->configureUserFormParameter($postData);

		self::assertSame(['processedData' => 'some data'], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreUserValidData(): void
	{
		$expectedKeys = ['username', 'email', 'locale', 'password'];
		$expectedValues = ['testuser', 'test@example.com', 'en', 'Test@1234'];
		$saveData = array_combine($expectedKeys, $expectedValues);

		$this->settingsParametersMock->expects(self::once())
			->method('getInputParametersKeys')
			->willReturn($expectedKeys);

		$this->settingsParametersMock->expects(self::once())
			->method('getInputValuesArray')
			->willReturn($expectedValues);

		$this->usersAdminCreateServiceMock->expects(self::once())
			->method('insertNewAdminUser')
			->with($saveData)
			->willReturn(1);

		$result = $this->facade->storeUser();

		self::assertSame(1, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrorsReturnsTranslatedErrors(): void
	{
		$errorMessages = ['error.user.not_found', 'error.user.invalid_data'];
		$translatedMessages = ['User not found', 'Invalid data'];
		$this->usersAdminCreateServiceMock->expects(self::once())->method('getErrorMessages')
			->willReturn($errorMessages);

		$this->translatorMock->expects(self::exactly(2))->method('translate')
			->willReturnMap([
				['error.user.not_found', 'users', [], 'User not found'],
				['error.user.invalid_data', 'users', [], 'Invalid data']
			]);

		$this->facade->init($this->translatorMock);

		$result = $this->facade->getUserServiceErrors();

		self::assertSame($translatedMessages, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareUITemplateValidInput(): void
	{
		$postData = [
			'username' => 'admin',
			'email' => 'admin@example.com',
			'locale' => 'en',
			'password' => 'Test@1234',
			'password_confirm' => 'Test@1234',
		];
		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';
		$formData = [
			'inputs' => [
				'username' => [],
				'email' => [],
			]
		];

		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['create_admin', 'users', [], 'Create Admin User'],
				['save', 'main', [], 'Save']
			]);

		$this->settingsFormBuilderMock->expects($this->once())->method('buildForm')
			->with($postData, $passwordPattern)
			->willReturn($formData);

		$this->facade->init($this->translatorMock);
		$result = $this->facade->prepareUITemplate($postData);

		$expectedResult = array_merge($formData, [
			'title' => 'Create Admin User',
			'additional_css' => ['/css/users/edit.css', '/css/profile/password.css'],
			'footer_modules' => ['/js/profile/password/init.js'],
			'template_name' => 'users/edit',
			'form_action' => '/create-initial',
			'save_button_label' => 'Save',
		]);

		self::assertSame($expectedResult, $result);
	}
}
