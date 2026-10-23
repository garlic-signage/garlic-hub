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

namespace Tests\Integration\Support;

use App\Framework\Http\Session;
use App\Framework\Localization\Locales\Locales;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Container\ContainerInterface;

/**
 * Base class for tests which use the real services of the application, wired by the real
 * container, on a database built by the real migrations.
 *
 * PHPUnit does not inherit groups from a parent class: every concrete test class needs its
 * own group, e.g. #[Group('acl')].
 */
abstract class ContainerTestCase extends DatabaseTestCase
{
	protected ContainerInterface $container;

	protected function setUp(): void
	{
		parent::setUp();

		$_SESSION        = [];
		$_COOKIE         = [];
		$this->container = TestContainerFactory::create($this->connection, sys_get_temp_dir() . '/garlic-hub-tests');
		$this->container->get(Psr16Adapter::class)->clear();
	}

	protected function tearDown(): void
	{
		$_SESSION = [];
		$_COOKIE  = [];
		unset($_SERVER['REMOTE_ADDR']);
		parent::tearDown();
	}

	/**
	 * Logs in as the user without going through the login: the session holds what the
	 * login would have written.
	 */
	protected function actingAs(int $UID): void
	{
		$user = $this->connection->fetchAssociative('SELECT UID, username, locale FROM user_main WHERE UID = ?', [$UID]);
		if ($user === false)
			self::fail('No user ' . $UID . ' in the test database.');

		$this->container->get(Session::class)->set('user', $user);
		// the LocaleMiddleware does this in the application
		$this->container->get(Locales::class)->determineCurrentLocale();
	}
}
