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
		static::assertSame('[]', Json::encodeForScript([]));
	}

	#[Group('units')]
	public function testEncodeForScriptCannotCloseScriptTag(): void
	{
		$result = Json::encodeForScript(['x' => '</script><b>Tom & Jerry\'s</b>']);

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
}
