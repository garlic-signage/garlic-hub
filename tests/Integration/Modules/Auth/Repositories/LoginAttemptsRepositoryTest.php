<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Modules\Auth\Repositories;

use App\Modules\Auth\Repositories\LoginAttemptsRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;
use Tests\Support\FixedClock;

/**
 * The counters of the login throttle in the database: limit, window and the attempt which counts on all keys or none.
 */
#[Group('db')]
class LoginAttemptsRepositoryTest extends RepositoryTestCase
{
	private const int WINDOW = 900;

	private FixedClock $clock;
	private LoginAttemptsRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->clock      = new FixedClock(1_800_000_000);
		$this->repository = new LoginAttemptsRepository($this->connection, $this->clock);
	}

	public function testAnAttemptCountsOnEveryKey(): void
	{
		static::assertSame(['a' => 1, 'b' => 1], $this->repository->add(['a' => 3, 'b' => 3], self::WINDOW));
		static::assertSame(['a' => 2, 'c' => 1], $this->repository->add(['a' => 3, 'c' => 3], self::WINDOW));

		static::assertSame([2, 1, 1, 0], [$this->repository->count('a'), $this->repository->count('b'), $this->repository->count('c'), $this->repository->count('d')]);
	}

	/**
	 * A key at its limit refuses the attempt: the other keys keep their counts, nothing is left open.
	 */
	public function testAKeyAtItsLimitCountsNothing(): void
	{
		$this->repository->add(['a' => 1], self::WINDOW);

		static::assertNull($this->repository->add(['b' => 5, 'a' => 1], self::WINDOW));
		static::assertSame([1, 0], [$this->repository->count('a'), $this->repository->count('b')]);
		static::assertFalse($this->connection->isTransactionActive());
	}

	public function testTheCountEndsAfterTheWindow(): void
	{
		$this->repository->add(['a' => 1], self::WINDOW);

		$this->clock->moveBy(self::WINDOW - 1);
		static::assertSame(1, $this->repository->count('a'));
		static::assertNull($this->repository->add(['a' => 1], self::WINDOW));

		$this->clock->moveBy(1);
		static::assertSame(0, $this->repository->count('a'));
		static::assertSame(['a' => 1], $this->repository->add(['a' => 1], self::WINDOW), 'it starts again');
	}

	/**
	 * Every counted attempt restarts the window: whoever keeps guessing stays locked. A refused one does not, so the
	 * lock ends.
	 */
	public function testACountedAttemptRestartsTheWindowARefusedOneNot(): void
	{
		$this->repository->add(['a' => 2], self::WINDOW);
		$this->clock->moveBy(self::WINDOW - 10);
		$this->repository->add(['a' => 2], self::WINDOW);
		$this->clock->moveBy(self::WINDOW - 10);
		static::assertSame(2, $this->repository->count('a'));

		static::assertNull($this->repository->add(['a' => 2], self::WINDOW));
		$this->clock->moveBy(10);
		static::assertSame(0, $this->repository->count('a'));
	}

	public function testExpiredCountsAreRemoved(): void
	{
		$this->repository->add(['a' => 1], self::WINDOW);
		$this->clock->moveBy(self::WINDOW);

		$this->repository->add(['b' => 1], self::WINDOW);

		static::assertSame(['b'], $this->connection->fetchFirstColumn('SELECT attempt_key FROM login_attempts'));
	}

	public function testRemoveTakesBackOneAttemptAndNeverGoesBelowZero(): void
	{
		$this->repository->add(['a' => 5, 'b' => 5], self::WINDOW);
		$this->repository->add(['a' => 5], self::WINDOW);

		$this->repository->remove('a', 'b');
		$this->repository->remove('b', 'unknown');

		static::assertSame([1, 0, 0], [$this->repository->count('a'), $this->repository->count('b'), $this->repository->count('unknown')]);
	}

	public function testDeleteEndsTheCountOfOneKey(): void
	{
		$this->repository->add(['a' => 5, 'b' => 5], self::WINDOW);

		$this->repository->delete('a');

		static::assertSame([0, 1], [$this->repository->count('a'), $this->repository->count('b')]);
	}
}
