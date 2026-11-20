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

namespace Tests\Support;

use App\Modules\Auth\LoginAttempts;

/**
 * The counters of the login throttle in memory, without the window: the database version is tested on its own
 * (LoginAttemptsRepositoryTest).
 */
final class MemoryLoginAttempts implements LoginAttempts
{
	/** @var array<string,int> */
	private array $counts = [];

	/**
	 * @param array<string,int> $limits
	 * @return array<string,int>|null
	 */
	public function add(array $limits, int $window): ?array
	{
		foreach ($limits as $key => $limit)
		{
			if ($this->count($key) >= $limit)
				return null;
		}
		foreach (array_keys($limits) as $key)
			$this->counts[$key] = $this->count($key) + 1;

		return array_intersect_key($this->counts, $limits);
	}

	public function remove(string ...$keys): void
	{
		foreach ($keys as $key)
			$this->counts[$key] = max(0, $this->count($key) - 1);
	}

	public function delete(string $key): void
	{
		unset($this->counts[$key]);
	}

	public function count(string $key): int
	{
		return $this->counts[$key] ?? 0;
	}
}
