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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Config\Config;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Account\Helper\Password\FormElementsCreator;
use App\Modules\Account\Helper\Password\FormInputHandler;
use App\Modules\Account\Helper\Password\Orchestrator;
use App\Modules\Account\Helper\Password\SettingsFormBuilder;
use App\Modules\Account\Services\AccountService;
use App\Modules\Auth\UserSession;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * Input handling, validation and form are the real ones (see PasswordFixture). The stubs are the borders:
 * the database (AccountService, UserTokenService) and the translations.
 * The whole chain with the database is in tests/Integration/Modules/Account.
 */
class OrchestratorTest extends TestCase
{
	private PasswordFixture $fixture;
	private AccountService&Stub $accountService;
	private UserTokenService&Stub $userTokenService;
	private Orchestrator $orchestrator;

	/** @var array{UID:int, company_id:int, username:string, status:int, expires_at:string, used_at:null, purpose:string} */
	private array $tokenUser = ['UID' => 9, 'company_id' => 1, 'username' => 'heidi', 'status' => 2, 'expires_at' => '2999-01-01 00:00:00', 'used_at' => null, 'purpose' => 'initial_password'];

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(PasswordFixture::translation());
		$translator->method('translateException')->willReturnCallback(
			static fn(TranslatableModuleException $e): string => $e->getModuleName() . '.' . $e->getTranslationKey()
		);
		$this->fixture = new PasswordFixture($translator);

		// a stub which answers every token with a valid user: only a token which is checked before gets no user
		$this->userTokenService = $this->createStub(UserTokenService::class);
		$this->userTokenService->method('findByToken')->willReturn($this->tokenUser);
		$this->accountService = $this->createStub(AccountService::class);
		$this->accountService->method('updatePassword')->willReturn(1);

		$this->fixture->session->set('user', ['UID' => 7, 'username' => 'regular', 'locale' => 'de_DE']);
		$config = new Config(new RecordingConfigLoader(['main' => ['password_pattern' => PasswordFixture::PATTERN]]));

