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
	/** the codes of the second factor of a user from all addresses: who has the password tries from many */
	public const int MAX_SECOND_FACTOR_FAILURES_PER_USER = 10;

	public function __construct(private CacheInterface $cache)
	{
	}

	/**
	 * The codes of the second factor count in the login and in the account together: per user and IP address like
	 * a password, and per user from all addresses. Who guesses them has the password already, so the user may be
	 * locked out for the window: the password login does not lock the user alone on purpose.
	 *
	 * @throws InvalidArgumentException
	 */
	public function isSecondFactorBlocked(int $UID, string $ip): bool
	{
		return $this->isBlocked(self::secondFactor($UID), $ip)
			|| $this->count(self::secondFactorUserKey($UID)) >= self::MAX_SECOND_FACTOR_FAILURES_PER_USER;
	}

	/**
	 * @return bool true when this failure locks the user on all addresses (for the audit log)
	 * @throws InvalidArgumentException
	 * @phpstan-impure it counts the failure, a check before is outdated afterwards
	 */
	public function registerSecondFactorFailure(int $UID, string $ip): bool
	{
		$this->registerFailure(self::secondFactor($UID), $ip);
		$key   = self::secondFactorUserKey($UID);
		$count = $this->count($key) + 1;
		$this->cache->set($key, $count, self::LOCK_WINDOW_SECONDS);

		return $count === self::MAX_SECOND_FACTOR_FAILURES_PER_USER;
	}

	/**
	 * A valid code ends the lock of this address. The count of the user stays until the window ends: the valid code
	 * of the user must not give the one who guesses new tries.
	 *
	 * @throws InvalidArgumentException
	 */
	public function resetSecondFactor(int $UID, string $ip): void
	{
		$this->reset(self::secondFactor($UID), $ip);
	}

	private static function secondFactor(int $UID): string
	{
		return 'two_factor_' . $UID;
	}

	private static function secondFactorUserKey(int $UID): string
	{
		return 'two_factor_fail_user_' . $UID;
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
