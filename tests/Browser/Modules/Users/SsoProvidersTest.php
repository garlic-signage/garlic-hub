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

namespace Tests\Browser\Modules\Users;

use Facebook\WebDriver\WebDriverExpectedCondition;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The admin deletes an SSO provider only after the question.
 */
#[Group('browser')]
class SsoProvidersTest extends BrowserTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->connection->insert('sso_providers', ['provider_id' => 3, 'name' => 'Google', 'issuer' => 'https://accounts.google.com', 'client_id' => 'the-client', 'client_secret' => '']);
	}

	public function testDeletingAsksFirst(): void
	{
		$this->login('admin');
		$this->visit('/users/sso/edit/3');
		$this->waitForVisible('#delete');
		$driver = $this->client()->getWebDriver();

		$this->click('#delete');
		$driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
		$driver->switchTo()->alert()->dismiss();
		static::assertStringEndsWith('/users/sso/edit/3', $driver->getCurrentURL());
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));

		$this->click('#delete');
		$driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
		$driver->switchTo()->alert()->accept();
		$driver->wait(5)->until(fn() => str_ends_with($driver->getCurrentURL(), '/users/sso'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));

		$this->assertNoErrors();
	}
}
