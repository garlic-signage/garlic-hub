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

namespace Tests\Support;

use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache in an array with its own clock: a test lets time pass with advance() instead of waiting.
 */
class ArrayCache implements CacheInterface
{
	/** @var array<string,array{value:mixed,expires:int|null}> */
	private array $store = [];
	private int $offset  = 0;

	public function advance(int $seconds): void
	{
		$this->offset += $seconds;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->has($key) ? $this->store[$key]['value'] : $default;
	}

	public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
	{
		if ($ttl instanceof \DateInterval)
			$ttl = (int) new \DateTimeImmutable('@0')->add($ttl)->format('U');

		$this->store[$key] = ['value' => $value, 'expires' => $ttl === null ? null : $this->now() + $ttl];

		return true;
	}

	public function delete(string $key): bool
	{
		unset($this->store[$key]);

		return true;
	}

	public function clear(): bool
	{
		$this->store = [];

		return true;
	}

	/**
	 * @param iterable<string> $keys
	 * @return iterable<string,mixed>
	 */
	public function getMultiple(iterable $keys, mixed $default = null): iterable
	{
		$result = [];
		foreach ($keys as $key)
			$result[$key] = $this->get($key, $default);

		return $result;
	}

	/**
	 * @param iterable<string,mixed> $values
	 */
	public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
	{
		foreach ($values as $key => $value)
			$this->set((string) $key, $value, $ttl);

		return true;
	}

	/**
	 * @param iterable<string> $keys
	 */
	public function deleteMultiple(iterable $keys): bool
	{
		foreach ($keys as $key)
			$this->delete($key);

		return true;
	}

	public function has(string $key): bool
	{
		if (!isset($this->store[$key]))
			return false;

		$expires = $this->store[$key]['expires'];

		return $expires === null || $expires > $this->now();
	}

	private function now(): int
	{
		return time() + $this->offset;
	}
}
