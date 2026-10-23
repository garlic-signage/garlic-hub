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

namespace Tests\Integration\Modules\Account\Services;

use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Shared\User\UsersService;
use Phpfastcache\Helper\Psr16Adapter;
use App\Modules\Account\Helper\Password\Orchestrator;
use App\Modules\Account\Services\AccountService;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Change of the own password and the password change by a link token (initial password or reset).
 * Users of the fixture: 10 regular, 13 not verified, 14 other. All have the password Test1234!
 */
#[Group('auth')]
class PasswordTest extends ContainerTestCase
{
	private AccountService $accountService;
	private UserTokenService $tokenService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->accountService = $this->container->get(AccountService::class);
		$this->tokenService   = $this->container->get(UserTokenService::class);
	}

	public function testUpdatePasswordStoresAHashOfTheNewPassword(): void
	{
		static::assertSame(1, $this->accountService->updatePassword(10, 'New-Pass1!'));

		$stored = $this->passwordOf(10);
		static::assertNotSame('New-Pass1!', $stored);
		static::assertTrue(password_verify('New-Pass1!', $stored));
		static::assertFalse(password_verify('Test1234!', $stored));
	}

	public function testUpdatePasswordChangesOnlyTheGivenUser(): void
	{
		$before = $this->passwordOf(14);

		$this->accountService->updatePassword(10, 'New-Pass1!');

		static::assertSame($before, $this->passwordOf(14));
	}

	public function testUpdatePasswordOfAnUnknownUserChangesNothing(): void
	{
		static::assertSame(0, $this->accountService->updatePassword(999, 'New-Pass1!'));
	}

	public function testUpdateLocaleChangesOnlyTheGivenUser(): void
	{
		static::assertSame(1, $this->accountService->updateLocale(10, 'en_US'));

		static::assertSame('en_US', $this->connection->fetchOne('SELECT locale FROM user_main WHERE UID = 10'));
		// user 14 had en_US before, so change user 10 back and look at user 14 with a different value
		$this->accountService->updateLocale(14, 'fr_FR');
		$this->accountService->updateLocale(10, 'de_DE');
		static::assertSame('fr_FR', $this->connection->fetchOne('SELECT locale FROM user_main WHERE UID = 14'));
	}

	public function testForcedPasswordSetsThePasswordUsesTheTokenAndVerifiesTheUser(): void
	{
		$token = $this->createToken(13, TokenPurposes::INITIAL_PASSWORD);

		$this->accountService->storeNewForcedPassword(13, $token, 'New-Pass1!');

		static::assertTrue(password_verify('New-Pass1!', $this->passwordOf(13)));
		static::assertNull($this->tokenService->findByToken($token));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 13'));
	}

	public function testForcedPasswordDoesNotChangeTheStatusOfAVerifiedUser(): void
	{
		$token = $this->createToken(10, TokenPurposes::PASSWORD_RESET);
		$this->connection->executeStatement('UPDATE user_main SET status = 4 WHERE UID = 10');

		$this->accountService->storeNewForcedPassword(10, $token, 'New-Pass1!');

		static::assertSame(4, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 10'));
	}

	public function testForcedPasswordWithAnUnknownTokenChangesNothing(): void
	{
		$before = $this->passwordOf(10);

		try
		{
			$this->accountService->storeNewForcedPassword(10, 'unknown-token', 'New-Pass1!');
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException)
		{
			static::assertSame($before, $this->passwordOf(10));
		}
	}

	public function testChangingThePasswordEndsAllRememberMeLoginsOfTheUser(): void
	{
		$this->createToken(10, TokenPurposes::AUTOLOGIN);
		$this->createToken(10, TokenPurposes::AUTOLOGIN);
		$this->createToken(14, TokenPurposes::AUTOLOGIN);

		$this->accountService->updatePassword(10, 'New-Pass1!');

		static::assertSame(0, $this->countTokens(10, TokenPurposes::AUTOLOGIN));
		static::assertSame(1, $this->countTokens(14, TokenPurposes::AUTOLOGIN));
	}

	public function testChangingThePasswordKeepsTheOtherTokensOfTheUser(): void
	{
		$this->createToken(10, TokenPurposes::AUTOLOGIN);
		$this->createToken(10, TokenPurposes::EMAIL_VERIFICATION);

		$this->accountService->updatePassword(10, 'New-Pass1!');

		static::assertSame(1, $this->countTokens(10, TokenPurposes::EMAIL_VERIFICATION));
	}

	public function testTheOldRememberMeCookieDoesNotLogInAfterThePasswordChange(): void
	{
		$this->container->get(AutoLoginService::class)->createAutologinCookie(10);
		$this->accountService->updatePassword(10, 'New-Pass1!');

		$authService = new AuthService(
			$this->container->get(UsersService::class),
			$this->container->get(AutoLoginService::class),
			$this->container->get('ModuleLogger'),
			new LoginThrottle($this->container->get(Psr16Adapter::class))
		);

		static::assertNull($authService->loginByCookie());
	}

	public function testAFailedPasswordChangeKeepsTheRememberMeLogins(): void
	{
		$this->createToken(10, TokenPurposes::AUTOLOGIN);

		static::assertSame(0, $this->accountService->updatePassword(999, 'New-Pass1!'));

		static::assertSame(1, $this->countTokens(10, TokenPurposes::AUTOLOGIN));
	}

	public function testThePasswordChangeByLinkEndsTheRememberMeLoginsToo(): void
	{
		$this->createToken(10, TokenPurposes::AUTOLOGIN);
		$resetToken = $this->createToken(10, TokenPurposes::PASSWORD_RESET);

		$this->accountService->storeNewForcedPassword(10, $resetToken, 'New-Pass1!');

		static::assertSame(0, $this->countTokens(10, TokenPurposes::AUTOLOGIN));
	}

	public function testTheLinkOfAValidTokenIsAccepted(): void
	{
		$user = $this->orchestrator()->loadTokenUser(bin2hex($this->createToken(10, TokenPurposes::PASSWORD_RESET)));

		static::assertNotSame([], $user);
		static::assertSame(10, $user['UID']);
		static::assertSame('regular', $user['username']);
	}

	public function testInitialPasswordTokenIsAccepted(): void
	{
		$user = $this->orchestrator()->loadTokenUser(bin2hex($this->createToken(13, TokenPurposes::INITIAL_PASSWORD)));

		static::assertNotSame([], $user);
		static::assertSame(13, $user['UID']);
	}

	public function testUsedTokenIsRejected(): void
	{
		$token = $this->createToken(10, TokenPurposes::PASSWORD_RESET);
		$this->tokenService->useToken($token);

		static::assertSame([], $this->orchestrator()->loadTokenUser(bin2hex($token)));
	}

	public function testExpiredTokenIsRejected(): void
	{
		$token = $this->createToken(10, TokenPurposes::PASSWORD_RESET);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		static::assertSame([], $this->orchestrator()->loadTokenUser(bin2hex($token)));
	}

	/**
	 * A cookie token or an email verification token must not allow to set a password.
	 */
	public function testTokenOfAnotherPurposeIsRejected(): void
	{
		$autologin = $this->createToken(10, TokenPurposes::AUTOLOGIN);
		$verify    = $this->createToken(14, TokenPurposes::EMAIL_VERIFICATION);

		static::assertSame([], $this->orchestrator()->loadTokenUser(bin2hex($autologin)));
		static::assertSame([], $this->orchestrator()->loadTokenUser(bin2hex($verify)));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function brokenTokens(): array
	{
		return [
			'empty'       => [''],
			'not hex'     => ['zz11'],
			'odd length'  => ['abc'],
			'unknown'     => [str_repeat('ab', 32)],
			'sql'         => ["' OR '1'='1"],
		];
	}

	/**
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('brokenTokens')]
	public function testBrokenTokensAreRejected(string $token): void
	{
		static::assertSame([], $this->orchestrator()->loadTokenUser($token));
	}

	private function countTokens(int $UID, TokenPurposes $purpose): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ? AND purpose = ?', [$UID, $purpose->value]);
	}

	private function createToken(int $UID, TokenPurposes $purpose): string
	{
		$token = $this->tokenService->generateToken();
		$this->tokenService->insertToken($UID, $token, $purpose);

		return $token;
	}

	private function orchestrator(): Orchestrator
	{
		return $this->container->get(Orchestrator::class);
	}

	private function passwordOf(int $UID): string
	{
		return (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = ?', [$UID]);
	}
}
