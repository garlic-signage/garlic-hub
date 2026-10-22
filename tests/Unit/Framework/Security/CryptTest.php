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

namespace Tests\Unit\Framework\Security;

use App\Framework\Security\Crypt;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class CryptTest extends TestCase
{

	private String $testkey = 'def00000c3cfd8b3bbd0317e9283e4f77afdf78f506c38c5f500a15817ea0ac6588daf39685118a3fec8997e4fe6dc2cd23dc5ba434885a4bd63966ed53ec7a510984595';
	private Crypt $crypt;

	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$key = Key::loadFromAsciiSafeString($this->testkey);

		$this->crypt = new Crypt($key);
	}

	#[Group('units')]
	public function testGetEncryptionKey(): void
	{
		 $expected = Key::loadFromAsciiSafeString($this->testkey);
		 $actual = $this->crypt->getEncryptionKey();

		 static::assertEquals($expected, $actual);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateRandomString(): void
	{
		$length = 64;
		$randomString = $this->crypt->generateRandomString($length / 2);

		static::assertEquals($length, strlen($randomString));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateRandomStringWithZeroLength(): void
	{
		$length = 64;
		$randomString = $this->crypt->generateRandomString(0);

		static::assertEquals($length, strlen($randomString));
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGeneratePassword(): void
	{
		$length = 8;
		$password = $this->crypt->generatePassword($length);

		static::assertEquals($length, strlen($password));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateRandomNumber(): void
	{
		$places = 5;
		$randomNumber = $this->crypt->generateRandomNumber($places);

		static::assertGreaterThanOrEqual(10000, $randomNumber);
		static::assertLessThanOrEqual(99999, $randomNumber);
	}

	#[Group('units')]
	public function testCreatePasswordHashAndCheckPassword(): void
	{
		$password = 'securePassword123!';
		$hash = $this->crypt->createPasswordHash($password);

		static::assertTrue($this->crypt->checkPassword($password, $hash));
		static::assertFalse($this->crypt->checkPassword('wrongPassword', $hash));
	}

	#[Group('units')]
	public function testCreateSha256Hash(): void
	{
		$input = 'test';
		$hash = $this->crypt->createSha256Hash($input);

		static::assertEquals(64, strlen($hash)); // SHA-256 creates 64 chars
	}

	#[Group('units')]
	public function testCreateMd5Hash(): void
	{
		$input = 'test';
		$hash = $this->crypt->createMd5Hash($input);

		static::assertEquals(32, strlen($hash)); // md5 creates 32 chars
	}

	#[Group('units')]
	public function testCreateCrc32vHash(): void
	{
		$input = 'test';
		$hash = $this->crypt->createCrc32bHash($input);

		static::assertGreaterThan(0, strlen($hash));
	}
}
