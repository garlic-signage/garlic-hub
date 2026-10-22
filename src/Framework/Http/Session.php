<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Http;


use App\Framework\Exceptions\FrameworkException;

/**
 * Let's ignore a single responsibility here and put session management and
 * storage in one class.
 */
class Session
{
	public function __construct(string $sessionName = 'GhSid')
	{
		session_name($sessionName);
	}

	/**
	 * @param array<string,mixed> $options
	 * @return void
	 */
	public function start(array $options = []): void
	{
		$defaultOptions = [
			'cookie_lifetime' => 0, // to end when browser closed
			'cookie_path' => '/',
			'cookie_domain' => '',
			'cookie_secure' => false,
			'cookie_httponly' => true,
			'cookie_samesite' => 'Strict',
		];

		$settings = array_merge($defaultOptions, $options);

		session_start($settings);
	}

	/**
	 * @throws FrameworkException
	 */
	public function regenerateID(): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
			throw new FrameworkException('Session not active for regenerating.');

		session_regenerate_id(true);
		$sessionName = session_name();
		if ($sessionName === false)
			throw new FrameworkException('Session name failed for regenerating.');

		$sessionId = session_id();
		if ($sessionId === false)
			throw new FrameworkException('Session Id failed for regenerating.');

		$cookieLifetime = ini_get('session.cookie_lifetime');
		if ($cookieLifetime === false)
			throw new FrameworkException('Cookie lifetime failed for regenerating.');

		setcookie($sessionName, $sessionId, (int) $cookieLifetime, '/');
	}


	/**
	 * @return string|array<string,mixed>|null
	 */
	public function get(string $key): null|string|array
	{
		return $this->exists($key) ? $_SESSION[$key] : null;
	}

	/**
	 * @param string|array<string,mixed> $value
	 */
	public function set(string $key, string|array $value): void
	{
		$_SESSION[$key] = $value;
	}

	public function delete(string $key): void
	{
		if ($this->exists($key))
			unset($_SESSION[$key]);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getSession(): array
	{
		return $_SESSION;
	}

	public function clear(): void
	{
		$_SESSION = [];
	}

	public function exists(string $key): bool
	{
		return array_key_exists($key, $_SESSION);
	}

	public function id(bool $new = false): false|string
	{
		if ($new && session_id())
			session_regenerate_id(true);

		return session_id() ?: '';
	}
}