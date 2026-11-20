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

namespace App\Modules\Auth;

/**
 * The counters of the login throttle. Counting must be atomic: parallel requests must not all pass a limit
 * which none of them raised yet.
 */
interface LoginAttempts
{
	/**
	 * Counts one attempt on all keys together, each up to its limit. When a key reached its limit nothing counts,
	 * and the window does not start again.
	 *
	 * @param array<string,int> $limits the limit per key
	 * @param int $window seconds after the last attempt until the count of a key ends
	 * @return array<string,int>|null the counts with this attempt, null when a key reached its limit
	 */
	public function add(array $limits, int $window): ?array;

	/**
	 * Takes back one attempt of each key: it was no failure.
	 */
	public function remove(string ...$keys): void;

	public function delete(string $key): void;

	public function count(string $key): int;
}
