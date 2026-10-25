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

namespace Tests\Unit\Modules\Users\Helper\Settings;

use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Users\Helper\Settings\FormInputHandler;
use App\Modules\Users\Helper\Settings\Orchestrator;
use App\Modules\Users\Helper\Settings\SettingsFormBuilder;
use App\Modules\Users\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Users\Services\UsersAdminService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private Session&MockObject $sessionMock;
	private UsersAdminService&MockObject $serviceMock;
	private SettingsParametersPolicy&MockObject $policyMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private Translator&MockObject $translatorMock;
	private Orchestrator $orchestrator;

	/** @var array{UID: int, company_id: int, status: int, locale: string, email:string, username:string, tokens: array{}} */
	private array $user = ['UID' => 12, 'company_id' => 1, 'status' => 1, 'locale' => 'de_DE',
		'email' => 'heidi@example.com', 'username' => 'heidi', 'tokens' => []];

	/** @var array{hidden: list<mixed>, visible: list<mixed>} */
	private array $form = ['hidden' => [], 'visible' => []];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionMock      = $this->createMock(Session::class);
		$this->serviceMock      = $this->createMock(UsersAdminService::class);
		$this->policyMock       = $this->createMock(SettingsParametersPolicy::class);
		$this->inputHandlerMock = $this->createMock(FormInputHandler::class);
		$this->formBuilderMock  = $this->createMock(SettingsFormBuilder::class);
		$this->translatorMock   = $this->createMock(Translator::class);

		$this->orchestrator = new Orchestrator(
			$this->sessionMock,
			$this->serviceMock,
			$this->policyMock,
			$this->inputHandlerMock,
			$this->formBuilderMock,
			$this->translatorMock
		);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadUserForEdit(): void
	{
		$this->expectUntouched($this->sessionMock, $this->policyMock, $this->inputHandlerMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();

		static::assertSame($this->user, $this->orchestrator->loadUserForEdit(12));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildCreateForm(): void
	{
		$this->expectUntouched($this->sessionMock, $this->serviceMock, $this->inputHandlerMock, $this->translatorMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with(['username' => 'heidi'])->willReturn($this->form);
		$this->formBuilderMock->expects($this->never())->method('buildButtons');

		static::assertSame($this->form, $this->orchestrator->buildCreateForm(['username' => 'heidi']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildEditFormWithoutLoadedUserThrows(): void
	{
		$this->expectUntouched($this->sessionMock, $this->serviceMock, $this->policyMock,
			$this->inputHandlerMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectException(ModuleException::class);

		$this->orchestrator->buildEditForm();
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testBuildEditFormWithLoadedUser(): void
	{
		$this->expectUntouched($this->sessionMock, $this->inputHandlerMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements')->with($this->user);
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with($this->user)->willReturn($this->form);
		$this->formBuilderMock->expects($this->once())->method('buildButtons')->willReturn([['button']]);

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(
			$this->form + ['additional_buttons' => [['button']]],
			$this->orchestrator->buildEditForm()
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
	public function testBuildEditFormPrefersUserInput(): void
	{
		$this->expectUntouched($this->sessionMock, $this->inputHandlerMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements')->with($this->user);
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with(['UID' => 12, 'username' => ''])->willReturn($this->form);
		$this->formBuilderMock->expects($this->once())->method('buildButtons')->willReturn([]);

		$this->orchestrator->loadUserForEdit(12);
		$this->orchestrator->buildEditForm(['UID' => 12, 'username' => '']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->sessionMock, $this->serviceMock, $this->formBuilderMock, $this->translatorMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validate')->with(['username' => ''])->willReturn(['No username.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');

		static::assertSame(
			['success' => false, 'errors' => ['No username.']],
			$this->orchestrator->storeCreateSettings(['username' => ''])
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
	public function testStoreCreateSettingsReturnsTranslatedServiceErrors(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->expectValidInput();
		$this->serviceMock->expects($this->once())->method('insertNewUser')->with(['username' => 'heidi', 'status' => 2])
			->willThrowException($this->expectTranslatedServiceError('username_exists'));

		static::assertSame(
			['success' => false, 'errors' => ['Failed.']],
			$this->orchestrator->storeCreateSettings(['username' => 'heidi'])
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
	public function testStoreCreateSettingsStoresInitialTokenInSession(): void
	{
		$this->expectUntouched($this->formBuilderMock, $this->translatorMock);
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->expectValidInput();
		$this->serviceMock->expects($this->once())->method('insertNewUser')->willReturn(13);
		$this->serviceMock->expects($this->once())->method('getToken')->willReturn(hex2bin('beef'));
		$this->sessionMock->expects($this->once())->method('set')->with('initial_password_token', 'beef');

		static::assertSame(['success' => true], $this->orchestrator->storeCreateSettings(['username' => 'heidi']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsWithoutLoadedUserThrows(): void
	{
		$this->expectUntouched($this->sessionMock, $this->serviceMock, $this->policyMock,
			$this->inputHandlerMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectException(ModuleException::class);

		$this->orchestrator->storeEditSettings(12, []);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements')->with($this->user);
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn(['No email.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');
		$this->serviceMock->expects($this->never())->method('updateUser');

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(
			['success' => false, 'errors' => ['No email.']],
			$this->orchestrator->storeEditSettings(12, ['email' => ''])
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
	public function testStoreEditSettingsReturnsTranslatedServiceError(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->expectValidInput();
		$this->serviceMock->expects($this->once())->method('updateUser')->with(12, ['username' => 'heidi'])
			->willThrowException($this->expectTranslatedServiceError('email_exists'));

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(
			['success' => false, 'errors' => ['Failed.']],
			$this->orchestrator->storeEditSettings(12, ['username' => 'heidi'])
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
	public function testStoreEditSettings(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->expectValidInput();
		$this->serviceMock->expects($this->once())->method('updateUser')->willReturn(1);

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(['success' => true], $this->orchestrator->storeEditSettings(12, ['username' => 'heidi']));
	}

	/**
	 * A save without changes affects no rows and must count as success.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testStoreEditSettingsSucceedsWithoutAffectedRows(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->expectValidInput();
		$this->serviceMock->expects($this->once())->method('updateUser')->willReturn(0);

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(['success' => true], $this->orchestrator->storeEditSettings(12, ['username' => 'heidi']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testResetPasswordReturnsValidationErrors(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn(['CSRF token mismatch.']);
		$this->serviceMock->expects($this->never())->method('createPasswordResetToken');

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(
			['success' => false, 'errors' => ['CSRF token mismatch.']],
			$this->orchestrator->resetPassword(12, [])
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
	public function testResetPasswordReturnsTranslatedServiceErrors(): void
	{
		$this->expectUntouched($this->sessionMock, $this->formBuilderMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->serviceMock->expects($this->once())->method('createPasswordResetToken')->with(12)
			->willThrowException($this->expectTranslatedServiceError('tokens_exists'));

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(
			['success' => false, 'errors' => ['Failed.']],
			$this->orchestrator->resetPassword(12, [])
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
	public function testResetPasswordStoresTokenInSession(): void
	{
		$this->expectUntouched($this->formBuilderMock, $this->translatorMock);
		$this->expectUserLoaded();
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->serviceMock->expects($this->once())->method('createPasswordResetToken')->with(12)->willReturn(hex2bin('beef'));
		$this->sessionMock->expects($this->once())->method('set')->with('password_reset_token', 'beef');

		$this->orchestrator->loadUserForEdit(12);
		static::assertSame(['success' => true], $this->orchestrator->resetPassword(12, []));
	}

	private function expectUserLoaded(): void
	{
		$this->serviceMock->expects($this->once())->method('loadForAdminEdit')->with(12)->willReturn($this->user);
	}

	private function expectValidInput(): void
	{
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['username' => 'heidi']);
	}

	private function expectTranslatedServiceError(string $key): TranslatableModuleException
	{
		$exception = new TranslatableModuleException('users', $key);
		$this->translatorMock->expects($this->once())->method('translateException')->with($exception)->willReturn('Failed.');

		return $exception;
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
