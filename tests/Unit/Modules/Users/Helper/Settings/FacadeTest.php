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
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Users\Helper\Settings\Builder;
use App\Modules\Users\Helper\Settings\Facade;
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Services\UsersAdminService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class FacadeTest extends TestCase
{
	private Builder&MockObject $settingsFormBuilderMock;
	private UsersAdminService&MockObject $usersAdminService;
	private Parameters&MockObject $settingsParameters;
	private Translator&MockObject $translatorMock;
	private Session&Stub $sessionStub;
	private Facade $facade;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->settingsFormBuilderMock = $this->createMock(Builder::class);
		$this->usersAdminService = $this->createMock(UsersAdminService::class);
		$this->settingsParameters  = $this->createMock(Parameters::class);
		$this->translatorMock      = $this->createMock(Translator::class);
		$this->sessionStub         = $this->createMock(Session::class);

		$this->facade = new Facade(
			$this->settingsFormBuilderMock,
			$this->usersAdminService,
			$this->settingsParameters,
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInit(): void
	{
		$sessionMock = $this->createMock(Session::class);

		$sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->settingsFormBuilderMock->expects($this->once())->method('init')
			->with($sessionMock);

		$this->usersAdminService->expects($this->once())->method('setUID')
			->with(123);

		$this->facade->init($this->translatorMock, $sessionMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadUserForEditReturnsUserArrayWhenUserExists(): void
	{
		$userID = 123;
		$userData = [
			'UID' => $userID,
			'company_id' => 1,
			'status' => 1,
			'locale' => 'en',
			'email' => 'user@example.com',
			'username' => 'testuser',
			'tokens' => [],
		];

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with($userID)
			->willReturn($userData);

		$result = $this->facade->loadUserForEdit($userID);

		self::assertSame($userData, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testLoadUserForEditReturnsEmptyArrayWhenUserDoesNotExist(): void
	{
		$userID = 456;

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with($userID)
			->willReturn([]);

		$result = $this->facade->loadUserForEdit($userID);

		self::assertSame([], $result);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigureUserFormParameterCallsConfigEditParameterWhenUserExists(): void
	{
		$post = ['UID' => 123, 'email' => 'user@example.com', 'username' => 'testuser'];
		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'status' => 1,
			'locale' => 'en',
			'email' => 'user@example.com',
			'username' => 'testuser',
			'tokens' => [],
		];

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with(123)
			->willReturn($userData);

		$this->settingsFormBuilderMock->expects($this->once())->method('configEditParameter')
			->with($userData);

		$this->settingsFormBuilderMock->expects($this->once())->method('handleUserInput')
			->with($post)
			->willReturn(['success']);

		$result = $this->facade->configureUserFormParameter($post);

		self::assertSame(['success'], $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureUserFormParameterReturnsErrorWhenUserNotFound(): void
	{
		$post = ['UID' => 456];

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with(456)
			->willReturn([]);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('user_not_found', 'users')
			->willReturn('User not found.');
		$sessionMock = $this->createMock(Session::class);

		$sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->facade->init($this->translatorMock, $sessionMock);
		$result = $this->facade->configureUserFormParameter($post);

		self::assertSame(['User not found.'], $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigureUserFormParameterCallsConfigNewParameterWhenNoUIDProvided(): void
	{
		$post = ['email' => 'user@example.com', 'username' => 'new user'];

		$this->settingsFormBuilderMock->expects($this->once())->method('configNewParameter');

		$this->settingsFormBuilderMock->expects($this->once())->method('handleUserInput')
			->with($post)
			->willReturn(['success']);

		$result = $this->facade->configureUserFormParameter($post);

		self::assertSame(['success'], $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreUserCallsUpdateForExistingUID(): void
	{
		$UID = 123;
		$parametersKeys = ['username', 'email'];
		$parametersValues = ['testuser', 'test@example.com'];

		$this->settingsParameters->expects($this->once())->method('getInputParametersKeys')
			->willReturn($parametersKeys);

		$this->settingsParameters->expects($this->once())->method('getInputValuesArray')
			->willReturn($parametersValues);

		$this->usersAdminService->expects($this->once())->method('updateUser')
			->with($UID, [
				'username' => 'testuser',
				'email' => 'test@example.com',
			]);

		$this->facade->storeUser($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreUserCallsInsertForNewUID(): void
	{
		$UID = 0;
		$parametersKeys = ['username', 'email'];
		$parametersValues = ['new user', 'new@example.com'];

		$this->settingsParameters->expects($this->once())->method('getInputParametersKeys')
			->willReturn($parametersKeys);

		$this->settingsParameters->expects($this->once())->method('getInputValuesArray')
			->willReturn($parametersValues);

		$this->usersAdminService->expects($this->once())->method('insertNewUser')
			->with([
				'username' => 'new user',
				'email' => 'new@example.com',
			]);
		$this->sessionStub->method('get')->willReturn(['UID' => 1]);
		$this->facade->init($this->translatorMock, $this->sessionStub);

		$this->facade->storeUser($UID);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreUserReturnsUpdateResultForExistingUID(): void
	{
		$UID = 123;
		$parametersKeys = ['username', 'email'];
		$parametersValues = ['testuser', 'test@example.com'];

		$this->settingsParameters->expects($this->once())->method('getInputParametersKeys')
			->willReturn($parametersKeys);

		$this->settingsParameters->expects($this->once())->method('getInputValuesArray')
			->willReturn($parametersValues);

		$this->usersAdminService->expects($this->once())->method('updateUser')
			->with($UID, [
				'username' => 'testuser',
				'email' => 'test@example.com',
			])
			->willReturn(123);

		$result = $this->facade->storeUser($UID);

		self::assertSame(123, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreUserReturnsInsertResultForNewUID(): void
	{
		$UID = 0;
		$parametersKeys = ['username', 'email'];
		$parametersValues = ['new user', 'new@example.com'];

		$this->settingsParameters->expects($this->once())->method('getInputParametersKeys')
			->willReturn($parametersKeys);

		$this->settingsParameters->expects($this->once())->method('getInputValuesArray')
			->willReturn($parametersValues);

		$this->usersAdminService->expects($this->once())->method('insertNewUser')
			->with([
				'username' => 'new user',
				'email' => 'new@example.com',
			])
			->willReturn(456);
		$this->sessionStub->method('get')->willReturn(['UID' => 1]);
		$this->facade->init($this->translatorMock, $this->sessionStub);

		$result = $this->facade->storeUser($UID);

		self::assertSame(456, $result);
	}


	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordResetTokenReturnsExpectedToken(): void
	{
		$UID = 123;
		$expectedToken = 'resetToken123';

		$this->usersAdminService->expects($this->once())->method('createPasswordResetToken')
			->with($UID)
			->willReturn($expectedToken);

		$this->sessionStub->method('get')->willReturn(['UID' => 1]);
		$this->facade->init($this->translatorMock, $this->sessionStub);

		$result = $this->facade->createPasswordResetToken($UID);

		self::assertSame($expectedToken, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrorsNoErrors(): void
	{
		$this->usersAdminService->expects($this->once())->method('getErrorMessages')
			->willReturn([]);

		$this->translatorMock->expects($this->never())->method('translate');

		$result = $this->facade->getUserServiceErrors();

		self::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrors(): void
	{
		$this->usersAdminService->expects($this->once())->method('getErrorMessages')
			->willReturn(['an_error']);

		$this->translatorMock->expects($this->once())->method('translate')
			->with('an_error', 'users')
 			->willReturn('An error');

		$sessionMock = $this->createMock(Session::class);

		$sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->settingsFormBuilderMock->expects($this->once())->method('init')
			->with($sessionMock);

		$this->facade->init($this->translatorMock, $sessionMock);
		$result = $this->facade->getUserServiceErrors();

		self::assertSame([0 =>'An error'], $result);
	}


	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testBuildCreateNewParameterCallsConfigNewParameter(): void
	{
		$this->settingsFormBuilderMock->expects($this->once())->method('configNewParameter');

		$this->facade->buildCreateNewParameter();
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildEditParameterCallsConfigEditParameter(): void
	{
		$userID = 123;
		$userData = [
			'UID' => $userID,
			'company_id' => 1,
			'status' => 1,
			'locale' => 'en',
			'email' => 'user@example.com',
			'username' => 'testuser',
			'tokens' => [],
		];

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with($userID)
			->willReturn($userData);

		$this->facade->loadUserForEdit($userID);

		$this->settingsFormBuilderMock->expects($this->once())->method('configEditParameter');

		$this->facade->buildEditParameter();
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareUITemplateWithOldUser(): void
	{
		$post = ['parameter' => 'value'];
		$expectedAdditionalButtons = [0 => 'button1'];

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['core_data', 'users', [], 'Core data'],
				['save', 'main', [], 'Save']
			]);

		$this->settingsFormBuilderMock->expects($this->once())->method('buildForm')
			->with($post)
			->willReturn(['field1' => 'value1']);

		$this->settingsFormBuilderMock->expects($this->once())->method('addButtons')
			->willReturn($expectedAdditionalButtons);

		$sessionMock = $this->createMock(Session::class);
		$sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn(['UID' => 123]);
		$this->facade->init($this->translatorMock, $sessionMock);

		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'status' => 1,
			'locale' => 'en',
			'email' => 'user@example.com',
			'username' => 'testuser',
			'tokens' => [],
		];

		$this->usersAdminService->expects($this->once())->method('loadForAdminEdit')
			->with(123)
			->willReturn($userData);

		$this->facade->loadUserForEdit(123);

		$result = $this->facade->prepareUITemplate($post);

		self::assertSame([
			'field1' => 'value1',
			'title' => 'Core data: testuser',
			'additional_css' => ['/css/users/edit.css'],
			'footer_modules' => ['/js/users/edit/init.js'],
			'template_name' => 'users/edit',
			'form_action' => '/users/edit',
			'save_button_label' => 'Save',
			'additional_buttons' => $expectedAdditionalButtons,
		], $result);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareUITemplateWithOutOldUser(): void
	{
		$post = ['parameter' => 'value'];

		$this->translatorMock->expects($this->exactly(3))->method('translate')
			->willReturnMap([
				['add', 'users', [], 'Add new user'],
				['core_data', 'users', [], 'Core data'],
				['save', 'main', [], 'Save']
			]);

		$this->settingsFormBuilderMock->expects($this->once())->method('buildForm')
			->with($post)
			->willReturn(['field1' => 'value1']);

		$this->settingsFormBuilderMock->expects($this->never())->method('addButtons');

		$sessionMock = $this->createMock(Session::class);
		$sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn(['UID' => 123]);
		$this->facade->init($this->translatorMock, $sessionMock);

		$result = $this->facade->prepareUITemplate($post);

		self::assertSame([
			'field1' => 'value1',
			'title' => 'Core data: Add new user',
			'additional_css' => ['/css/users/edit.css'],
			'footer_modules' => ['/js/users/edit/init.js'],
			'template_name' => 'users/edit',
			'form_action' => '/users/edit',
			'save_button_label' => 'Save'
		], $result);
	}

}
