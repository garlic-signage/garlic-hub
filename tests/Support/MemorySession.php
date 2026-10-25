<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Http\Session;

/**
 * A session which lives in an array: no PHP session, no cookie, nothing shared with other tests.
 */
class MemorySession extends Session
{
	/** @var array<string,mixed> */
	public array $data = [];
	/** how often a new session id was requested: the real one needs a PHP session */
	public int $regenerations = 0;

	public function regenerateID(): void
	{
		$this->regenerations++;
	}

	public function get(string $key): null|string|array
	{
		$value = $this->data[$key] ?? null;

		return is_string($value) || is_array($value) ? $value : null;
	}

	public function set(string $key, string|array $value): void
	{
		$this->data[$key] = $value;
	}

	public function delete(string $key): void
	{
		unset($this->data[$key]);
	}

	public function exists(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	public function getSession(): array
	{
		return $this->data;
	}

	public function clear(): void
	{
		$this->data = [];
	}
}
