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

namespace Tests\Unit\Framework\Helper;

use App\Framework\Helper\Json;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
	#[Group('units')]
	public function testEncodeForScriptReturnsObjectLiteral(): void
	{
		static::assertSame('{"a":"Hello","b":"Grüße"}', Json::encodeForScript(['a' => 'Hello', 'b' => 'Grüße']));
	}

	#[Group('units')]
	public function testEncodeForScriptOfEmptyDataIsAnEmptyObject(): void
	{
		static::assertSame('{}', Json::encodeForScript([]));
	}

	#[Group('units')]
	public function testEncodeForScriptKeepsNestedEmptyArraysAndLists(): void
	{
		static::assertSame('{"a":[],"b":[1,2]}', Json::encodeForScript(['a' => [], 'b' => [1, 2]]));
	}

	#[Group('units')]
	public function testEncodeForScriptCannotCloseScriptTag(): void
	{
		$result = Json::encodeForScript(['x' => '</script><b>Tom & Jerry\'s</b>']);

		static::assertSame('{"x":"\u003C\/script\u003E\u003Cb\u003ETom \u0026 Jerry\u0027s\u003C\/b\u003E"}', $result);
		static::assertStringNotContainsString('<', $result);
		static::assertStringNotContainsString('>', $result);
		static::assertStringNotContainsString('&', $result);
		static::assertStringNotContainsString("'", $result);
		static::assertSame(['x' => '</script><b>Tom & Jerry\'s</b>'], json_decode($result, true));
	}

	#[Group('units')]
	public function testEncodeForScriptEscapesDoubleQuotesAndBackslashes(): void
	{
		$result = Json::encodeForScript(['x' => 'say "hi" \\ bye']);

		static::assertSame('{"x":"say \"hi\" \\\\ bye"}', $result);
		static::assertSame(['x' => 'say "hi" \\ bye'], json_decode($result, true));
	}

	#[Group('units')]
	public function testEncodeForScriptSubstitutesInvalidUtf8(): void
	{
		static::assertSame(['x' => "a\u{FFFD}b"], json_decode(Json::encodeForScript(['x' => "a\xC3b"]), true));
	}

	/**
	 * U+2028 and U+2029 end a line in JavaScript and break a string literal in older engines.
	 */
	#[Group('units')]
	public function testEncodeForScriptEscapesLineSeparators(): void
	{
		$result = Json::encodeForScript(['x' => "a\u{2028}b\u{2029}c"]);

		static::assertSame('{"x":"a\u2028b\u2029c"}', $result);
	}

	#[Group('units')]
	public function testEncodeForScriptFallsBackToAnEmptyObjectForDataWhichCanNotBeEncoded(): void
	{
		static::assertSame('{}', Json::encodeForScript(['x' => NAN]));
	}
}
