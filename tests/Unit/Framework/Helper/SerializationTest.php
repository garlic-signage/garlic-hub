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

namespace Tests\Unit\Framework\Helper;

use App\Framework\Helper\Serialization;
use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use __PHP_Incomplete_Class;

class SerializationTest extends TestCase
{
	#[Group('units')]
	public function testUnserializeSecureReturnsTheArray(): void
	{
		static::assertSame(['a', 'b', 'c', 'd'], Serialization::unserializeSecure(serialize(['a', 'b', 'c', 'd'])));
	}

	#[Group('units')]
	public function testUnserializeSecureKeepsKeysAndNestedArrays(): void
	{
		$data = ['zone' => ['x' => 1, 'y' => [2, 3]], 'name' => 'Lobby', 5 => true];

		static::assertSame($data, Serialization::unserializeSecure(serialize($data)));
	}

	#[Group('units')]
	public function testUnserializeSecureOfEmptyStringAndNullIsAnEmptyArray(): void
	{
		static::assertSame([], Serialization::unserializeSecure(''));
		static::assertSame([], Serialization::unserializeSecure(null));
	}

	#[Group('units')]
	#[DataProvider('noArrayProvider')]
	public function testUnserializeSecureOfAnythingButAnArrayIsAnEmptyArray(string $value): void
	{
		static::assertSame([], Serialization::unserializeSecure($value));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function noArrayProvider(): array
	{
		return [
			'string'            => [serialize('text')],
			'integer'           => [serialize(5)],
			'false'             => [serialize(false)],
			'no serialized data' => ['no serialized data'],
			'truncated array'   => ['a:1:{i:0;'],
		];
	}

	/**
	 * Stored data must never create objects (PHP object injection), not even nested ones.
	 */
	#[Group('units')]
	public function testUnserializeSecureCreatesNoObjects(): void
	{
		$result = Serialization::unserializeSecure(serialize(['item' => new ArrayObject([1, 2])]));

		static::assertInstanceOf(__PHP_Incomplete_Class::class, $result['item']);
	}

	#[Group('units')]
	public function testUnserializeSecureOfAnObjectIsAnEmptyArray(): void
	{
		static::assertSame([], Serialization::unserializeSecure(serialize(new ArrayObject([1, 2]))));
	}
}
