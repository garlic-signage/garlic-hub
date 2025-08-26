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

namespace App\Modules\Auth;

use App\Framework\Core\Session;
use App\Framework\Exceptions\UserException;

/**
 * Handles user-related session operations by interacting with a session object.
 * Provides methods to retrieve the user's unique ID, username, and locale from the session.
 * Throws exceptions if the user data is not available or invalid in the session.
 */
readonly class UserSession
{
	private Session $session;

	public function __construct(Session $session)
	{
		$this->session = $session;
	}

	/**
	 * @throws UserException
	 */
	public function getUID(): int
	{
		$user = $this->checkUser();

		return $user['UID'];
	}

	/**
	 * @throws UserException
	 */
	public function getUsername(): string
	{
		$user = $this->checkUser();
		return $user['username'];
	}

	/**
	 * @throws UserException
	 */
	public function getLocales(): string
	{
		$user = $this->checkUser();
		return $user['locale'];
	}

	/**
	 * @return array{UID:int, username:string, locale:string}
	 * @throws UserException
	 */
	private function checkUser(): array
	{
		/** @var array{UID?:int, username?:string, locale?:string}|null $user */
		$user = $this->session->get('user');
		if (!is_array($user) || !isset($user['UID'], $user['username'], $user['locale']))
			throw new UserException('User not found in session.');

		return $user;
	}


}