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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SerializationTest extends TestCase
{
    #[Group('units')]
    public function testUnSerializeSecureSucceed(): void
    {
        $expected = ['a', 'b', 'c', 'd'];
        $result   = Serialization::unserializeSecure(serialize($expected));
        static::assertEquals($expected, $result);
    }

    #[Group('units')]
    public function testUnSerializeSecureFails(): void
    {
        $result   = Serialization::unserializeSecure('');
        static::assertEquals([], $result);
    }

    #[Group('units')]
    public function testUnSerializeSecureWithNullAndNoArray(): void
    {
        static::assertSame([], Serialization::unserializeSecure(null));
        static::assertSame([], Serialization::unserializeSecure(serialize('text')));
        static::assertSame([], Serialization::unserializeSecure('no serialized data'));
    }

    /**
     * Stored data must never create objects (PHP object injection), not even nested ones.
     */
    #[Group('units')]
    public function testUnSerializeSecureCreatesNoObjects(): void
    {
        $result = Serialization::unserializeSecure(serialize(['item' => new \ArrayObject([1, 2])]));

        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $result['item']);
    }

}
