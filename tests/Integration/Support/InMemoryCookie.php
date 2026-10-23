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

namespace Tests\Integration\Support;

use App\Framework\Http\Cookie;
use DateTime;

/**
 * Cookies without HTTP: what the application "sets" is kept in memory, so a test can read it,
 * hand it back as the next request or delete it.
 */
class InMemoryCookie extends Cookie
{
	/** @var array<string,array{value:string,expire:int}> */
	private array $sent = [];

	public function getCookie(string $cookieName): ?string
	{
		return $_COOKIE[$cookieName] ?? null;
	}

	public function createCookie(string $name, string $content, DateTime $expire): void
	{
		$this->sent[$name] = ['value' => $content, 'expire' => $expire->getTimestamp()];
		$_COOKIE[$name]    = $content;
	}

	public function deleteCookie(string $name): void
	{
		unset($this->sent[$name], $_COOKIE[$name]);
	}

	public function hasCookie(string $name): bool
	{
		return array_key_exists($name, $_COOKIE);
	}

	/**
	 * The expire timestamp of the cookie the application has sent, null if it sent none.
	 */
	public function sentExpire(string $name): ?int
	{
		return $this->sent[$name]['expire'] ?? null;
	}
}
