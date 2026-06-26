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

namespace App\Framework\Core;

use App\Framework\Exceptions\FrameworkException;
use DateTime;

/**
 * Manages the creation, retrieval, validation, and deletion of browser cookies,
 * with additional support for hashing and integrity verification of cookie data.
 */
class Cookie
{
	private Crypt $crypt;

	public function __construct(Crypt $crypt)
	{
		$this->crypt = $crypt;
	}

	/**
	 * @return array<string,string>|null
	 * @throws  FrameworkException
	 */
	public function getHashedCookie(string $cookieName): ?array
	{
		$payload = $this->getCookie($cookieName);

		if (is_null($payload))
			return null;

		return $this->validateAndUnpackContent($payload);
	}

	public function getCookie(string $cookieName): ?string
	{
		if (!array_key_exists($cookieName, $_COOKIE))
			return null;

		return $_COOKIE[$cookieName];
	}
	
	/**
	 * @throws FrameworkException
	 */
	public function createCookie(string $name, string $content, DateTime $expire): void
	{
		$expire  = $expire->getTimestamp();
		$result  = setcookie($name, $content, $expire, '/', '', false, true);

		if ($result === false)
			throw new FrameworkException('Cookie failed to set.');
	}

	public function deleteCookie(string $name): void
	{
		// easiest way to delete a cookie without knowing its details
		setcookie($name, '', time() - 3600, '/');
	}

	public function hasCookie(string $name): bool
	{
		return array_key_exists($name, $_COOKIE);
	}

	/**
	 * @return array<string,string>
	 * @throws  FrameworkException
	 */
	private function validateAndUnpackContent(string $raw_content): array
	{
		$data = @unserialize($raw_content);
		if (!is_array($data) || count($data) !== 2)
			throw new FrameworkException('Failed to unserialize content.');

		[$content, $checksum] = $data;

		if (!$this->crypt->verifyHmacSha256($content, $checksum))
			throw new FrameworkException('Possible cookie manipulation detected.');

		$ret =  @unserialize($content);
		if ($ret === false)
			return [];

		return $ret;
	}
}