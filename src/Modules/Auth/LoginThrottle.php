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

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Limits failed login attempts to slow down password guessing.
 *
 * Failures are counted per combination of username and IP address and additionally per IP address.
 * The username alone is not blocked on purpose, otherwise anybody could lock out an admin by
 * sending wrong passwords. Every failure restarts the lock window.
 *
 * Behind a reverse proxy REMOTE_ADDR is the proxy, so the IP limit is rather high.
 */
readonly class LoginThrottle
{
	public const int MAX_FAILURES_PER_USER_AND_IP = 5;
	public const int MAX_FAILURES_PER_IP          = 30;
	public const int LOCK_WINDOW_SECONDS          = 900;

	public function __construct(private CacheInterface $cache)
	{
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function isBlocked(string $identifier, string $ip): bool
	{
		return $this->count($this->userKey($identifier, $ip)) >= self::MAX_FAILURES_PER_USER_AND_IP
			|| $this->count($this->ipKey($ip)) >= self::MAX_FAILURES_PER_IP;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function registerFailure(string $identifier, string $ip): void
	{
		foreach ([$this->userKey($identifier, $ip), $this->ipKey($ip)] as $key)
			$this->cache->set($key, $this->count($key) + 1, self::LOCK_WINDOW_SECONDS);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function reset(string $identifier, string $ip): void
	{
		$this->cache->delete($this->userKey($identifier, $ip));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function count(string $key): int
	{
		$value = $this->cache->get($key, 0);

		return is_int($value) ? $value : 0;
	}

	private function userKey(string $identifier, string $ip): string
	{
		return 'login_fail_user_' . hash('sha256', strtolower(trim($identifier)) . '|' . $ip);
	}

	private function ipKey(string $ip): string
	{
		return 'login_fail_ip_' . hash('sha256', $ip);
	}
}
