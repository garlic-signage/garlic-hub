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

/**
 * Limits failed login attempts to slow down password guessing.
 *
 * Attempts are counted per combination of username and IP address and additionally per IP address.
 * The username alone is not blocked on purpose, otherwise anybody could lock out an admin by
 * sending wrong passwords. Every attempt restarts the lock window.
 *
 * An attempt counts before the password is checked: parallel requests must not all pass a limit which none of them
 * raised yet. A right password takes its attempt back.
 *
 * The address is the one of the visitor (ClientIp), also behind a trusted reverse proxy.
 */
readonly class LoginThrottle
{
	public const int MAX_FAILURES_PER_USER_AND_IP = 5;
	public const int MAX_FAILURES_PER_IP          = 30;
	public const int LOCK_WINDOW_SECONDS          = 900;
	/** the codes of the second factor of a user from all addresses: who has the password tries from many */
	public const int MAX_SECOND_FACTOR_FAILURES_PER_USER = 10;

	public function __construct(private LoginAttempts $attempts)
	{
	}

	/**
	 * Counts the attempt before the password is checked.
	 *
	 * @return bool false when the user or the address is blocked: then the attempt does not count
	 * @phpstan-impure it counts the attempt
	 */
	public function attempt(string $identifier, string $ip): bool
	{
		return $this->attempts->add($this->limits($identifier, $ip), self::LOCK_WINDOW_SECONDS) !== null;
	}

	/**
	 * A right password frees the user, but not the address: a guesser who knows one password must not reset the
	 * counter of the address with it. Only the attempt of the right password is taken back there.
	 */
	public function succeeded(string $identifier, string $ip): void
	{
		$this->attempts->delete($this->userKey($identifier, $ip));
		$this->attempts->remove($this->ipKey($ip));
	}

	public function isBlocked(string $identifier, string $ip): bool
	{
		return $this->attempts->count($this->userKey($identifier, $ip)) >= self::MAX_FAILURES_PER_USER_AND_IP
			|| $this->attempts->count($this->ipKey($ip)) >= self::MAX_FAILURES_PER_IP;
	}

	/**
	 * The codes of the second factor count in the login and in the account together: per user and IP address like
	 * a password, and per user from all addresses. Who guesses them has the password already, so the user may be
	 * locked out for the window: the password login does not lock the user alone on purpose.
	 *
	 * @return int the number of this attempt of the user on all addresses, 0 when blocked: then it does not count
	 * @phpstan-impure it counts the attempt
	 */
	public function attemptSecondFactor(int $UID, string $ip): int
	{
		$userKey = self::secondFactorUserKey($UID);
		$counts  = $this->attempts->add(
			$this->limits(self::secondFactor($UID), $ip) + [$userKey => self::MAX_SECOND_FACTOR_FAILURES_PER_USER],
			self::LOCK_WINDOW_SECONDS
		);

		return $counts[$userKey] ?? 0;
	}

	/**
	 * Whether the failure of this attempt locks the user on all addresses (for the audit log): only the one which
	 * reaches the limit, the following attempts are blocked before.
	 */
	public function locksSecondFactor(int $attempt): bool
	{
		return $attempt === self::MAX_SECOND_FACTOR_FAILURES_PER_USER;
	}

	/**
	 * A valid code ends the lock of this address. The failures of the user stay until the window ends: the valid
	 * code of the user must not give the one who guesses new tries. Only the attempt of the valid code is taken back.
	 */
	public function succeededSecondFactor(int $UID, string $ip): void
	{
		$this->attempts->delete($this->userKey(self::secondFactor($UID), $ip));
		$this->attempts->remove($this->ipKey($ip), self::secondFactorUserKey($UID));
	}

	public function isSecondFactorBlocked(int $UID, string $ip): bool
	{
		return $this->isBlocked(self::secondFactor($UID), $ip)
			|| $this->attempts->count(self::secondFactorUserKey($UID)) >= self::MAX_SECOND_FACTOR_FAILURES_PER_USER;
	}

	/**
	 * @return array<string,int>
	 */
	private function limits(string $identifier, string $ip): array
	{
		return [
			$this->userKey($identifier, $ip) => self::MAX_FAILURES_PER_USER_AND_IP,
			$this->ipKey($ip)                => self::MAX_FAILURES_PER_IP
		];
	}

	private static function secondFactor(int $UID): string
	{
		// @infection-ignore-all any order of the parts gives a key of its own
		return 'two_factor_' . $UID;
	}

	private static function secondFactorUserKey(int $UID): string
	{
		// @infection-ignore-all any order of the parts gives a key of its own
		return 'two_factor_fail_user_' . $UID;
	}

	private function userKey(string $identifier, string $ip): string
	{
		return 'login_fail_user_' . hash('sha256', strtolower(trim($identifier)) . '|' . $ip);
	}

	private function ipKey(string $ip): string
	{
		// @infection-ignore-all the prefix only separates it from the other keys, which are hashes, too
		return 'login_fail_ip_' . hash('sha256', $ip);
	}
}
