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

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Group;
use Tests\Blackbox\Support\BlackboxTestCase;
use Tests\Blackbox\Support\Browser;
use Tests\Support\FixedClock;

/**
 * The second factor over HTTP: the setup in the account, the second step of the login, which can not be skipped,
 * and the setting with which the admin requires it. The users are admin (1), anna (2) and bernd (3),
 * the password of all of them is Test1234!
 */
#[Group('blackbox')]
class TwoFactorFlowTest extends BlackboxTestCase
{
	private const string PASSWORD = 'Test1234!';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	/**
	 * The code of the app: the secret is encrypted with the key of the test server.
	 */
	private function codeOf(int $UID, int $offset = 0): string
	{
		$key    = Key::loadFromAsciiSafeString((string) file_get_contents($this->deployment()->directory() . '/var/keys/encryption.key'));
		$secret = Crypto::decrypt((string) $this->connection->fetchOne('SELECT secret FROM user_two_factor WHERE UID = ?', [$UID]), $key);
		static::assertNotSame('', $secret);

		return TOTP::createFromSecret($secret, new FixedClock(time()))->at(max(0, time() + $offset));
	}

	/**
	 * Logged in with the password only, anna sets up the second factor in her account.
	 *
	 * @return list<string> the recovery codes
	 */
	private function setUpSecondFactor(Browser $browser): array
	{
		$browser->login('anna', self::PASSWORD);
		$page = (string) $browser->get('/account/settings')->getBody();
		static::assertStringContainsString('<svg', $page, 'the QR code');

		$confirm = $browser->post('/account/two-factor', ['code' => $this->codeOf(2), 'csrf_token' => $browser->csrfToken('/account/settings')]);
		static::assertSame('/account/settings#two_factor', $confirm->getHeaderLine('Location'));

		$page = (string) $browser->get('/account/settings')->getBody();
		preg_match_all('#<code>([a-z2-9]{5}-[a-z2-9]{5})</code>#', $page, $matches);
		static::assertCount(10, $matches[1], 'the recovery codes are shown once');
		static::assertDoesNotMatchRegularExpression('#<code>[a-z2-9]{5}-[a-z2-9]{5}</code>#', (string) $browser->get('/account/settings')->getBody());

		$browser->get('/logout');

		return $matches[1];
	}

	private function secondStep(Browser $browser, string $code): string
	{
		return $browser->post('/login/two-factor', ['code' => $code, 'csrf_token' => $browser->csrfToken('/login/two-factor')])->getHeaderLine('Location');
	}

