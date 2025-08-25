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

namespace App\Framework\Core;

use Defuse\Crypto\Key;
use Exception;

/**
 * This class provides cryptographic functionality including generating
 * random strings, random numbers, password hashing, and creating hash
 * values for strings.
 */
readonly class Crypt
{
	private Key $encryptionKey;

	public function __construct(Key $encryptionKey)
	{
		$this->encryptionKey = $encryptionKey;
	}

	public function getEncryptionKey(): Key
	{
		return $this->encryptionKey;
	}

	/**
	 * @throws Exception
	 */
	public function generateRandomString(int $length = 32): string
	{
		return bin2hex($this->generateRandomBytes($length));
	}

	/**
	 * @throws Exception
	 */
	public function generateRandomBytes(int $length = 32): string
	{
		if ($length < 1)
			$length = 32;

		return random_bytes($length);
	}

	/**
	 * @throws Exception
	 */
	public function generatePassword(int $length = 8): string
	{
		$chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ123456789!@#$%*()_-=+;:,.?';
		$password = '';
		for ($i = 0; $i < $length; $i++)
		{
			$password .= $chars[random_int(0, strlen($chars) - 1)];
		}
		return $password;	}

	/**
	 * @throws Exception
	 */
	public function generateRandomNumber(int $places = 9): int
	{
		$min = 10 ** ($places - 1);
		$max = (10 ** $places) - 1;
		return random_int($min, $max);

	}

	public function checkPassword(string $clearText, string $hash): bool
	{
		// todo: implement password_needs_rehash and update this to userMain DB
		return password_verify($clearText, $hash);
	}

	public function createPasswordHash(string $clearText):string
	{
		return password_hash($clearText, PASSWORD_DEFAULT);
	}

	public function createSha256Hash(string $clearText): string
	{
		return hash('sha256', $clearText);
	}

	public function createCrc32bHash(string $clearText): string
	{
		return hash('crc32b', $clearText);
	}

	public function createMd5Hash(string $clearText): string
	{
		return hash('md5', $clearText);
	}
}