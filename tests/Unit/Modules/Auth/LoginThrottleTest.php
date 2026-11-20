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

use App\Modules\Auth\LoginThrottle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemoryLoginAttempts;

/**
 * The limits and keys of the throttle. The counters in the database with the window: LoginAttemptsRepositoryTest.
 */
#[Group('units')]
class LoginThrottleTest extends TestCase
{
	private MemoryLoginAttempts $attempts;
	private LoginThrottle $throttle;

	protected function setUp(): void
	{
		$this->attempts = new MemoryLoginAttempts();
		$this->throttle = new LoginThrottle($this->attempts);
	}

	private function attempts(string $identifier, string $ip, int $count): void
	{
		for ($i = 0; $i < $count; $i++)
			static::assertTrue($this->throttle->attempt($identifier, $ip));
	}

	public function testNotBlockedWithoutAttempts(): void
	{
		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * The attempt counts before the password is checked: the one after the limit is refused at once.
	 */
	public function testBlocksAfterMaxAttemptsForUserAndIp(): void
	{
		$this->attempts('admin', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_USER_AND_IP - 1);
		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));

		static::assertTrue($this->throttle->attempt('admin', '10.0.0.1'));
		static::assertTrue($this->throttle->isBlocked('admin', '10.0.0.1'));
		static::assertFalse($this->throttle->attempt('admin', '10.0.0.1'));
	}

	public function testOtherIpIsNotBlockedForSameUser(): void
	{
		$this->attempts('admin', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_USER_AND_IP);

		static::assertTrue($this->throttle->attempt('admin', '10.0.0.2'));
	}

	public function testUsernameIsCaseAndWhitespaceInsensitive(): void
	{
		$this->attempts(' Admin ', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_USER_AND_IP);

		static::assertFalse($this->throttle->attempt('admin', '10.0.0.1'));
	}

	public function testBlocksIpAfterTooManyAttemptsWithDifferentUsers(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP; $i++)
			static::assertTrue($this->throttle->attempt('user' . $i, '10.0.0.1'));

		static::assertFalse($this->throttle->attempt('another', '10.0.0.1'));
		static::assertTrue($this->throttle->attempt('another', '10.0.0.2'));
	}

	/**
	 * A refused attempt counts nowhere: the blocked address does not raise the count of the user.
	 */
	public function testABlockedAttemptCountsNothing(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP; $i++)
			$this->throttle->attempt('user' . $i, '10.0.0.1');

		static::assertFalse($this->throttle->attempt('another', '10.0.0.1'));
		static::assertSame(0, $this->attempts->count('login_fail_user_' . hash('sha256', 'another|10.0.0.1')));
	}

	public function testARightPasswordFreesTheUser(): void
	{
		$this->attempts('admin', '10.0.0.1', LoginThrottle::MAX_FAILURES_PER_USER_AND_IP);

		$this->throttle->succeeded('admin', '10.0.0.1');

		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * A right password frees the user, but not the address: a guesser who knows one password must not reset the
	 * counter of the address with it. Only its own attempt is taken back.
	 */
	public function testARightPasswordKeepsTheFailuresOfTheAddress(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP - 1; $i++)
			$this->throttle->attempt('user' . $i, '10.0.0.1');
		$this->throttle->attempt('anna', '10.0.0.1');

		$this->throttle->succeeded('anna', '10.0.0.1');

		static::assertSame(LoginThrottle::MAX_FAILURES_PER_IP - 1, $this->attempts->count('login_fail_ip_' . hash('sha256', '10.0.0.1')));
		static::assertTrue($this->throttle->attempt('anna', '10.0.0.1'));
		static::assertFalse($this->throttle->attempt('anna', '10.0.0.1'));
	}

	/**
	 * Name and address are kept apart in the key: "anna1" from 1.2.3.4 is not "anna" from 11.2.3.4.
	 */
	public function testNameAndAddressDoNotRunIntoEachOther(): void
	{
		$this->attempts('anna1', '1.2.3.4', LoginThrottle::MAX_FAILURES_PER_USER_AND_IP);

		static::assertTrue($this->throttle->isBlocked('anna1', '1.2.3.4'));
		static::assertFalse($this->throttle->isBlocked('anna', '11.2.3.4'));
	}

	/**
	 * Codes of the second factor from many addresses: who has the password tries from a botnet.
	 */
	public function testTheSecondFactorOfAUserIsLockedOnAllAddresses(): void
	{
		$locks = [];
		for ($i = 1; $i <= LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
		{
			$attempt = $this->throttle->attemptSecondFactor(7, '10.0.0.' . $i);
			static::assertSame($i, $attempt);
			$locks[] = $this->throttle->locksSecondFactor($attempt);
		}

		static::assertSame([false, false, false, false, false, false, false, false, false, true], $locks, 'the lock is reported once');
		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '192.168.1.1'));
		static::assertSame(0, $this->throttle->attemptSecondFactor(7, '192.168.1.1'));
		static::assertSame(1, $this->throttle->attemptSecondFactor(8, '192.168.1.1'), 'another user');
		static::assertFalse($this->throttle->isBlocked('7', '192.168.1.1'), 'the password login is not locked');
	}

	public function testFromOneAddressTheSecondFactorIsLockedEarlier(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->attemptSecondFactor(7, '10.0.0.1');

		static::assertSame(0, $this->throttle->attemptSecondFactor(7, '10.0.0.1'));
		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
		static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.2'));
	}

	/**
	 * Wrong codes of one user lock only him on this address: the others behind it (an office) go on.
	 */
	public function testWrongCodesOfAUserDoNotLockAnotherOneOnTheSameAddress(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->attemptSecondFactor(7, '10.0.0.1');

		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
		static::assertFalse($this->throttle->isSecondFactorBlocked(8, '10.0.0.1'));
	}

	public function testAValidCodeStartsTheCountOfTheAddressAgain(): void
	{
		for ($i = 1; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->attemptSecondFactor(7, '10.0.0.1');
		$this->throttle->attemptSecondFactor(7, '10.0.0.1');
		$this->throttle->succeededSecondFactor(7, '10.0.0.1');

		$this->throttle->attemptSecondFactor(7, '10.0.0.1');

		static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
	}

	/**
	 * The valid code frees the address, but the failures of the user stay: the user who logs in meanwhile must not
	 * give the one who guesses new tries. Only the attempt of the valid code is taken back.
	 */
	public function testAValidCodeKeepsTheFailuresOfTheUser(): void
	{
		for ($i = 1; $i < LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
			$this->throttle->attemptSecondFactor(7, '10.0.0.' . $i);
		$this->throttle->attemptSecondFactor(7, '10.0.0.1');
		$this->throttle->succeededSecondFactor(7, '10.0.0.1');

		static::assertSame(LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER, $this->throttle->attemptSecondFactor(7, '10.0.0.99'));
		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
	}
}
