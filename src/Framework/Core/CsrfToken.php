<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


namespace App\Framework\Core;

use Exception;

class CsrfToken
{
	const string CSRF_TOKEN_SESSION_KEY = 'csrf_token';
	const int CSRF_TOKEN_LENGTH = 32;
	private readonly Session $session;
	private readonly Crypt $crypt;
	private string $token = '';

	public function __construct(Crypt $crypt, Session $session)
	{
		$this->crypt = $crypt;
		$this->session = $session;

		$sessionToken = $this->session->get(self::CSRF_TOKEN_SESSION_KEY);
		$this->token = is_string($sessionToken) ? $sessionToken : '';
	}

	public function getToken(): string
	{
		if ($this->token === '')
			$this->generateToken();

		return $this->token;
	}

	public function validateToken(string $receivedToken): bool
	{
		$sessionToken = $this->session->get(self::CSRF_TOKEN_SESSION_KEY);

		if (!is_string($sessionToken) || $sessionToken === '')
			return false;

		return hash_equals($sessionToken, $receivedToken);
	}

	/**
	 * @throws Exception
	 */
	public function generateToken(): void
	{
		$this->token = $this->crypt->generateRandomString(self::CSRF_TOKEN_LENGTH);
		$this->session->set(self::CSRF_TOKEN_SESSION_KEY, $this->token);
	}

	public function destroyToken(): void
	{
		$this->session->delete(self::CSRF_TOKEN_SESSION_KEY);
		$this->token = '';
	}
}