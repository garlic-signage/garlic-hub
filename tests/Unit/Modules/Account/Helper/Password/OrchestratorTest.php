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

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Shared\User\TokenPurposes;
use App\Modules\Account\Helper\Password\FormInputHandler;
use App\Modules\Account\Helper\Password\Orchestrator;
use App\Modules\Account\Helper\Password\SettingsFormBuilder;
use App\Modules\Account\Services\AccountService;
use App\Shared\User\UserTokenService;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private UserSession&MockObject $userSessionMock;
	private AccountService&MockObject $accountServiceMock;
	private UserTokenService&MockObject $userTokenServiceMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private Config&MockObject $configMock;
	private Translator&MockObject $translatorMock;
	private Orchestrator $orchestrator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->userSessionMock      = $this->createMock(UserSession::class);
		$this->accountServiceMock   = $this->createMock(AccountService::class);
		$this->userTokenServiceMock = $this->createMock(UserTokenService::class);
		$this->inputHandlerMock     = $this->createMock(FormInputHandler::class);
		$this->formBuilderMock      = $this->createMock(SettingsFormBuilder::class);
		$this->configMock           = $this->createMock(Config::class);
		$this->translatorMock       = $this->createMock(Translator::class);

		$this->orchestrator = new Orchestrator(
			$this->userSessionMock,
			$this->accountServiceMock,
			$this->userTokenServiceMock,
			$this->inputHandlerMock,
			$this->formBuilderMock,
			$this->configMock,
			$this->translatorMock
		);
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadTokenUserWithInvalidTokenTouchesNothing(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->userTokenServiceMock,
			$this->inputHandlerMock, $this->formBuilderMock, $this->configMock, $this->translatorMock);

		static::assertSame([], $this->orchestrator->loadTokenUser('xyz'));
		static::assertSame([], $this->orchestrator->loadTokenUser('abc'));
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadTokenUserNotFound(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->inputHandlerMock,
			$this->formBuilderMock, $this->configMock, $this->translatorMock);
		$this->userTokenServiceMock->expects($this->once())->method('findByToken')
			->with(hex2bin('abcd'))->willReturn(null);

		static::assertSame([], $this->orchestrator->loadTokenUser('abcd'));
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadTokenUserWithWrongPurpose(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->inputHandlerMock,
			$this->formBuilderMock, $this->configMock, $this->translatorMock);
		$this->userTokenServiceMock->expects($this->once())->method('findByToken')
			->willReturn($this->tokenUser(TokenPurposes::AUTOLOGIN->value));

		static::assertSame([], $this->orchestrator->loadTokenUser('abcd'));
	}

	/**
	 * @throws DateMalformedStringException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadTokenUser(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->inputHandlerMock,
			$this->formBuilderMock, $this->configMock, $this->translatorMock);
		$user = $this->tokenUser(TokenPurposes::PASSWORD_RESET->value);
		$this->userTokenServiceMock->expects($this->once())->method('findByToken')->willReturn($user);

		static::assertSame($user, $this->orchestrator->loadTokenUser('abcd'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildForm(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->inputHandlerMock,
			$this->userTokenServiceMock, $this->translatorMock);
		$form = ['hidden' => [], 'visible' => []];
		$this->expectPasswordPattern();
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with('pattern', 'abcd')->willReturn($form);

		static::assertSame($form, $this->orchestrator->buildForm('abcd'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStorePasswordReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->formBuilderMock,
			$this->userTokenServiceMock, $this->translatorMock);
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')
			->with(['password' => ''], 'pattern')->willReturn(['No password.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');

		static::assertSame(
			['success' => false, 'errors' => ['No password.']],
			$this->orchestrator->storePassword(['password' => ''])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStorePasswordReturnsTranslatedServiceErrors(): void
	{
		$this->expectValidInput();
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->accountServiceMock->expects($this->once())->method('updatePassword')->with(7, 'secret')->willReturn(0);
		$this->translatorMock->expects($this->once())->method('translate')
			->with('password_update_failed', 'account')->willReturn('Something failed.');

		static::assertSame(
			['success' => false, 'errors' => ['Something failed.']],
			$this->orchestrator->storePassword(['password' => 'secret'])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStorePassword(): void
	{
		$this->expectValidInput();
		$this->userSessionMock->expects($this->once())->method('getUID')->willReturn(7);
		$this->accountServiceMock->expects($this->once())->method('updatePassword')->with(7, 'secret')->willReturn(1);
		$this->translatorMock->expects($this->never())->method('translate');

		static::assertSame(['success' => true], $this->orchestrator->storePassword(['password' => 'secret']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->userSessionMock, $this->accountServiceMock, $this->formBuilderMock,
			$this->userTokenServiceMock, $this->translatorMock);
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn(['No password.']);

		static::assertSame(
			['success' => false, 'errors' => ['No password.']],
			$this->orchestrator->storeForcedPassword(9, 'abcd', [])
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordReturnsTranslatedServiceErrors(): void
	{
		$this->expectValidInput();
		$this->userSessionMock->expects($this->never())->method($this->anything());
		$exception = new TranslatableModuleException('account', 'token_update_failed');
		$this->accountServiceMock->expects($this->once())->method('storeNewForcedPassword')
			->with(9, hex2bin('abcd'), 'secret')->willThrowException($exception);
		$this->translatorMock->expects($this->once())->method('translateException')
			->with($exception)->willReturn('Something failed.');

		static::assertSame(
			['success' => false, 'errors' => ['Something failed.']],
			$this->orchestrator->storeForcedPassword(9, 'abcd', ['password' => 'secret'])
		);
	}

	/**
	 * The forced password change works without a logged-in user.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreForcedPasswordWithoutUserSession(): void
	{
		$this->expectValidInput();
		$this->userSessionMock->expects($this->never())->method($this->anything());
		$this->accountServiceMock->expects($this->once())->method('storeNewForcedPassword')
			->with(9, hex2bin('abcd'), 'secret');
		$this->translatorMock->expects($this->never())->method('translate');

		static::assertSame(['success' => true], $this->orchestrator->storeForcedPassword(9, 'abcd', ['password' => 'secret']));
	}

	/**
	 * @return array{UID:int, company_id:int, username:string, status:int, expires_at:string, used_at:null, purpose:string}
	 */
	private function tokenUser(string $purpose): array
	{
		return ['UID' => 9, 'company_id' => 1, 'username' => 'heidi', 'status' => 1,
			'expires_at' => '2026-10-21 00:00:00', 'used_at' => null, 'purpose' => $purpose];
	}

	private function expectPasswordPattern(): void
	{
		$this->configMock->expects($this->once())->method('getConfigValue')
			->with('password_pattern', 'main')->willReturn('pattern');
	}

	private function expectValidInput(): void
	{
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->userTokenServiceMock->expects($this->never())->method($this->anything());
		$this->expectPasswordPattern();
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')
			->willReturn(['password' => 'secret', 'password_confirm' => 'secret']);
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