	public function testThePasswordAloneDoesNotLogInAndTheCodeDoes(): void
	{
		$browser = $this->browser();
		$this->setUpSecondFactor($browser);

		$login = $browser->login('anna', self::PASSWORD);
		static::assertSame('/login/two-factor', $login->getHeaderLine('Location'));
		// not logged in yet: neither the pages nor the api
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
		static::assertSame(401, $browser->get('/async/playlists/items/load/1')->getStatusCode());

		static::assertSame('/login/two-factor', $this->secondStep($browser, '000000'), 'a wrong code: one more try');
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));

		// the slot of the setup is used: the code of the next one, which the app shows in a moment
		static::assertSame('/', $this->secondStep($browser, $this->codeOf(2, 30)));
		static::assertSame(200, $browser->get('/player')->getStatusCode());
	}

	public function testARecoveryCodeWorksOnce(): void
	{
		$browser = $this->browser();
		$codes   = $this->setUpSecondFactor($browser);

		$browser->login('anna', self::PASSWORD);
		static::assertSame('/', $this->secondStep($browser, $codes[0]));
		$browser->get('/logout');

		$browser->login('anna', self::PASSWORD);
		static::assertSame('/login/two-factor', $this->secondStep($browser, $codes[0]));
	}

	public function testTheSecondStepWithoutThePasswordLeadsToTheLogin(): void
	{
		$browser = $this->browser();
		$this->setUpSecondFactor($browser);

		static::assertSame('/login', $browser->get('/login/two-factor')->getHeaderLine('Location'));
		$step = $browser->post('/login/two-factor', ['code' => $this->codeOf(2, 30), 'csrf_token' => $browser->csrfToken('/login')]);
		static::assertSame('/login', $step->getHeaderLine('Location'));
		static::assertSame('/login', $browser->get('/player')->getHeaderLine('Location'));
	}

	public function testTooManyWrongCodesStartTheLoginAgain(): void
	{
		$browser = $this->browser();
		$this->setUpSecondFactor($browser);
		$browser->login('anna', self::PASSWORD);

		for ($i = 1; $i < 5; $i++)
			static::assertSame('/login/two-factor', $this->secondStep($browser, '000000'));

		static::assertSame('/login', $this->secondStep($browser, '000000'), 'the fifth wrong code');
		static::assertSame('/login', $browser->get('/login/two-factor')->getHeaderLine('Location'));
		// the right code does not help now, the user is blocked for a while
		$browser->login('anna', self::PASSWORD);
		static::assertSame('/login', $this->secondStep($browser, $this->codeOf(2, 30)));
	}

	public function testTheRememberMeCookieComesOnlyWithTheSecondFactor(): void
	{
		$browser = $this->browser();
		$this->setUpSecondFactor($browser);

		$browser->login('anna', self::PASSWORD, true);
		static::assertNull($browser->cookie('UserLogin'));

		$this->secondStep($browser, $this->codeOf(2, 30));
		static::assertNotNull($browser->cookie('UserLogin'));
	}

	public function testWhileTheAdminRequiresItAUserWithoutItReachesOnlyHisAccount(): void
	{
		$this->deployment()->changeSettings('users', ['TwoFactor' => ['required' => '1']]);
		$browser = $this->browser();

		static::assertSame('/', $browser->login('bernd', self::PASSWORD)->getHeaderLine('Location'));
		static::assertSame('/account/settings#two_factor', $browser->get('/')->getHeaderLine('Location'));
		static::assertSame('/account/settings#two_factor', $browser->get('/player')->getHeaderLine('Location'));
		static::assertSame(403, $browser->get('/async/playlists/items/load/1')->getStatusCode());

		$account = (string) $browser->get('/account/settings')->getBody();
		static::assertStringContainsString('<svg', $account);
		// he can not turn it off himself while it is required: there is no form for it
		static::assertStringNotContainsString('/account/two-factor/disable', $account);

		$browser->post('/account/two-factor', ['code' => $this->codeOf(3), 'csrf_token' => $browser->csrfToken('/account/settings')]);
		static::assertSame(200, $browser->get('/player')->getStatusCode());
	}

	public function testTheUserTurnsItOffWithACode(): void
	{
		$browser = $this->browser();
		$codes   = $this->setUpSecondFactor($browser);
		$browser->login('anna', self::PASSWORD);
		$this->secondStep($browser, $this->codeOf(2, 30));

		$off = $browser->post('/account/two-factor/disable', ['code' => '000000', 'csrf_token' => $browser->csrfToken('/account/settings')]);
		static::assertSame('/account/settings#two_factor', $off->getHeaderLine('Location'));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_two_factor WHERE UID = 2 AND enabled_at IS NOT NULL'), 'a wrong code changes nothing');

		// the codes of the app are used in this half minute: a recovery code
		$browser->post('/account/two-factor/disable', ['code' => $codes[0], 'csrf_token' => $browser->csrfToken('/account/settings')]);
		$browser->get('/logout');
		static::assertSame('/', $browser->login('anna', self::PASSWORD)->getHeaderLine('Location'));
	}

	public function testTooManyWrongCodesBlockTurningItOff(): void
	{
		$browser = $this->browser();
		$codes   = $this->setUpSecondFactor($browser);
		$browser->login('anna', self::PASSWORD);
		$this->secondStep($browser, $this->codeOf(2, 30));

		for ($i = 0; $i < 5; $i++)
			$browser->post('/account/two-factor/disable', ['code' => '000000', 'csrf_token' => $browser->csrfToken('/account/settings')]);

		// the right recovery code does not help now
		$browser->post('/account/two-factor/disable', ['code' => $codes[0], 'csrf_token' => $browser->csrfToken('/account/settings')]);
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_two_factor WHERE UID = 2 AND enabled_at IS NOT NULL'));
		static::assertSame(10, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_recovery_codes WHERE UID = 2 AND used_at IS NULL'), 'the code is not used up');
	}
}