		$this->orchestrator = new Orchestrator(
			new UserSession($this->fixture->session),
			$this->accountService,
			$this->userTokenService,
			new FormInputHandler($this->fixture->parameters, $this->fixture->validator),
			new SettingsFormBuilder(new FormElementsCreator($this->fixture->formBuilder, $translator)),
			$config,
			$translator
		);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function unusableLinkTokens(): array
	{
		return [
			'empty'         => [''],
			'not hex'       => ['xyz'],
			'odd length'    => ['abc'],
			'sql'           => ["ab' OR '1'='1"],
			'with spaces'   => ['ab cd'],
			'array syntax'  => ['ab[]'],
		];
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('unusableLinkTokens')]
	public function testATokenWhichIsNoHexStringIsRejected(string $token): void
	{
		static::assertSame([], $this->orchestrator->loadTokenUser($token));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAKnownTokenGivesTheUser(): void
	{
		static::assertSame($this->tokenUser, $this->orchestrator->loadTokenUser('abcd'));
		static::assertSame($this->tokenUser, $this->orchestrator->loadTokenUser('ABCD'));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAnUnknownUsedOrExpiredTokenIsRejected(): void
	{
		$service = $this->createStub(UserTokenService::class);
		$service->method('findByToken')->willReturn(null);

		static::assertSame([], $this->orchestratorWith($service)->loadTokenUser('abcd'));
	}

	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function purposeProvider(): array
	{
		return [
			'initial password' => [TokenPurposes::INITIAL_PASSWORD->value, true],
			'password reset'   => [TokenPurposes::PASSWORD_RESET->value, true],
			'autologin'        => [TokenPurposes::AUTOLOGIN->value, false],
			'verification'     => [TokenPurposes::EMAIL_VERIFICATION->value, false],
			'unknown'          => ['anything', false],
		];
	}

	/**
	 * A token from a cookie or a verification mail must not allow to set a password.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('purposeProvider')]
	public function testOnlyTheTokensOfAPasswordLinkAreAccepted(string $purpose, bool $accepted): void
	{
		$service = $this->createStub(UserTokenService::class);
		$service->method('findByToken')->willReturn(['purpose' => $purpose] + $this->tokenUser);

		$user = $this->orchestratorWith($service)->loadTokenUser('abcd');

		static::assertSame($accepted, $user !== []);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheFormGetsThePatternOfTheConfiguration(): void
	{
		$form = $this->orchestrator->buildForm();

		static::assertSame(PasswordFixture::PATTERN, PasswordFixture::element($form['visible'][0]['HTML_ELEMENT'])?->getAttribute('pattern'));
		static::assertCount(1, $form['hidden']);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheFormOfALinkCarriesTheToken(): void
	{
		$form = $this->orchestrator->buildForm('abcd');

		static::assertCount(2, $form['hidden']);
		static::assertSame('abcd', PasswordFixture::element($form['hidden'][1]['HIDDEN_HTML_ELEMENT'])?->getAttribute('value'));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStorePasswordSucceedsForValidInput(): void
	{
		static::assertSame(['success' => true], $this->orchestrator->storePassword($this->fixture->post()));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStorePasswordReturnsTheErrorsOfTheValidation(): void
	{
		$result = $this->orchestrator->storePassword($this->fixture->post(['password' => 'weak', 'password_confirm' => 'weak']));

		static::assertSame(['success' => false, 'errors' => ['account.password_explanation']], $result);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStorePasswordReportsAChangeWhichChangedNothing(): void
	{
		$accountService = $this->createStub(AccountService::class);
		$accountService->method('updatePassword')->willReturn(0);

		$result = $this->orchestratorWith($this->userTokenService, $accountService)->storePassword($this->fixture->post());

		static::assertSame(['success' => false, 'errors' => ['account.password_update_failed']], $result);
	}

	/**
	 * Whoever is not logged in can not change a password, the input does not matter.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStorePasswordNeedsALoggedInUser(): void
	{
		$this->fixture->session->clear();
		$this->expectException(UserException::class);

		$this->orchestrator->storePassword($this->fixture->post());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordSucceedsWithoutALoggedInUser(): void
	{
		$this->fixture->session->clear();
		$post = $this->fixture->post();

		static::assertSame(['success' => true], $this->orchestrator->storeForcedPassword(9, 'abcd', $post));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordReturnsTheErrorsOfTheValidation(): void
	{
		$result = $this->orchestrator->storeForcedPassword(9, 'abcd', $this->fixture->post(['password_confirm' => 'Other1234']));

		static::assertSame(['success' => false, 'errors' => ['account.no_passwords_match']], $result);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('unusableLinkTokens')]
	public function testStoreForcedPasswordRejectsATokenWhichIsNoHex(string $token): void
	{
		$result = $this->orchestrator->storeForcedPassword(9, $token, $this->fixture->post());

		static::assertSame(['success' => false, 'errors' => ['account.token_error']], $result);
	}

	/**
	 * The form is shown again with the error text instead of the standard error page.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordShowsAnErrorOfTheServiceInTheForm(): void
	{
		$accountService = $this->createStub(AccountService::class);
		$accountService->method('storeNewForcedPassword')->willThrowException(new TranslatableModuleException('account', 'token_update_failed'));

		$result = $this->orchestratorWith($this->userTokenService, $accountService)->storeForcedPassword(9, 'abcd', $this->fixture->post());

		static::assertSame(['success' => false, 'errors' => ['account.token_update_failed']], $result);
	}

	/**
	 * A user who is gone meanwhile is shown in the form as well, the NotFoundException is a translatable one.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testStoreForcedPasswordShowsAnErrorOfTheServiceAlsoForAMissingUser(): void
	{
		$accountService = $this->createStub(AccountService::class);
		$accountService->method('storeNewForcedPassword')->willThrowException(new NotFoundException('users', 'user_not_found'));

		$result = $this->orchestratorWith($this->userTokenService, $accountService)->storeForcedPassword(9, 'abcd', $this->fixture->post());

		static::assertSame(['success' => false, 'errors' => ['users.user_not_found']], $result);
	}

	/**
	 * A technical error is not for the user: it ends in the error page and in the log.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAnUnexpectedErrorOfTheServiceIsNotHidden(): void
	{
		$accountService = $this->createStub(AccountService::class);
		$accountService->method('storeNewForcedPassword')->willThrowException(new \RuntimeException('connection lost'));
		$this->expectException(\RuntimeException::class);

		$this->orchestratorWith($this->userTokenService, $accountService)->storeForcedPassword(9, 'abcd', $this->fixture->post());
	}

	private function orchestratorWith(UserTokenService $tokenService, ?AccountService $accountService = null): Orchestrator
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(PasswordFixture::translation());
		$translator->method('translateException')->willReturnCallback(
			static fn(TranslatableModuleException $e): string => $e->getModuleName() . '.' . $e->getTranslationKey()
		);

		return new Orchestrator(
			new UserSession($this->fixture->session),
			$accountService ?? $this->accountService,
			$tokenService,
			new FormInputHandler($this->fixture->parameters, $this->fixture->validator),
			new SettingsFormBuilder(new FormElementsCreator($this->fixture->formBuilder, $translator)),
			new Config(new RecordingConfigLoader(['main' => ['password_pattern' => PasswordFixture::PATTERN]])),
			$translator
		);
	}
}
