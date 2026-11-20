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

namespace App\Modules\Auth\Repositories;

use App\Modules\Auth\LoginAttempts;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * The counters of the login throttle in the database. One statement raises a counter and checks its limit:
 * SQLite writes one after the other, so parallel requests count every attempt.
 */
readonly class LoginAttemptsRepository implements LoginAttempts
{
	private const string TABLE = 'login_attempts';

	public function __construct(private Connection $connection, private ClockInterface $clock) {}

	/**
	 * @param array<string,int> $limits
	 * @return array<string,int>|null
	 * @throws Throwable
	 */
	public function add(array $limits, int $window): ?array
	{
		$now = $this->clock->now()->getTimestamp();
		// the first statement writes: the transaction waits for the lock instead of failing on a read lock
		$this->connection->beginTransaction();
		try
		{
			$this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE expires_at <= ?', [$now]);

			$counts = [];
			foreach ($limits as $key => $limit)
			{
				// a key at its limit gives no row: nothing of this attempt counts
				$count = $this->connection->fetchOne(
					'INSERT INTO ' . self::TABLE . ' (attempt_key, attempts, expires_at) VALUES (?, 1, ?)
					ON CONFLICT (attempt_key) DO UPDATE SET attempts = attempts + 1, expires_at = excluded.expires_at
					WHERE attempts < ?
					RETURNING attempts',
					[$key, $now + $window, $limit]
				);
				if ($count === false)
				{
					$this->connection->rollBack();
					return null;
				}
				// @infection-ignore-all the cast is for databases which give text
				$counts[$key] = (int) $count;
			}
			$this->connection->commit();

			return $counts;
		}
		catch (Throwable $e)
		{
			$this->connection->rollBack();
			throw $e;
		}
	}

	/**
	 * @throws Exception
	 */
	public function remove(string ...$keys): void
	{
		foreach ($keys as $key)
			$this->connection->executeStatement('UPDATE ' . self::TABLE . ' SET attempts = attempts - 1 WHERE attempt_key = ? AND attempts > 0', [$key]);
	}

	/**
	 * @throws Exception
	 */
	public function delete(string $key): void
	{
		$this->connection->delete(self::TABLE, ['attempt_key' => $key]);
	}

	/**
	 * @throws Exception
	 */
	public function count(string $key): int
	{
		$count = $this->connection->fetchOne('SELECT attempts FROM ' . self::TABLE . ' WHERE attempt_key = ? AND expires_at > ?',
			[$key, $this->clock->now()->getTimestamp()]);

		// @infection-ignore-all the cast is for databases which give text
		return is_numeric($count) ? (int) $count : 0;
	}
}
