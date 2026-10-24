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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class CryptTest extends TestCase
{
	private const string TEST_KEY = 'def00000c3cfd8b3bbd0317e9283e4f77afdf78f506c38c5f500a15817ea0ac6588daf39685118a3fec8997e4fe6dc2cd23dc5ba434885a4bd63966ed53ec7a510984595';

	private Crypt $crypt;

	/**
	 * @throws EnvironmentIsBrokenException
	 * @throws BadFormatException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->crypt = new Crypt(Key::loadFromAsciiSafeString(self::TEST_KEY));
	}

	/**
	 * @throws EnvironmentIsBrokenException
	 */
	#[Group('units')]
	public function testGetEncryptionKey(): void
	{
		static::assertSame(self::TEST_KEY, $this->crypt->getEncryptionKey()->saveToAsciiSafeString());
	}

	/**
	 * The length is the number of random bytes, the string is their hex form: twice as long.
	 *
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('randomStringLengthProvider')]
	public function testGenerateRandomString(int $bytes, int $expectedLength): void
	{
		$randomString = $this->crypt->generateRandomString($bytes);

		static::assertSame($expectedLength, strlen($randomString));
		static::assertMatchesRegularExpression('/^[0-9a-f]+$/', $randomString);
	}

	/**
	 * @return array<string, array{int, int}>
	 */
	public static function randomStringLengthProvider(): array
	{
		return [
			'16 bytes'              => [16, 32],
			'32 bytes'              => [32, 64],
			'zero falls back to 32' => [0, 64],
			'negative falls back'   => [-5, 64],
		];
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateRandomBytes(): void
	{
		static::assertSame(16, strlen($this->crypt->generateRandomBytes(16)));
		static::assertSame(32, strlen($this->crypt->generateRandomBytes(0)));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRandomValuesDiffer(): void
	{
		static::assertNotSame($this->crypt->generateRandomString(), $this->crypt->generateRandomString());
		static::assertNotSame($this->crypt->generateRandomBytes(), $this->crypt->generateRandomBytes());
		static::assertNotSame($this->crypt->generatePassword(16), $this->crypt->generatePassword(16));
	}

	/**
	 * Long enough that every sign of the alphabet is expected to show up. Signs which are easy to
	 * confuse (0 O I l) are not part of it, nor whitespace or quotes.
	 *
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGeneratePasswordUsesOnlyTheAllowedSigns(): void
	{
		$password = $this->crypt->generatePassword(2000);

		static::assertSame(2000, strlen($password));
		static::assertMatchesRegularExpression('/^[a-km-zA-HJ-NP-Z1-9!@#$%*()_=+;:,.?-]+$/', $password);
		static::assertSame(0, preg_match('/[0OIl]/', $password));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGeneratePasswordDefaultLength(): void
	{
		static::assertSame(8, strlen($this->crypt->generatePassword()));
	}

	/**
	 * A password is never shorter than 8 signs.
	 *
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('passwordLengthProvider')]
	public function testGeneratePasswordHasAtLeastEightSigns(int $requested, int $expected): void
	{
		static::assertSame($expected, strlen($this->crypt->generatePassword($requested)));
	}

	/**
	 * @return array<string, array{int, int}>
	 */
	public static function passwordLengthProvider(): array
	{
		return [
			'negative'    => [-3, 8],
			'zero'        => [0, 8],
			'one'         => [1, 8],
			'seven'       => [7, 8],
			'eight'       => [8, 8],
			'nine'        => [9, 9],
			'twenty'      => [20, 20],
		];
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateRandomNumberHasTheGivenPlaces(): void
	{
		foreach ([1, 2, 5, 9, 18] as $places)
		{
			$number = $this->crypt->generateRandomNumber($places);

			static::assertSame($places, strlen((string) $number), "$places places");
			static::assertGreaterThanOrEqual(10 ** ($places - 1), $number);
			static::assertLessThan(10 ** $places, $number);
		}
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('invalidPlacesProvider')]
	public function testGenerateRandomNumberRejectsInvalidPlaces(int $places): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Places must be between 1 and 18.');

		$this->crypt->generateRandomNumber($places);
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function invalidPlacesProvider(): array
	{
		return ['zero' => [0], 'negative' => [-1], 'nineteen' => [19]];
	}

	#[Group('units')]
	public function testCreatePasswordHashAndCheckPassword(): void
	{
		$hash = $this->crypt->createPasswordHash('securePassword123!');

		static::assertTrue($this->crypt->checkPassword('securePassword123!', $hash));
		static::assertFalse($this->crypt->checkPassword('wrongPassword', $hash));
		static::assertFalse($this->crypt->checkPassword('', $hash));
		static::assertFalse($this->crypt->checkPassword('securePassword123!', 'no hash'));
		static::assertFalse($this->crypt->checkPassword('securePassword123!', ''));
	}

	#[Group('units')]
	public function testThePasswordHashIsSaltedAndNotTheClearText(): void
	{
		$first  = $this->crypt->createPasswordHash('securePassword123!');
		$second = $this->crypt->createPasswordHash('securePassword123!');

		static::assertNotSame($first, $second);
		static::assertStringNotContainsString('securePassword123!', $first);
		static::assertNotSame('unknown', password_get_info($first)['algoName']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateHmacSha256(): void
	{
		static::assertSame('6eff2b2d83487a7908796cb5049823e5ea56974f3a16d7fc7aa1c4505b2ef2ba', $this->crypt->createHmacSha256('hello'));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTheHmacDependsOnTheKey(): void
	{
		$other = new Crypt(Key::createNewRandomKey());

		static::assertNotSame($this->crypt->createHmacSha256('hello'), $other->createHmacSha256('hello'));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testVerifyHmacSha256(): void
	{
		$hmac = $this->crypt->createHmacSha256('hello');

		static::assertTrue($this->crypt->verifyHmacSha256('hello', $hmac));
		static::assertFalse($this->crypt->verifyHmacSha256('hello!', $hmac));
		static::assertFalse($this->crypt->verifyHmacSha256('hello', strtoupper($hmac)));
		static::assertFalse($this->crypt->verifyHmacSha256('hello', substr($hmac, 0, 63)));
		static::assertFalse($this->crypt->verifyHmacSha256('hello', ''));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testVerifyHmacSha256RejectsAnHmacOfAnotherKey(): void
	{
		$other = new Crypt(Key::createNewRandomKey());

		static::assertFalse($this->crypt->verifyHmacSha256('hello', $other->createHmacSha256('hello')));
	}

	#[Group('units')]
	public function testHashes(): void
	{
		static::assertSame('9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08', $this->crypt->createSha256Hash('test'));
		static::assertSame('098f6bcd4621d373cade4e832627b4f6', $this->crypt->createMd5Hash('test'));
		static::assertSame('d87f7e0c', $this->crypt->createCrc32bHash('test'));
	}
}
