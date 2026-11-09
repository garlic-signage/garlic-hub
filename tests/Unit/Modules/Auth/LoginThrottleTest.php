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
use Psr\SimpleCache\InvalidArgumentException;
use Tests\Support\ArrayCache;

class LoginThrottleTest extends TestCase
{
	private ArrayCache $cache;
	private LoginThrottle $throttle;

	protected function setUp(): void
	{
		$this->cache    = new ArrayCache();
		$this->throttle = new LoginThrottle($this->cache);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testNotBlockedWithoutFailures(): void
	{
		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testBlocksAfterMaxFailuresForUserAndIp(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP - 1; $i++)
			$this->throttle->registerFailure('admin', '10.0.0.1');
		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));

		$this->throttle->registerFailure('admin', '10.0.0.1');
		static::assertTrue($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testOtherIpIsNotBlockedForSameUser(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure('admin', '10.0.0.1');

		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.2'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testUsernameIsCaseAndWhitespaceInsensitive(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure(' Admin ', '10.0.0.1');

		static::assertTrue($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testBlocksIpAfterTooManyFailuresWithDifferentUsers(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP; $i++)
			$this->throttle->registerFailure('user' . $i, '10.0.0.1');

		static::assertTrue($this->throttle->isBlocked('another', '10.0.0.1'));
		static::assertFalse($this->throttle->isBlocked('another', '10.0.0.2'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testResetClearsUserCounter(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure('admin', '10.0.0.1');

		$this->throttle->reset('admin', '10.0.0.1');

		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testTheLockEndsAfterTheWindow(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure('admin', '10.0.0.1');

		$this->cache->advance(LoginThrottle::LOCK_WINDOW_SECONDS - 1);
		static::assertTrue($this->throttle->isBlocked('admin', '10.0.0.1'));

		$this->cache->advance(2);
		static::assertFalse($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * Every failure restarts the window: whoever keeps guessing stays locked.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testAFurtherFailureRestartsTheWindow(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure('admin', '10.0.0.1');
		$this->cache->advance(LoginThrottle::LOCK_WINDOW_SECONDS - 10);

		$this->throttle->registerFailure('admin', '10.0.0.1');
		$this->cache->advance(LoginThrottle::LOCK_WINDOW_SECONDS - 10);

		static::assertTrue($this->throttle->isBlocked('admin', '10.0.0.1'));
	}

	/**
	 * A right password frees the user, but not the address: a guesser who knows one password
	 * must not reset the counter of the address with it.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testResetKeepsTheCounterOfTheAddress(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_IP; $i++)
			$this->throttle->registerFailure('user' . $i, '10.0.0.1');

		$this->throttle->reset('user0', '10.0.0.1');

		static::assertTrue($this->throttle->isBlocked('user0', '10.0.0.1'));
	}

	/**
	 * Codes of the second factor from many addresses: who has the password tries from a botnet.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testTheSecondFactorOfAUserIsLockedOnAllAddresses(): void
	{
		$locked = [];
		for ($i = 1; $i <= LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
		{
			static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.' . $i));
			$locked[] = $this->throttle->registerSecondFactorFailure(7, '10.0.0.' . $i);
		}

		static::assertSame([false, false, false, false, false, false, false, false, false, true], $locked, 'the lock is reported once');
		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '192.168.1.1'));
		static::assertFalse($this->throttle->isSecondFactorBlocked(8, '192.168.1.1'), 'another user');
		static::assertFalse($this->throttle->isBlocked('7', '192.168.1.1'), 'the password login is not locked');
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testFromOneAddressTheSecondFactorIsLockedEarlier(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerSecondFactorFailure(7, '10.0.0.1');

		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
		static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.2'));
	}

	/**
	 * Name and address are kept apart in the key: "anna1" from 1.2.3.4 is not "anna" from 11.2.3.4.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testNameAndAddressDoNotRunIntoEachOther(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerFailure('anna1', '1.2.3.4');

		static::assertTrue($this->throttle->isBlocked('anna1', '1.2.3.4'));
		static::assertFalse($this->throttle->isBlocked('anna', '11.2.3.4'));
	}

	/**
	 * Wrong codes of one user lock only him on this address: the others behind it (an office) go on.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testWrongCodesOfAUserDoNotLockAnotherOneOnTheSameAddress(): void
	{
		for ($i = 0; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerSecondFactorFailure(7, '10.0.0.1');

		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
		static::assertFalse($this->throttle->isSecondFactorBlocked(8, '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testAValidCodeStartsTheCountOfTheAddressAgain(): void
	{
		for ($i = 1; $i < LoginThrottle::MAX_FAILURES_PER_USER_AND_IP; $i++)
			$this->throttle->registerSecondFactorFailure(7, '10.0.0.1');
		$this->throttle->resetSecondFactor(7, '10.0.0.1');

		$this->throttle->registerSecondFactorFailure(7, '10.0.0.1');

		static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
	}

	/**
	 * The valid code frees the address, but the count of the user stays: the user who logs in meanwhile must not
	 * give the one who guesses new tries.
	 *
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testAValidCodeKeepsTheCountOfTheUser(): void
	{
		for ($i = 1; $i < LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
			$this->throttle->registerSecondFactorFailure(7, '10.0.0.' . $i);
		$this->throttle->resetSecondFactor(7, '10.0.0.1');

		$this->throttle->registerSecondFactorFailure(7, '10.0.0.99');

		static::assertTrue($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	#[Group('units')]
	public function testTheLockOfTheSecondFactorEndsAfterTheWindow(): void
	{
		for ($i = 1; $i <= LoginThrottle::MAX_SECOND_FACTOR_FAILURES_PER_USER; $i++)
			$this->throttle->registerSecondFactorFailure(7, '10.0.0.' . $i);

		$this->cache->advance(LoginThrottle::LOCK_WINDOW_SECONDS + 1);

		static::assertFalse($this->throttle->isSecondFactorBlocked(7, '10.0.0.1'));
	}
}
