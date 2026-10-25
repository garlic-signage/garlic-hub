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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class UserSessionTest extends TestCase
{
	private MemorySession $session;
	private UserSession $userSession;

	protected function setUp(): void
	{
		parent::setUp();
		$this->session     = new MemorySession();
		$this->userSession = new UserSession($this->session);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testTheLoggedInUserIsReadFromTheSession(): void
	{
		$this->session->set('user', ['UID' => 7, 'username' => 'heidi', 'locale' => 'de_DE', 'status' => 3]);

		static::assertSame(7, $this->userSession->getUID());
		static::assertSame('heidi', $this->userSession->getUsername());
		static::assertSame('de_DE', $this->userSession->getLocales());
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function noUserProvider(): array
	{
		return [
			'nobody logged in'  => [null],
			'UID missing'       => [['username' => 'heidi', 'locale' => 'de_DE']],
			'username missing'  => [['UID' => 7, 'locale' => 'de_DE']],
			'locale missing'    => [['UID' => 7, 'username' => 'heidi']],
			'UID is null'       => [['UID' => null, 'username' => 'heidi', 'locale' => 'de_DE']],
			'empty array'       => [[]],
			'a string'          => ['heidi'],
		];
	}

	/**
	 * Whoever acts on behalf of a user needs all three entries: an incomplete one is no user.
	 *
	 * @throws UserException
	 */
	#[Group('units')]
	#[DataProvider('noUserProvider')]
	public function testAnIncompleteUserIsNoUserForAnyGetter(mixed $sessionUser): void
	{
		if ($sessionUser !== null)
			$this->session->set('user', $sessionUser);

		foreach (['getUID', 'getUsername', 'getLocales'] as $getter)
		{
			try
			{
				$this->userSession->$getter();
				static::fail($getter . ' must throw for ' . json_encode($sessionUser));
			}
			catch (UserException $e)
			{
				static::assertSame('User not found in session.', $e->getMessage());
			}
		}
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testALogoutIsSeenAtOnce(): void
	{
		$this->session->set('user', ['UID' => 7, 'username' => 'heidi', 'locale' => 'de_DE']);
		$this->session->clear();

		$this->expectException(UserException::class);
		$this->userSession->getUID();
	}
}
