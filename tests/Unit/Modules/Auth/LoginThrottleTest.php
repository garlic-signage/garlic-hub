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
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class LoginThrottleTest extends TestCase
{
	private LoginThrottle $throttle;

	protected function setUp(): void
	{
		$this->throttle = new LoginThrottle(new class implements CacheInterface {
			/** @var array<string,mixed> */
			private array $store = [];

			public function get(string $key, mixed $default = null): mixed { return $this->store[$key] ?? $default; }
			public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool { $this->store[$key] = $value; return true; }
			public function delete(string $key): bool { unset($this->store[$key]); return true; }
			public function clear(): bool { $this->store = []; return true; }
			/** @param iterable<string> $keys @return iterable<string,mixed> */
			public function getMultiple(iterable $keys, mixed $default = null): iterable { return []; }
			/** @param iterable<string,mixed> $values */
			public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool { return true; }
			/** @param iterable<string> $keys */
			public function deleteMultiple(iterable $keys): bool { return true; }
			public function has(string $key): bool { return isset($this->store[$key]); }
		});
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
}
