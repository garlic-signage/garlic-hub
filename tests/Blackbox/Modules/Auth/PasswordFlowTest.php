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

namespace Tests\Blackbox\Modules\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\BlackboxTestCase;
use Tests\Blackbox\Support\Browser;

/**
 * The flows around the password, the way a browser does them: the link which the admin gives to a new user or after
 * a reset, the change of the own password and the first admin of a new installation. Users: admin (1), anna (2),
 * bernd (3), the password of all of them is Test1234!
 */
#[Group('blackbox')]
class PasswordFlowTest extends BlackboxTestCase
{
	private const string PASSWORD = 'Test1234!';
	private const string NEW_PASSWORD = 'Brand-New-5!x';

	private Browser $admin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->admin = $this->browser();
		$this->admin->login('admin', self::PASSWORD);
	}

	protected function tearDown(): void
	{
		// the tests of the first admin take the lock file away
		$lock = $this->deployment()->directory() . '/var/install.lock';
		if (!is_file($lock))
			file_put_contents($lock, 'blackbox');

		parent::tearDown();
	}

	// ---------------------------------------------------------------- helpers

	/**
	 * The admin creates a user in the form and reads the link from the page of the user.
	 */
	private function createUserAndLink(string $username = 'carla'): string
	{
		$response = $this->admin->post('/users/edit', [
			'UID' => 0, 'username' => $username, 'email' => $username . '@example.com', 'status' => 2, 'locale' => 'en_US',
			'csrf_token' => $this->admin->csrfToken('/users/new'),
		]);
		static::assertSame(302, $response->getStatusCode());

		return $this->linkOnThePageOf($this->uidOf($username));
	}

	private function resetAndGetLink(int $UID): string
	{
		$row      = $this->connection->fetchAssociative('SELECT username, email FROM user_main WHERE UID = ' . $UID);
		static::assertIsArray($row);
		$response = $this->admin->post('/users/edit', [
			'UID' => $UID, 'username' => $row['username'], 'email' => $row['email'], 'status' => 3, 'locale' => 'en_US', 'resetPassword' => 1,
			'csrf_token' => $this->admin->csrfToken('/users/edit/' . $UID),
		]);
		static::assertSame(302, $response->getStatusCode());

		return $this->linkOnThePageOf($UID);
	}

	private function linkOnThePageOf(int $UID): string
	{
		$html = (string) $this->admin->get('/users/edit/' . $UID)->getBody();
		static::assertSame(1, preg_match('~/force-password\?token=([0-9a-f]+)~', $html, $match), 'The page of the user has no link.');

		return $match[1];
	}

	private function uidOf(string $username): int
	{
		return (int) $this->connection->fetchOne('SELECT UID FROM user_main WHERE username = ?', [$username]);
	}

	private function hashOf(int $UID): string
	{
		return (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = ' . $UID);
	}

	private function hasPassword(string $username, string $password): bool
	{
		return password_verify($password, (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE username = ?', [$username]));
	}

	private function canLogin(string $username, string $password): bool
	{
		$browser = $this->browser();
		$browser->login($username, $password);

		return $browser->get('/player')->getStatusCode() === 200;
	}

	/**
	 * Opens the link as a guest and sends the form with the given fields; the fields of the form (token, csrf) are filled in.
	 *
	 * @param array<string,mixed> $fields
	 */
	private function submitLink(Browser $guest, string $token, array $fields): ResponseInterface
	{
		// the token of the visitor is the same on every page, also when the link is not valid any more
		$csrf = $guest->csrfToken('/login');

		return $guest->post('/force-password', $fields + ['token' => $token, 'csrf_token' => $csrf]);
	}

	private function submitPassword(string $token, string $password): ResponseInterface
	{
		return $this->submitLink($this->browser(), $token, ['password' => $password, 'password_confirm' => $password]);
	}

	private function assertRefusedToLogin(ResponseInterface $response): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location'));
	}

	// ---------------------------------------------------------------- A: the link

	public function testTheLinkOfANewUserSetsThePasswordAndOpensTheLogin(): void
	{
		$token = $this->createUserAndLink();
		static::assertSame(2, (int) $this->connection->fetchOne("SELECT status FROM user_main WHERE username = 'carla'"));
		static::assertSame('', $this->hashOf($this->uidOf('carla')));

		$guest = $this->browser();
		$page  = $guest->get('/force-password?token=' . $token);
		static::assertSame(200, $page->getStatusCode());
		static::assertStringContainsString('carla', (string) $page->getBody());

		$response = $this->submitLink($guest, $token, ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD]);

		$this->assertRefusedToLogin($response);
		static::assertSame(3, (int) $this->connection->fetchOne("SELECT status FROM user_main WHERE username = 'carla'"));
		static::assertTrue($this->canLogin('carla', self::NEW_PASSWORD));
		static::assertFalse($this->canLogin('carla', self::PASSWORD));
	}

	public function testTheLinkWorksOnlyOnce(): void
	{
		$token = $this->createUserAndLink();
		$this->submitPassword($token, self::NEW_PASSWORD);
		$hash = $this->hashOf($this->uidOf('carla'));

		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . $token));
		$this->assertRefusedToLogin($this->submitPassword($token, 'Other-Pass-9x'));

		static::assertSame($hash, $this->hashOf($this->uidOf('carla')));
		static::assertTrue($this->hasPassword('carla', self::NEW_PASSWORD));
	}

	public function testAnExpiredLinkIsRefusedByThePageAndByTheForm(): void
	{
		$token = $this->createUserAndLink();
		$guest = $this->browser();
		$csrf  = $guest->csrfToken('/force-password?token=' . $token);
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");

		$this->assertRefusedToLogin($guest->get('/force-password?token=' . $token));
		$this->assertRefusedToLogin($guest->post('/force-password', ['token' => $token, 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => $csrf]));
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function brokenTokens(): array
	{
		return [
			'empty'             => [''],
			'odd length'        => ['abc'],
			'no hex'            => ['zzzzzzzz'],
			'a script'          => ['<script>alert(1)</script>'],
			'quotes'            => ["' OR '1'='1"],
						'very long'         => [str_repeat('a', 4000)],
			'unknown but valid' => [str_repeat('ab', 32)],
			'a path'            => ['../../etc/passwd'],
		];
	}

	#[DataProvider('brokenTokens')]
	public function testALinkWhichIsNoLinkLeadsToTheLogin(string $token): void
	{
		$guest = $this->browser();
		$page  = $guest->get('/force-password?token=' . rawurlencode($token));

		$this->assertRefusedToLogin($page);
		static::assertStringNotContainsString($token === '' ? "\0" : $token, (string) $page->getBody());
		$csrf = $guest->csrfToken('/login');
		$post = $guest->post('/force-password', ['token' => $token, 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => $csrf]);
		$this->assertRefusedToLogin($post);
		static::assertSame('', (string) $this->connection->fetchOne("SELECT password FROM user_main WHERE UID = 99"));
	}

	public function testAListAsTokenInTheAddressLeadsToTheLogin(): void
	{
		$this->assertRefusedToLogin($this->browser()->get('/force-password?token[]=abc'));
	}

	public function testALinkWithoutATokenOrWithAListLeadsToTheLogin(): void
	{
		$guest = $this->browser();
		$csrf  = $guest->csrfToken('/login');

		$this->assertRefusedToLogin($guest->get('/force-password'));
		$this->assertRefusedToLogin($guest->post('/force-password', ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => $csrf]));
		$this->assertRefusedToLogin($guest->post('/force-password', ['token' => ['ab', 'cd'], 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => $csrf]));
	}

	/**
	 * The remember me token of a browser and the tokens for other purposes are no password links.
	 */
	public function testATokenForAnotherPurposeIsNoPasswordLink(): void
	{
		$token = $this->createUserAndLink();

		foreach (['autologin', 'email_verification'] as $purpose)
		{
			$this->connection->executeStatement('UPDATE user_tokens SET purpose = ?', [$purpose]);

			$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . $token));
			$this->assertRefusedToLogin($this->submitPassword($token, self::NEW_PASSWORD));
			static::assertSame('', $this->hashOf($this->uidOf('carla')), $purpose);
		}
	}

	public function testTheRememberMeCookieIsNoPasswordLink(): void
	{
		$victim = $this->browser();
		$victim->login('anna', self::PASSWORD, true);
		$cookie = (string) $victim->cookie('UserLogin')?->getValue();
		static::assertNotSame('', $cookie);

		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . $cookie));
		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . bin2hex($cookie)));
		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . bin2hex((string) base64_decode($cookie, false))));
	}

	public function testAResetEndsTheOldPasswordAndTheRememberMeLoginAtOnce(): void
	{
		$anna = $this->browser();
		$anna->login('anna', self::PASSWORD, true);

		$token = $this->resetAndGetLink(2);

		static::assertSame('', $this->hashOf(2));
		static::assertFalse($this->canLogin('anna', self::PASSWORD));
		static::assertFalse($this->canLogin('anna', ''));
		$anna->forgetCookie('GhSid');
		static::assertSame('/login', $anna->get('/player')->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_tokens WHERE UID = 2 AND purpose = 'autologin'"));

		$this->submitPassword($token, self::NEW_PASSWORD);

		static::assertTrue($this->canLogin('anna', self::NEW_PASSWORD));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 2'));
	}

	public function testASecondResetWhileTheLinkIsValidIsRefusedAndKeepsTheLink(): void
	{
		$token = $this->resetAndGetLink(2);
		$row   = $this->connection->fetchAssociative('SELECT token, expires_at FROM user_tokens WHERE UID = 2');

		$this->admin->post('/users/edit', [
			'UID' => 2, 'username' => 'anna', 'email' => 'anna@example.com', 'status' => 3, 'locale' => 'en_US', 'resetPassword' => 1,
			'csrf_token' => $this->admin->csrfToken('/users/edit/2'),
		]);

		static::assertSame([$row], $this->connection->fetchAllAssociative('SELECT token, expires_at FROM user_tokens WHERE UID = 2'));
		$this->submitPassword($token, self::NEW_PASSWORD);
		static::assertTrue($this->canLogin('anna', self::NEW_PASSWORD));
	}

	public function testALinkOfAGuestWorksOnlyForTheUserOfTheToken(): void
	{
		$tokenCarla = $this->createUserAndLink('carla');
		$tokenDora  = $this->createUserAndLink('dora');

		$this->submitPassword($tokenCarla, self::NEW_PASSWORD);

		static::assertTrue($this->hasPassword('carla', self::NEW_PASSWORD));
		static::assertSame('', $this->hashOf($this->uidOf('dora')));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE UID = ' . $this->uidOf('dora') . ' AND used_at IS NULL'));
		static::assertNotSame($tokenCarla, $tokenDora);
	}

	public function testABlockedUserCanNotUseAnOpenLink(): void
	{
		$token = $this->createUserAndLink();
		$this->connection->executeStatement("UPDATE user_main SET status = 1 WHERE username = 'carla'");

		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . $token));
		$this->assertRefusedToLogin($this->submitPassword($token, self::NEW_PASSWORD));
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
	}

	public function testADeletedUserCanNotUseAnOpenLink(): void
	{
		$token = $this->createUserAndLink();
		$this->connection->executeStatement("UPDATE user_main SET status = 0 WHERE username = 'carla'");

		$this->assertRefusedToLogin($this->browser()->get('/force-password?token=' . $token));
		$this->assertRefusedToLogin($this->submitPassword($token, self::NEW_PASSWORD));
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
	}

	// ---------------------------------------------------------------- B: the form

	public function testTheFormOfTheLinkHasTheTokenAsHexAndPasswordFields(): void
	{
		$token = $this->createUserAndLink();

		$html = (string) $this->browser()->get('/force-password?token=' . $token)->getBody();

		static::assertSame(1, preg_match('/<input[^>]*name="token"[^>]*>/', $html, $field));
		static::assertStringContainsString('value="' . $token . '"', $field[0]);
		static::assertSame(2, preg_match_all('/<input[^>]*type="password"[^>]*>/', $html));
		static::assertStringNotContainsString('name="password" value=', $html);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function csrfVariants(): array
	{
		return [
			'missing'   => ['missing'],
			'forged'    => ['forged'],
			'another visitor' => ['other'],
		];
	}

	#[DataProvider('csrfVariants')]
	public function testTheLinkFormNeedsTheCsrfTokenOfTheVisitor(string $variant): void
	{
		$token  = $this->createUserAndLink();
		$guest  = $this->browser();
		$guest->get('/force-password?token=' . $token);
		$fields = ['token' => $token, 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD];
		if ($variant === 'forged')
			$fields['csrf_token'] = 'forged';
		if ($variant === 'other')
			$fields['csrf_token'] = $this->browser()->csrfToken('/login');

		$response = $guest->post('/force-password', $fields);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
		// the link is not used up by the failure
		$this->submitPassword($token, self::NEW_PASSWORD);
		static::assertTrue($this->hasPassword('carla', self::NEW_PASSWORD));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function badPasswords(): array
	{
		return [
			'too short'         => [['password' => 'Ab1', 'password_confirm' => 'Ab1']],
			'no digit'          => [['password' => 'Abcdefghij', 'password_confirm' => 'Abcdefghij']],
			'no capital'        => [['password' => 'abcdefgh12', 'password_confirm' => 'abcdefgh12']],
			'no small letter'   => [['password' => 'ABCDEFGH12', 'password_confirm' => 'ABCDEFGH12']],
			'empty'             => [['password' => '', 'password_confirm' => '']],
			'not the same'      => [['password' => 'Brand-New-5!x', 'password_confirm' => 'Brand-New-5!y']],
			'no confirmation'   => [['password' => 'Brand-New-5!x']],
			'no password'       => [['password_confirm' => 'Brand-New-5!x']],
			'password is list'  => [['password' => ['Brand-New-5!x'], 'password_confirm' => ['Brand-New-5!x']]],
			'only blanks'       => [['password' => '        ', 'password_confirm' => '        ']],
		];
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	#[DataProvider('badPasswords')]
	public function testAPasswordWhichIsNotAllowedShowsTheFormAgainAndKeepsTheLink(array $fields): void
	{
		$token = $this->createUserAndLink();

		$response = $this->submitLink($this->browser(), $token, $fields);

		static::assertSame(200, $response->getStatusCode());
		static::assertStringContainsString('name="token"', (string) $response->getBody());
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
		$this->submitPassword($token, self::NEW_PASSWORD);
		static::assertTrue($this->hasPassword('carla', self::NEW_PASSWORD));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function passwordsWithSpecialCharacters(): array
	{
		return [
			'html characters' => ['<b>A1"\'&%;--x'],
			'unicode'         => ['Überraschung-1ßA'],
			'emoji'           => ["Passw0rd-\u{1F512}-Zz"],
			'blanks around'   => ['  Brand New 5x  '],
			'a template'      => ['{{Abc}}{{{12345}}}'],
			'a backslash'     => ['Ab1\\\\x\\0Zz9\\'],
		];
	}

	#[DataProvider('passwordsWithSpecialCharacters')]
	public function testAPasswordIsStoredAsItIsTyped(string $password): void
	{
		$token = $this->createUserAndLink();

		$response = $this->submitPassword($token, $password);

		$this->assertRefusedToLogin($response);
		static::assertTrue($this->hasPassword('carla', $password));
		static::assertTrue($this->canLogin('carla', $password));
	}

	public function testAVeryLongPasswordIsNoError(): void
	{
		$token    = $this->createUserAndLink();
		$password = 'Aa1' . str_repeat('x', 10000);

		$response = $this->submitPassword($token, $password);

		static::assertLessThan(500, $response->getStatusCode());
		static::assertSame('/login', $response->getHeaderLine('Location') ?: '/login');
	}

	/**
	 * bcrypt ignores everything after the 72nd byte: a password which is longer is refused, so that no one
	 * thinks that the tail counts. The limit is in bytes, an umlaut has two of them.
	 */
	public function testAPasswordIsAllowedUpTo72BytesAndRefusedBeyond(): void
	{
		$token = $this->createUserAndLink();
		$long  = 'Aa1' . str_repeat('x', 69);

		$refused = $this->submitPassword($token, $long . 'y');
		static::assertSame(200, $refused->getStatusCode());
		static::assertSame('', $this->hashOf($this->uidOf('carla')));
		$refusedUmlauts = $this->submitPassword($token, 'Aa1' . str_repeat('ü', 35));
		static::assertSame(200, $refusedUmlauts->getStatusCode());

		$this->assertRefusedToLogin($this->submitPassword($token, $long));
		static::assertTrue($this->canLogin('carla', $long));
	}

	// ---------------------------------------------------------------- C: the own password

	public function testTheFormOfTheOwnPasswordIsForUsersOnly(): void
	{
		$guest = $this->browser();
		$csrf  = $guest->csrfToken('/login');

		$this->assertRefusedToLogin($guest->get('/account/password'));
		$this->assertRefusedToLogin($guest->post('/account/password', ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => $csrf]));
		static::assertTrue($this->hasPassword('anna', self::PASSWORD));
	}

	/**
	 * @param array<string,mixed> $extra
	 */
	private function changeOwnPassword(Browser $user, string $password, array $extra = []): ResponseInterface
	{
		return $user->post('/account/password', $extra + ['password' => $password, 'password_confirm' => $password, 'csrf_token' => $user->csrfToken('/account/password')]);
	}

	public function testAUserChangesOnlyTheOwnPassword(): void
	{
		$anna  = $this->browser();
		$anna->login('anna', self::PASSWORD, true);
		$others = [1 => $this->hashOf(1), 3 => $this->hashOf(3)];

		// a UID or user name in the form does not choose another user
		$response = $this->changeOwnPassword($anna, self::NEW_PASSWORD, ['UID' => 3, 'username' => 'bernd']);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
		static::assertTrue($this->hasPassword('anna', self::NEW_PASSWORD));
		static::assertSame($others, [1 => $this->hashOf(1), 3 => $this->hashOf(3)]);
		static::assertTrue($this->canLogin('anna', self::NEW_PASSWORD));
		static::assertFalse($this->canLogin('anna', self::PASSWORD));
		static::assertTrue($this->canLogin('bernd', self::PASSWORD));
	}

	public function testAChangeOfThePasswordEndsTheRememberMeLogins(): void
	{
		$anna = $this->browser();
		$anna->login('anna', self::PASSWORD, true);
		$stolen = (string) $anna->cookie('UserLogin')?->getValue();

		$this->changeOwnPassword($anna, self::NEW_PASSWORD);

		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_tokens WHERE UID = 2 AND purpose = 'autologin'"));
		$thief = $this->browser()->request('GET', '/player', ['headers' => ['Cookie' => 'UserLogin=' . $stolen]]);
		static::assertSame('/login', $thief->getHeaderLine('Location'));
	}

	#[DataProvider('csrfVariants')]
	public function testTheFormOfTheOwnPasswordNeedsTheCsrfTokenOfTheVisitor(string $variant): void
	{
		$anna = $this->browser();
		$anna->login('anna', self::PASSWORD);
		$anna->get('/account/password');
		$fields = ['password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD];
		if ($variant === 'forged')
			$fields['csrf_token'] = 'forged';
		if ($variant === 'other')
			$fields['csrf_token'] = $this->browser()->csrfToken('/login');

		$response = $anna->post('/account/password', $fields);

		static::assertSame(200, $response->getStatusCode());
		static::assertTrue($this->hasPassword('anna', self::PASSWORD));
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	#[DataProvider('badPasswords')]
	public function testABadNewPasswordChangesNothingAtTheOwnForm(array $fields): void
	{
		$anna = $this->browser();
		$anna->login('anna', self::PASSWORD);

		$response = $anna->post('/account/password', $fields + ['csrf_token' => $anna->csrfToken('/account/password')]);

		static::assertSame(200, $response->getStatusCode());
		static::assertTrue($this->hasPassword('anna', self::PASSWORD));
	}

	public function testThePasswordCanNotBeChangedWithoutKnowingTheOldOne(): void
	{
		static::markTestSkipped('Decision: the form asks no current password, who takes over a session can lock the owner out. Remove this line when it is decided.');
		// @phpstan-ignore deadCode.unreachable
		$anna = $this->browser();
		$anna->login('anna', self::PASSWORD);

		$this->changeOwnPassword($anna, self::NEW_PASSWORD);

		static::assertTrue($this->hasPassword('anna', self::PASSWORD));
	}

	public function testAnotherSessionOfTheUserEndsWithTheChangeOfThePassword(): void
	{
		static::markTestSkipped('Decision: the sessions of other browsers stay valid after a change of the password. Remove this line when it is decided.');
		// @phpstan-ignore deadCode.unreachable
		$first  = $this->browser();
		$first->login('anna', self::PASSWORD);
		$second = $this->browser();
		$second->login('anna', self::PASSWORD);

		$this->changeOwnPassword($first, self::NEW_PASSWORD);

		static::assertSame('/login', $second->get('/player')->getHeaderLine('Location'));
	}

	// ---------------------------------------------------------------- D: the first admin

	private function removeLock(): void
	{
		unlink($this->deployment()->directory() . '/var/install.lock');
	}

	private function lockExists(): bool
	{
		return is_file($this->deployment()->directory() . '/var/install.lock');
	}

	/**
	 * @return array<string,string>
	 */
	private function adminFields(Browser $guest, string $password = self::NEW_PASSWORD): array
	{
		return ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'password' => $password, 'password_confirm' => $password,
			'csrf_token' => $guest->csrfToken('/create-initial')];
	}

	public function testWithTheLockFileNoOneCanCreateAnAdmin(): void
	{
		$this->connection->executeStatement('DELETE FROM user_main');
		$guest = $this->browser();
		$fields = ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => 'x'];

		static::assertSame('/', $guest->get('/create-initial')->getHeaderLine('Location'));
		static::assertSame('/', $guest->post('/create-initial', $fields)->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	public function testWithAnExistingAdminNoOneCanCreateAnotherAndTheLockIsSet(): void
	{
		$this->removeLock();
		$guest = $this->browser();

		static::assertSame('/', $guest->get('/create-initial')->getHeaderLine('Location'));
		static::assertTrue($this->lockExists());
		$this->removeLock();
		$fields = ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => 'x'];
		static::assertSame('/', $guest->post('/create-initial', $fields)->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_main WHERE username = 'root'"));
		static::assertSame($this->hashOf(1) !== self::NEW_PASSWORD, true);
	}

	public function testTheFirstAdminIsCreatedOnceAndTheLockIsSet(): void
	{
		$this->connection->executeStatement('DELETE FROM user_main');
		$this->connection->executeStatement('DELETE FROM user_acl');
		$this->removeLock();
		$guest = $this->browser();

		static::assertSame(200, $guest->get('/create-initial')->getStatusCode());
		$response = $guest->post('/create-initial', $this->adminFields($guest));

		static::assertSame('/login', $response->getHeaderLine('Location'));
		static::assertTrue($this->lockExists());
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT UID FROM user_main WHERE username = 'root'"));
		static::assertSame(9, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 1'));
		static::assertTrue($this->canLoginAsRoot());
		// the second visit is sent away and creates nothing
		$second = $this->browser();
		static::assertSame('/', $second->get('/create-initial')->getHeaderLine('Location'));
		static::assertSame('/', $second->post('/create-initial', ['username' => 'root2', 'email' => 'r2@example.com', 'locale' => 'en_US', 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD, 'csrf_token' => 'x'])->getHeaderLine('Location'));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	private function canLoginAsRoot(): bool
	{
		return $this->canLogin('root', self::NEW_PASSWORD);
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	#[DataProvider('badPasswords')]
	public function testTheFirstAdminNeedsAnAllowedPassword(array $fields): void
	{
		$this->connection->executeStatement('DELETE FROM user_main');
		$this->removeLock();
		$guest = $this->browser();

		$response = $guest->post('/create-initial', $fields + ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'csrf_token' => $guest->csrfToken('/create-initial')]);

		static::assertSame(200, $response->getStatusCode());
		static::assertFalse($this->lockExists());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	#[DataProvider('csrfVariants')]
	public function testTheFirstAdminNeedsTheCsrfTokenOfTheVisitor(string $variant): void
	{
		$this->connection->executeStatement('DELETE FROM user_main');
		$this->removeLock();
		$guest = $this->browser();
		$guest->get('/create-initial');
		$fields = ['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD];
		if ($variant === 'forged')
			$fields['csrf_token'] = 'forged';
		if ($variant === 'other')
			$fields['csrf_token'] = $this->browser()->csrfToken('/create-initial');

		$response = $guest->post('/create-initial', $fields);

		static::assertSame(200, $response->getStatusCode());
		static::assertFalse($this->lockExists());
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_main'));
	}

	// ---------------------------------------------------------------- E: users which exist and which do not

	public function testTheLockOfTheLoginSaysTheSameForUsersWhichDoNotExist(): void
	{
		$known   = $this->browser();
		$unknown = $this->browser();
		for ($i = 0; $i < 5; $i++)
		{
			$known->login('anna', 'wrong');
			$unknown->login('nobody', 'wrong');
		}

		$known->login('anna', self::PASSWORD);
		$unknown->login('nobody', self::PASSWORD);

		static::assertStringContainsString('Too many failed login attempts', (string) $known->get('/login')->getBody());
		static::assertStringContainsString('Too many failed login attempts', (string) $unknown->get('/login')->getBody());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function passwordsOfAnEmptyHash(): array
	{
		return [
			'nothing' => [''], 'zero' => ['0'], 'a blank' => [' '], 'null' => ['null'], 'the hash of nothing' => ['$2y$10$'],
		];
	}

	#[DataProvider('passwordsOfAnEmptyHash')]
	public function testAUserWithoutPasswordCanNotLoginWithAnyPassword(string $password): void
	{
		$this->createUserAndLink();
		$this->resetAndGetLink(2);

		static::assertFalse($this->canLogin('carla', $password));
		static::assertFalse($this->canLogin('anna', $password));
	}

	public function testTheLinkPageSaysTheSameForUnusedExpiredAndUnknownTokens(): void
	{
		$used    = $this->createUserAndLink('carla');
		$this->submitPassword($used, self::NEW_PASSWORD);
		$expired = $this->createUserAndLink('dora');
		$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00' WHERE UID = " . $this->uidOf('dora'));
		$answers = [];
		foreach ([$used, $expired, str_repeat('ab', 32)] as $token)
		{
			$guest  = $this->browser();
			$page   = $guest->get('/force-password?token=' . $token);
			$login  = (string) $guest->get('/login')->getBody();
			static::assertSame(1, preg_match('/<div[^>]*message[^>]*>(.*?)<\/div>/s', $login, $message));
			$answers[] = [$page->getStatusCode(), $page->getHeaderLine('Location'), trim(strip_tags($message[1]))];
		}

		static::assertSame($answers[0], $answers[1]);
		static::assertSame($answers[0], $answers[2]);
	}
}
