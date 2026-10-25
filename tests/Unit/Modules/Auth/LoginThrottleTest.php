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
}
