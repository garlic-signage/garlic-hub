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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Profile\Entities\TokenPurposes;
use App\Modules\Profile\Helper\Password\Builder;
use App\Modules\Profile\Helper\Password\Facade;
use App\Modules\Profile\Helper\Password\Parameters;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Profile\Services\UserTokenService;
use DateMalformedStringException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

class FacadeTest extends TestCase
{
	private Builder&MockObject $builderMock;
	private ProfileService&MockObject $profileServiceMock;
	private UserTokenService&MockObject $usersTokenServiceMock;
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
		$this->builderMock            = $this->createMock(Builder::class);
		$this->profileServiceMock     = $this->createMock(ProfileService::class);
		$this->usersTokenServiceMock  = $this->createMock(UserTokenService::class);
		$this->settingsParametersMock = $this->createMock(Parameters::class);
		$this->translatorMock         = $this->createMock(Translator::class);
		$this->configMock             = $this->createMock(Config::class);

		$this->facade = new Facade(
			$this->builderMock,
			$this->profileServiceMock,
			$this->usersTokenServiceMock,
			$this->translatorMock,
			$this->settingsParametersMock,
			$this->configMock
		);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception|RandomException
	 */
	#[Group('units')]
	public function testDetermineUIDByTokenReturnsZero(): void
	{
		$this->expectUntouched($this->builderMock, $this->profileServiceMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$passwordToken = 'zz11';
		$this->usersTokenServiceMock->expects($this->never())
			->method('findByToken');

		$result = $this->facade->determineUIDByToken($passwordToken);

		self::assertSame(0, $result);
	}


	/**
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception|RandomException
	 */
	#[Group('units')]
	public function testDetermineUIDByTokenReturnsZeroIfTokenNotFound(): void
	{
		$this->expectUntouched($this->builderMock, $this->profileServiceMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$passwordToken = bin2hex(random_bytes(32));
		$this->usersTokenServiceMock->expects($this->once())
			->method('findByToken')
			->with(hex2bin($passwordToken))
			->willReturn(null);

		$result = $this->facade->determineUIDByToken($passwordToken);

		self::assertSame(0, $result);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testDetermineUIDByTokenReturnsUIDForValidToken(): void
	{
		$this->expectUntouched($this->builderMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$passwordToken = bin2hex(random_bytes(32));
		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'username' => 'user123',
			'status' => 1,
			'purpose' => TokenPurposes::PASSWORD_RESET->value,
		];

		$this->usersTokenServiceMock->expects($this->once())->method('findByToken')
			->with(hex2bin($passwordToken))
			->willReturn($userData);

		$this->profileServiceMock->expects($this->once())->method('setUID')->with($userData['UID']);

		$result = $this->facade->determineUIDByToken($passwordToken);

		self::assertSame($userData['UID'], $result);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testDetermineUIDByTokenInValidToken(): void
	{
		$this->expectUntouched($this->builderMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$passwordToken = bin2hex(random_bytes(32));
		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'username' => 'user123',
			'status' => 1,
			'purpose' => 'something unknown',
		];

		$this->usersTokenServiceMock->expects($this->once())->method('findByToken')
			->with(hex2bin($passwordToken))
			->willReturn($userData);

		$this->profileServiceMock->expects($this->never())->method('setUID');

		$result = $this->facade->determineUIDByToken($passwordToken);

		self::assertSame(0, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInitWithSessionContainingValidUser(): void
	{
		$this->expectUntouched($this->builderMock, $this->usersTokenServiceMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$sessionMock = $this->createMock(Session::class);
		$sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->profileServiceMock->expects($this->once())->method('setUID')
			->with(123);

		$this->facade->init($sessionMock);
	}

	/**
	 * @throws \Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInitSetsProfileServiceUID(): void
	{
		$this->expectUntouched($this->builderMock, $this->usersTokenServiceMock, $this->settingsParametersMock, $this->translatorMock, $this->configMock);
		$sessionMock = $this->createMock(Session::class);
		$sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(['UID' => 456]);

		$this->profileServiceMock->expects($this->once())->method('setUID')
			->with(456);

		$this->facade->init($sessionMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigureUserFormParameterValidInput(): void
	{
		$this->expectUntouched($this->profileServiceMock, $this->usersTokenServiceMock, $this->settingsParametersMock, $this->translatorMock);
		$postData = ['username' => 'testuser', 'password' => 'Strong@123'];
		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';

		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$this->builderMock->expects($this->once())->method('handleUserInput')
			->with($postData, $passwordPattern)
			->willReturn(['status' => 'success', 'data' => $postData]);

		$result = $this->facade->configureUserFormParameter($postData);

		self::assertSame(['status' => 'success', 'data' => $postData], $result);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStorePassword(): void
	{
		$this->expectUntouched($this->builderMock, $this->usersTokenServiceMock, $this->translatorMock, $this->configMock);
		$password = 'SecurePass123!';

		$this->settingsParametersMock->expects($this->once())->method('getValueOfParameter')
			->with(Parameters::PARAMETER_PASSWORD)
			->willReturn($password);

		$sessionMock = $this->createMock(Session::class);
		$sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(['UID' => 123]);
		$this->profileServiceMock->expects($this->once())->method('setUID')
			->with(123);
		$this->facade->init($sessionMock);

		$this->profileServiceMock->expects($this->once())->method('updatePassword')
			->with(123, $password)
			->willReturn(1);

		$result = $this->facade->storePassword();

		self::assertSame(1, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrorsTranslatesErrorsCorrectly(): void
	{
		$this->expectUntouched($this->builderMock, $this->usersTokenServiceMock, $this->settingsParametersMock, $this->configMock);
		$errorMessages = ['error.password.invalid', 'error.password.mismatch'];
		$translatedMessages = ['Invalid password', 'Password mismatch'];

		$this->profileServiceMock->expects($this->once())->method('getErrorMessages')->willReturn($errorMessages);

		$this->translatorMock->expects($this->exactly(2))->method('translate')->willReturnMap([
			['error.password.invalid', 'profile', [], 'Invalid password'],
			['error.password.mismatch', 'profile', [], 'Password mismatch'],
		]);

		$result = $this->facade->getUserServiceErrors();

		static::assertSame($translatedMessages, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testPrepareUITemplateStandard(): void
	{
		$this->expectUntouched($this->profileServiceMock, $this->usersTokenServiceMock, $this->settingsParametersMock);
		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';
		$this->configMock->expects($this->once())
			->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$formData = [
			'inputs' => [
				'username' => [],
				'email' => [],
			],
		];

		$this->builderMock->expects($this->once())
			->method('buildForm')
			->with($passwordPattern, '')
			->willReturn($formData);

		$this->translatorMock->expects($this->exactly(3))
			->method('translate')
			->willReturnMap([
				['edit_password', 'profile', [], 'Edit Password'],
				['password_explanation', 'profile', [], 'Explain.'],
				['save', 'main', [], 'Save']
			]);

		$result = $this->facade->prepareUITemplate('');

		$expectedResult = array_merge($formData, [
			'title' => 'Edit Password',
			'explanations' => 'Explain.',
			'additional_css' => ['/css/profile/password.css'],
			'footer_modules' => ['/js/profile/password/init.js'],
			'template_name' => 'profile/edit',
			'form_action' => '/profile/password',
			'save_button_label' => 'Save'
		]);

		self::assertSame($expectedResult, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareUITemplateWithToken(): void
	{
		$passwordToken = bin2hex(random_bytes(32));
		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'username' => 'user123',
			'status' => 1,
			'purpose' => TokenPurposes::PASSWORD_RESET->value,
		];

		$this->usersTokenServiceMock->expects($this->once())->method('findByToken')
			->willReturn($userData);

		$this->profileServiceMock->expects($this->once())->method('setUID')->with($userData['UID']);
		$this->facade->determineUIDByToken($passwordToken);

		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';
		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$formData = [
			'inputs' => [
				'username' => [],
				'email' => [],
			],
		];

		$this->builderMock->expects($this->once())->method('buildForm')
			->with($passwordPattern, $passwordToken)
			->willReturn($formData);

		$this->translatorMock->expects($this->exactly(3))->method('translate')
			->willReturnMap([
				['edit_password_for', 'profile', [], 'Edit Password for %s'],
				['password_explanation', 'profile', [], 'Explain.'],
				['save', 'main', [], 'Save']
			]);

		// a token form needs the token as hidden parameter
		$this->settingsParametersMock->expects($this->once())->method('addToken');

		$result = $this->facade->prepareUITemplate($passwordToken);

		$expectedResult = array_merge($formData, [
			'title' => 'Edit Password for user123',
			'explanations' => 'Explain.',
			'additional_css' => ['/css/profile/password.css'],
			'footer_modules' => ['/js/profile/password/init.js'],
			'template_name' => 'profile/edit',
			'form_action' => '/force-password',
			'save_button_label' => 'Save'
		]);

		self::assertSame($expectedResult, $result);
	}


	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws RandomException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareUITemplateWithInitialToken(): void
	{
		$passwordToken = bin2hex(random_bytes(32));
		$userData = [
			'UID' => 123,
			'company_id' => 1,
			'username' => 'user123',
			'status' => 1,
			'purpose' => TokenPurposes::INITIAL_PASSWORD->value,
		];

		$this->usersTokenServiceMock->expects($this->once())->method('findByToken')
			->willReturn($userData);

		$this->profileServiceMock->expects($this->once())->method('setUID')->with($userData['UID']);
		$this->facade->determineUIDByToken($passwordToken);

		$passwordPattern = '^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$.!%*#?&]{8,}$';
		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')
			->willReturn($passwordPattern);

		$formData = [
			'inputs' => [
				'username' => [],
				'email' => [],
			],
		];

		$this->builderMock->expects($this->once())->method('buildForm')
			->with($passwordPattern, $passwordToken)
			->willReturn($formData);

		$this->translatorMock->expects($this->exactly(3))->method('translate')
			->willReturnMap([
				['initial_password_for', 'profile', [], 'Initial Password for %s'],
				['password_explanation', 'profile', [], 'Explain.'],
				['save', 'main', [], 'Save']
			]);

		// a token form needs the token as hidden parameter
		$this->settingsParametersMock->expects($this->once())->method('addToken');

		$result = $this->facade->prepareUITemplate($passwordToken);

		$expectedResult = array_merge($formData, [
			'title' => 'Initial Password for user123',
			'explanations' => 'Explain.',
			'additional_css' => ['/css/profile/password.css'],
			'footer_modules' => ['/js/profile/password/init.js'],
			'template_name' => 'profile/edit',
			'form_action' => '/force-password',
			'save_button_label' => 'Save'
		]);

		self::assertSame($expectedResult, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws RandomException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordThrowsException(): void
	{
		$this->expectUntouched($this->builderMock, $this->usersTokenServiceMock, $this->translatorMock, $this->configMock);
		$UID = 1234;
		$passwordToken = bin2hex(random_bytes(32));

		$this->settingsParametersMock->expects($this->once())->method('getValueOfParameter')
			->with(Parameters::PARAMETER_PASSWORD)
			->willReturn('valid_password');

		$this->profileServiceMock->expects($this->once())
			->method('storeNewForcedPassword')
			->with($UID, hex2bin($passwordToken), 'valid_password')
			->willReturn(1)
		;

		$this->facade->storeForcedPassword($UID, $passwordToken);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
