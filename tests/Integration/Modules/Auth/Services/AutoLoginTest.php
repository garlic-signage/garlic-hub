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

namespace Tests\Integration\Modules\Auth\Services;

use App\Modules\Auth\AuthService;
use App\Modules\Auth\AutoLoginService;
use App\Modules\Auth\LoginThrottle;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserEntity;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Phpfastcache\Helper\Psr16Adapter;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Integration\Support\InMemoryCookie;

/**
 * Login by the autologin cookie ("remember me"). The cookie holds a random token, the database
 * only its hash. Every use replaces the token. Users of the fixture: 10 regular, 11 deleted,
 * 12 blocked, 13 not verified, 14 other.
 */
#[Group('auth')]
class AutoLoginTest extends ContainerTestCase
{
	private InMemoryCookie $cookie;
	private UserTokenService $tokenService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->cookie       = $this->container->get(\App\Framework\Http\Cookie::class);
		$this->tokenService = $this->container->get(UserTokenService::class);
	}

	public function testCreatingTheCookieStoresOnlyTheHashOfTheToken(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);

		$cookieValue = $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		$stored      = $this->connection->fetchAllAssociative('SELECT token, UID, purpose FROM user_tokens');

		static::assertNotNull($cookieValue);
		static::assertCount(1, $stored);
		static::assertNotSame($cookieValue, $stored[0]['token']);
		static::assertSame(64, strlen($stored[0]['token']));
		static::assertSame(10, (int) $stored[0]['UID']);
		static::assertSame('autologin', $stored[0]['purpose']);
		static::assertEqualsWithDelta(strtotime('+28 days'), $this->cookie->sentExpire(AutoLoginService::COOKIE_NAME_AUTO_LOGIN), 5);
	}

	public function testLoginByCookie(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);

		$user = $this->loginByCookie();

		static::assertSame(10, $user?->getMain()['UID']);
	}

	public function testTheTokenIsReplacedAtEveryUse(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);
		$first = (string) $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);

		$this->loginByCookie();

		$second = (string) $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		static::assertNotSame($first, $second);
		static::assertNull($this->tokenService->findByToken($first));
		static::assertNotNull($this->tokenService->findByToken($second));
		// the second one works as well
		static::assertSame(10, $this->loginByCookie()?->getMain()['UID']);
	}

	/**
	 * A used token shows up again: somebody has copied the cookie. All tokens of the user are
	 * deleted, so the thief and the owner have to log in again.
	 */
	public function testReplayOfAUsedTokenDeletesAllTokensOfTheUser(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);
		$stolen = (string) $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		$this->loginByCookie();

		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = $stolen;
		static::assertNull($this->loginByCookie());

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 10'));
	}

	public function testReplayDoesNotTouchTheTokensOfOtherUsers(): void
	{
		$this->autoLoginFor(14)->createAutologinCookie(14);
		$this->autoLoginFor(10)->createAutologinCookie(10);
		$stolen = (string) $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN);
		$this->loginByCookie();
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = $stolen;
		$this->loginByCookie();

		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 14'));
	}

	public function testExpiredTokenIsRejected(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		static::assertNull($this->loginByCookie());
	}

	public function testUnknownTokenIsRejected(): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = bin2hex(random_bytes(32));

		static::assertNull($this->loginByCookie());
	}

	public function testNoCookieNoLogin(): void
	{
		static::assertNull($this->loginByCookie());
	}

	public function testEmptyCookieIsRejected(): void
	{
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = '';

		static::assertNull($this->loginByCookie());
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function notAllowedUsers(): array
	{
		return ['deleted' => [11], 'blocked' => [12], 'not verified' => [13]];
	}

	/**
	 * A user which can not log in with the password must not get in with an old cookie either.
	 */
	#[DataProvider('notAllowedUsers')]
	public function testDeletedBlockedAndNotVerifiedUsersCanNotLoginByCookie(int $UID): void
	{
		$this->autoLoginFor($UID)->createAutologinCookie($UID);

		static::assertNull($this->loginByCookie());
	}

	/**
	 * @return array<string,array{TokenPurposes}>
	 */
	public static function otherPurposes(): array
	{
		return [
			'password reset'     => [TokenPurposes::PASSWORD_RESET],
			'initial password'   => [TokenPurposes::INITIAL_PASSWORD],
			'email verification' => [TokenPurposes::EMAIL_VERIFICATION],
		];
	}

	/**
	 * Tokens which were made for an email link must not work as a login cookie.
	 */
	#[DataProvider('otherPurposes')]
	public function testTokenOfAnotherPurposeIsNoLogin(TokenPurposes $purpose): void
	{
		$token = $this->tokenService->generateToken();
		$this->tokenService->insertToken(10, $token, $purpose);
		$_COOKIE[AutoLoginService::COOKIE_NAME_AUTO_LOGIN] = $token;

		static::assertNull($this->loginByCookie());
	}

	public function testLoginByCookieCountsTheLogin(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);

		$this->loginByCookie();

		$user = $this->connection->fetchAssociative('SELECT num_logins, login_time FROM user_main WHERE UID = 10');
		static::assertIsArray($user);
		static::assertSame(1, (int) $user['num_logins']);
		static::assertNotNull($user['login_time']);
	}

	public function testLogoutDeletesTheTokenAndTheCookie(): void
	{
		$this->autoLoginFor(10)->createAutologinCookie(10);
		$this->autoLoginFor(14)->createAutologinCookie(14);
		$this->cookie->createCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN, (string) $this->cookie->getCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN), new \DateTime('+1 day'));

		$this->newAuthService()->logout(['UID' => 14, 'username' => 'other']);

		static::assertFalse($this->cookie->hasCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = 14'));
	}

	private function autoLoginFor(int $UID): AutoLoginService
	{
		return $this->container->get(AutoLoginService::class);
	}

	private function loginByCookie(): ?UserEntity
	{
		return $this->newAuthService()->loginByCookie();
	}

	private function newAuthService(): AuthService
	{
		return new AuthService(
			$this->container->get(UsersService::class),
			$this->container->get(AutoLoginService::class),
			$this->container->get('ModuleLogger'),
			new LoginThrottle($this->container->get(Psr16Adapter::class))
		);
	}
}
