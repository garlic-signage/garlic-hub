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

namespace Tests\Blackbox\Modules\Auth;

use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * Login, logout and the remember me cookie, the way a browser does it. The users are admin (1),
 * anna (2) and bernd (3), the password of all of them is Test1234!
 */
#[Group('blackbox')]
class LoginFlowTest extends BlackboxTestCase
{
	private const string PASSWORD = 'Test1234!';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	public function testTheLoginPageHasAFormAndASafeSessionCookie(): void
	{
		$response = $this->browser()->get('/login');

		static::assertSame(200, $response->getStatusCode());
		static::assertStringContainsString('name="csrf_token"', (string) $response->getBody());
		$cookie = $response->getHeaderLine('Set-Cookie');
		static::assertStringStartsWith('GhSid=', $cookie);
		static::assertStringContainsString('HttpOnly', $cookie);
		static::assertStringContainsString('SameSite=Strict', $cookie);
		// the test server speaks http: the Secure flag is only set over https
		static::assertStringNotContainsString('Secure', $cookie);
	}

	public function testAGuestIsSentToTheLoginOrGetsAJsonError(): void
	{
		$browser = $this->browser();

		$page = $browser->get('/player');
		static::assertSame(302, $page->getStatusCode());
		static::assertSame('/login', $page->getHeaderLine('Location'));
		// a redirect for a guest needs no session
		static::assertSame([], $page->getHeader('Set-Cookie'));

		$api = $browser->get('/async/playlists/items/load/1');
		static::assertSame(401, $api->getStatusCode());
		static::assertSame('application/json', $api->getHeaderLine('Content-Type'));
		static::assertFalse(json_decode((string) $api->getBody(), true)['success']);
	}

	public function testLoginLogoutAndTheProtectedPages(): void
	{
		$browser = $this->browser();

		$login = $browser->login('anna', self::PASSWORD);
		static::assertSame(302, $login->getStatusCode());
		static::assertSame('/', $login->getHeaderLine('Location'));
		static::assertSame(200, $browser->get('/')->getStatusCode());
		static::assertSame(200, $browser->get('/player')->getStatusCode());
		// the login page is for guests: a logged in user is sent away
		static::assertSame('/', $browser->get('/login')->getHeaderLine('Location'));

		$logout = $browser->get('/logout');
		static::assertSame(302, $logout->getStatusCode());
		static::assertSame('/login', $logout->getHeaderLine('Location'));
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
	}

	public function testLoginWorksWithTheEmailAddress(): void
	{
		$browser = $this->browser();

		static::assertSame('/', $browser->login('ANNA@example.com', self::PASSWORD)->getHeaderLine('Location'));
		static::assertSame(200, $browser->get('/player')->getStatusCode());
	}

	public function testAWrongPasswordGivesTheSameAnswerAsAnUnknownUser(): void
	{
		$wrong   = $this->browser();
		$unknown = $this->browser();

		$a = $wrong->login('anna', 'wrong');
		$b = $unknown->login('nobody', 'wrong');

		static::assertSame(302, $a->getStatusCode());
		static::assertSame('/login', $a->getHeaderLine('Location'));
		static::assertSame($a->getStatusCode(), $b->getStatusCode());
		static::assertSame($a->getHeaderLine('Location'), $b->getHeaderLine('Location'));
		static::assertStringContainsString('Invalid credentials.', (string) $wrong->get('/login')->getBody());
		static::assertStringContainsString('Invalid credentials.', (string) $unknown->get('/login')->getBody());
		static::assertSame('/login', $wrong->get('/player')->getHeaderLine('Location'));
	}

	public function testLoginWithoutAValidCsrfTokenIsRefused(): void
	{
		$browser = $this->browser();
		$browser->get('/login');

		$response = $browser->post('/login', ['username' => 'anna', 'password' => self::PASSWORD, 'csrf_token' => 'forged']);

		static::assertSame('/login', $response->getHeaderLine('Location'));
		// the message is shown once, by the next page
		static::assertStringContainsString('Invalid CSRF token', (string) $browser->get('/login')->getBody());
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
	}

	public function testAUserWhoCanNotLoginStaysOut(): void
	{
		$this->connection->executeStatement('UPDATE user_main SET status = 1 WHERE UID = 3');
		$browser = $this->browser();

		$browser->login('bernd', self::PASSWORD);

		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
	}

	public function testTooManyFailuresLockTheLoginEvenForTheRightPassword(): void
	{
		$browser = $this->browser();
		for ($i = 0; $i < 5; $i++)
			$browser->login('anna', 'wrong');

		$browser->login('anna', self::PASSWORD);

		static::assertStringContainsString('Too many failed login attempts', (string) $browser->get('/login')->getBody());
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
		// another user from the same address is not locked
		$other = $this->browser();
		$other->login('bernd', self::PASSWORD);
		static::assertSame(200, $other->get('/player')->getStatusCode());
	}

	public function testTheRememberMeCookieBringsTheUserBackAfterTheSessionIsGone(): void
	{
		$browser = $this->browser();
		$login   = $browser->login('anna', self::PASSWORD, true);

		$remember = implode(' ', $login->getHeader('Set-Cookie'));
		static::assertStringContainsString('UserLogin=', $remember);
		static::assertStringContainsString('HttpOnly', $remember);
		$first = $browser->cookie('UserLogin');
		static::assertNotNull($first);

		// the browser was closed: the session cookie is gone, the remember me cookie stays
		$browser->forgetCookie('GhSid');
		static::assertSame(200, $browser->get('/player')->getStatusCode());

		$second = $browser->cookie('UserLogin');
		static::assertNotNull($second);
		static::assertNotSame($first->getValue(), $second->getValue(), 'The token is replaced at every use.');
	}

	public function testALogoutEndsTheRememberMeLogin(): void
	{
		$browser = $this->browser();
		$browser->login('anna', self::PASSWORD, true);

		$browser->get('/logout');
		$browser->forgetCookie('GhSid');

		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_tokens WHERE UID = 2 AND purpose = 'autologin'"));
	}

	/**
	 * The admin blocks the user in the user form, as in the browser. The user closes the browser and
	 * opens it again: the old remember me cookie must not bring him back.
	 */
	public function testABlockedUserIsNotLoggedInByTheOldRememberMeCookie(): void
	{
		$user = $this->browser();
		$user->login('bernd', self::PASSWORD, true);
		$user->get('/player');

		$admin = $this->browser();
		$admin->login('admin', self::PASSWORD);
		$response = $admin->post('/users/edit', [
			'UID' => 3, 'username' => 'bernd', 'email' => 'bernd@example.com', 'locale' => 'en_US', 'status' => 1,
			'csrf_token' => $admin->csrfToken('/users/edit/3'),
		]);
		static::assertSame(302, $response->getStatusCode());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 3'));

		$user->forgetCookie('GhSid');

		static::assertSame('/login', $user->get('/player')->getHeaderLine('Location'));
	}

	public function testACopiedRememberMeCookieDoesNotWorkTwice(): void
	{
		$owner = $this->browser();
		$owner->login('anna', self::PASSWORD, true);
		$stolen = (string) $owner->cookie('UserLogin')?->getValue();

		// the owner comes back first: the token is replaced
		$owner->forgetCookie('GhSid');
		$owner->get('/player');

		$thief = $this->browser();
		$response = $thief->request('GET', '/player', ['headers' => ['Cookie' => 'UserLogin=' . $stolen]]);

		static::assertSame('/login', $response->getHeaderLine('Location'));
	}
}
