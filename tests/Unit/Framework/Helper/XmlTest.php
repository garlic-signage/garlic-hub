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

use App\Framework\Helper\Xml;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class XmlTest extends TestCase
{
	#[Group('units')]
	public function testEscapeEscapesAllXmlSpecialCharacters(): void
	{
		static::assertSame('&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&apos;s&lt;/a&gt;', Xml::escape('<a href="x">Tom & Jerry\'s</a>'));
	}

	#[Group('units')]
	public function testEscapeCastsScalarsAndNull(): void
	{
		static::assertSame('42', Xml::escape(42));
		static::assertSame('1.5', Xml::escape(1.5));
		static::assertSame('', Xml::escape(null));
	}

	#[Group('units')]
	public function testEscapeSubstitutesInvalidUtf8(): void
	{
		static::assertSame("\u{FFFD}", Xml::escape("\xC3"));
	}

	/**
	 * XML 1.0 does not allow these characters, not even as entity.
	 */
	#[Group('units')]
	public function testEscapeRemovesCharactersWhichAreNotAllowedInXml(): void
	{
		static::assertSame('abc', Xml::escape("a\x01b\x0Bc\x1F"));
		static::assertSame('ab', Xml::escape("a\u{FFFE}b"));
		static::assertSame('ab', Xml::escape("a\x00b"));
	}

	#[Group('units')]
	public function testEscapeKeepsTabNewlineCarriageReturnAndUnicode(): void
	{
		static::assertSame("a\tb\nc\rd", Xml::escape("a\tb\nc\rd"));
		static::assertSame('Grüße 日本 😀', Xml::escape('Grüße 日本 😀'));
	}

	#[Group('units')]
	public function testEscapedValueMakesAParsableAttributeAndTextNode(): void
	{
		$value = "Title \x0B with <\"special\"> & 'chars'\x01";

		$xml = simplexml_load_string('<r a="' . Xml::escape($value) . '">' . Xml::escape($value) . '</r>');

		static::assertNotFalse($xml);
		static::assertSame('Title  with <"special"> & \'chars\'', (string) $xml['a']);
		static::assertSame('Title  with <"special"> & \'chars\'', (string) $xml);
	}
}
