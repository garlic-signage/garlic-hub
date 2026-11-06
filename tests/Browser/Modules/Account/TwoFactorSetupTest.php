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

namespace Tests\Browser\Modules\Account;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Facebook\WebDriver\WebDriverBy;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;
use Tests\Support\FixedClock;

/**
 * The account with its tabs: anna opens the tab of the second factor, scans (reads) the key and turns it on.
 */
#[Group('browser')]
class TwoFactorSetupTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	private function codeOf(int $UID): string
	{
		$key    = Key::loadFromAsciiSafeString((string) file_get_contents($this->deployment()->directory() . '/var/keys/encryption.key'));
		$secret = Crypto::decrypt((string) $this->connection->fetchOne('SELECT secret FROM user_two_factor WHERE UID = ?', [$UID]), $key);
		static::assertNotSame('', $secret);

		return TOTP::createFromSecret($secret, new FixedClock(time()))->at(time());
	}

	public function testTheTabOfTheSecondFactorTurnsItOnAndShowsTheRecoveryCodes(): void
	{
		$this->login('anna');
		$this->visit('/account/settings');
		// the password is the first tab
		$this->waitForVisible('#accountSettings .tab-content[data-module="password"] #password');

		$this->click('#accountSettings .tab-button[data-tab="two_factor"]');
		$this->waitForVisible('#accountSettings .two-factor-qr-code svg');
		static::assertMatchesRegularExpression('/^[A-Z2-7]{4}( [A-Z2-7]{1,4})+$/', $this->text('.two-factor-secret'));

		$driver = $this->client()->getWebDriver();
		$driver->findElement(WebDriverBy::id('two_factor_code'))->sendKeys($this->codeOf(2));
		$this->click('.two-factor form button[type="submit"]');

		// back on the tab of the second factor, with the codes
		$this->waitForVisible('.two-factor-codes');
		static::assertCount(10, $driver->findElements(WebDriverBy::cssSelector('.two-factor-codes code')));
		static::assertNotSame('', $this->text('.two-factor-enabled'));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_two_factor WHERE UID = 2 AND enabled_at IS NOT NULL'));

		$this->assertNoErrors();
	}
}
