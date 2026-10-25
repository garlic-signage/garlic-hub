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

use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;

/**
 * In-memory replacement of the cache. It shares nothing between tests and can be switched to fail like a cache which is down.
 */
class MemoryCache extends Psr16Adapter
{
	/** @var array<string,mixed> */
	public array $store = [];
	public bool $down = false;

	public function __construct()
	{
		parent::__construct('Devnull');
	}

	public function get(string $key, mixed $default = null): mixed
	{
		if ($this->down)
			throw new PhpfastcacheSimpleCacheException('cache down');

		return $this->store[$key] ?? $default;
	}

	public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
	{
		if ($this->down)
			throw new PhpfastcacheSimpleCacheException('cache down');

		$this->store[$key] = $value;

		return true;
	}
}
