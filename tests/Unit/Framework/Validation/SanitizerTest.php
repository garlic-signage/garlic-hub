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

namespace Tests\Unit\Framework\Validation;

use App\Framework\Validation\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
	private Sanitizer $sanitizer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sanitizer = new Sanitizer('<b><i><br>');
	}

	// ---------------------------------------------------------------- string

	#[Group('units')]
	public function testStringSanitizationDoesNotEscape(): void
	{
		static::assertSame('<script>alert(1)</script>', $this->sanitizer->string('<script>alert(1)</script>'));
		static::assertSame('Tom & "Jerry\'s"', $this->sanitizer->string('Tom & "Jerry\'s"'));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function stringProvider(): array
	{
		return [
			'surrounding whitespace'  => ["  Hello World \n\t ", 'Hello World'],
			'control characters'      => ["Hello\x00 World\x07", 'Hello World'],
			'all low control signs'   => ["a\x01\x02\x08\x0B\x0C\x0E\x1F\x7Fb", 'ab'],
			'inner line break stays'  => ["line1\nline2\ttab", "line1\nline2\ttab"],
			'carriage return stays'   => ["a\r\nb", "a\r\nb"],
			'invalid utf-8'           => ["a\xC3b", 'a?b'],
			'umlauts'                 => ['Müller Straße', 'Müller Straße'],
			'cjk and emoji'           => ['日本 😀', '日本 😀'],
			'empty'                   => ['', ''],
			'only whitespace'         => [" \n ", ''],
		];
	}

	#[Group('units')]
	#[DataProvider('stringProvider')]
	public function testStringNormalizes(string $input, string $expected): void
	{
		static::assertSame($expected, $this->sanitizer->string($input));
	}

	// ---------------------------------------------------------------- html

	#[Group('units')]
	public function testHtmlKeepsAllowedTagsAndRemovesTheOthers(): void
	{
		static::assertSame('<b>bold</b>', $this->sanitizer->html('<b>bold</b>'));
		static::assertSame('alert()', $this->sanitizer->html('<script>alert()</script>'));
		static::assertSame('<b>bold</b> and <i>italic</i><br>x', $this->sanitizer->html('<b>bold</b> and <i>italic</i><br><div>x</div>'));
		static::assertSame('', $this->sanitizer->html());
	}

	/**
	 * An allowed tag keeps no attribute: onclick, style or a javascript: link would run in the page.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function htmlAttributeProvider(): array
	{
		return [
			'event handler'                => ['<b onclick="alert(1)">x</b>', '<b>x</b>'],
			'event handler without quotes' => ['<i onmouseover=alert(1)>y</i>', '<i>y</i>'],
			'style and class'              => ['<b style="color:red" class="a">z</b>', '<b>z</b>'],
			'javascript link'              => ['<b href="javascript:alert(1)">z</b>', '<b>z</b>'],
			'single quotes'                => ["<b onclick='alert(1)'>z</b>", '<b>z</b>'],
			'a closing sign in a value'    => ['<b title="a>b" onclick="alert(1)">z</b>', '<b>z</b>'],
			'upper case tag'               => ['<B ONCLICK="x">up</B>', '<b>up</b>'],
			'self closing'                 => ['a<br />b<br/>c<br onclick="x">d', 'a<br/>b<br/>c<br>d'],
			'closing tag with space'       => ['<b>x</b  >', '<b>x</b>'],
			// strip_tags does not know such a tag as <b>, it removes it (the closing tag stays)
			'slash instead of a space'     => ['<b/onclick=alert(1)>x</b>', 'x</b>'],
			'line break before the handler' => ["<b\nonclick=alert(1)>x</b>", '<b>x</b>'],
			'tab before the handler'       => ["<i\tonclick=alert(1)>x</i>", '<i>x</i>'],
			'several tags'                 => ['<b onclick="1">a</b><i onfocus="2">b</i>', '<b>a</b><i>b</i>'],
			'a tag which is not allowed'   => ['<a href="x" onclick="y">link</a>', 'link'],
		];
	}

	#[Group('units')]
	#[DataProvider('htmlAttributeProvider')]
	public function testHtmlRemovesTheAttributesOfAllowedTags(string $input, string $expected): void
	{
		static::assertSame($expected, $this->sanitizer->html($input));
	}

	#[Group('units')]
	public function testHtmlWithoutAllowedTagsIsPlainText(): void
	{
		static::assertSame('boldscript()', (new Sanitizer())->html('<b onclick="x">bold</b><script>script()</script>'));
	}

	#[Group('units')]
	public function testHtmlLeavesTextWithoutTagsAlone(): void
	{
		static::assertSame('Tom & Jerry, 3 > 2', $this->sanitizer->html('Tom & Jerry, 3 > 2'));
	}

	// ---------------------------------------------------------------- numbers and bool

	/**
	 * @return array<string, array{string|int, int}>
	 */
	public static function intProvider(): array
	{
		return [
			'number with text'  => ['123abc', 123],
			'no number'         => ['abc', 0],
			'surrounding space' => [' 12 ', 12],
			'negative'          => ['-7', -7],
			'decimal part cut'  => ['12.9', 12],
			'exponent'          => ['1e3', 1000],
			'hex is no hex'     => ['0x1A', 0],
			'too big'           => ['99999999999999999999', PHP_INT_MAX],
			'an int'            => [42, 42],
			'empty'             => ['', 0],
		];
	}

	#[Group('units')]
	#[DataProvider('intProvider')]
	public function testIntSanitization(string|int $input, int $expected): void
	{
		static::assertSame($expected, $this->sanitizer->int($input));
	}

	#[Group('units')]
	public function testIntWithoutArgument(): void
	{
		static::assertSame(0, $this->sanitizer->int());
	}

	#[Group('units')]
	public function testFloatSanitization(): void
	{
		static::assertSame(12.34, $this->sanitizer->float('12.34abc'));
		static::assertSame(0.0, $this->sanitizer->float('abc'));
		static::assertSame(-1.5, $this->sanitizer->float('-1.5'));
		static::assertSame(3.0, $this->sanitizer->float(3.0));
		static::assertSame(0.0, $this->sanitizer->float());
	}

	/**
	 * @return array<string, array{string|bool, bool}>
	 */
	public static function boolProvider(): array
	{
		return [
			'text 1' => ['1', true], 'true' => ['true', true], 'TRUE' => ['TRUE', true], 'yes' => ['yes', true], 'on' => ['on', true],
			'text 0' => ['0', false], 'false' => ['false', false], 'off' => ['off', false], 'no' => ['no', false],
			'something else' => ['maybe', false], 'empty' => ['', false],
			'bool true' => [true, true], 'bool false' => [false, false],
		];
	}

	#[Group('units')]
	#[DataProvider('boolProvider')]
	public function testBoolSanitization(string|bool $input, bool $expected): void
	{
		static::assertSame($expected, $this->sanitizer->bool($input));
	}

	#[Group('units')]
	public function testBoolWithoutArgument(): void
	{
		static::assertFalse($this->sanitizer->bool());
	}

	// ---------------------------------------------------------------- arrays

	#[Group('units')]
	public function testStringArraySanitization(): void
	{
		static::assertSame(
			['<script>alert(1)</script>', 'Hello "World"'],
			$this->sanitizer->stringArray(['<script>alert(1)</script>', ' Hello "World" '])
		);
		static::assertSame(['foo', 'bar', 'baz'], $this->sanitizer->stringArray(['foo', 'bar', 'baz']));
		static::assertSame(['<div>', '&', '<br>'], $this->sanitizer->stringArray(['<div>', '&', '<br>']));
		static::assertSame([], $this->sanitizer->stringArray());
	}

	#[Group('units')]
	public function testStringArrayKeepsTheKeysAndNormalizesLikeString(): void
	{
		static::assertSame(['a' => 'x', 'b' => 'y'], $this->sanitizer->stringArray(['a' => " x\x00", 'b' => 'y ']));
		static::assertSame(['5', '1', ''], $this->sanitizer->stringArray([5, true, '']));
	}

	#[Group('units')]
	public function testStringArrayDropsNestedArrays(): void
	{
		static::assertSame([0 => 'a', 2 => 'c'], $this->sanitizer->stringArray(['a', ['nested'], 'c']));
	}

	#[Group('units')]
	public function testIntArraySanitization(): void
	{
		static::assertSame([123, 456, 789], $this->sanitizer->intArray([123, 456, 789]));
		static::assertSame([1, 0, 12], $this->sanitizer->intArray(['1', 'abc', '12x']));
		static::assertSame([0 => 1, 2 => 3], $this->sanitizer->intArray(['1', ['nested'], '3']));
		static::assertSame([], $this->sanitizer->intArray());
	}

	#[Group('units')]
	public function testFloatArraySanitization(): void
	{
		static::assertSame([12.34, 56.78, 90.12], $this->sanitizer->floatArray([12.34, 56.78, 90.12]));
		static::assertSame([1.5, 0.0], $this->sanitizer->floatArray(['1.5', 'abc']));
		static::assertSame([0 => 1.0, 2 => 3.0], $this->sanitizer->floatArray(['1', ['nested'], '3']));
		static::assertSame([], $this->sanitizer->floatArray());
	}

	// ---------------------------------------------------------------- json

	#[Group('units')]
	public function testJsonArraySanitization(): void
	{
		static::assertSame(['key' => 'value', 'number' => 123, 'bool' => true], $this->sanitizer->jsonArray('{"key": "value", "number": 123, "bool": true}'));
		static::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $this->sanitizer->jsonArray('[{"id": 1}, {"id": 2}, {"id": 3}]'));
		static::assertSame([], $this->sanitizer->jsonArray('[]'));
	}

	/**
	 * Only an object or a list counts as JSON data, everything else is an empty array.
	 *
	 * @return array<string, array{string}>
	 */
	public static function noJsonDataProvider(): array
	{
		return ['invalid' => ['invalid json'], 'null' => ['null'], 'cut off' => ['{"incomplete":'], 'empty' => [''], 'string' => ['"text"'], 'number' => ['5'], 'true' => ['true'], 'trailing comma' => ['{"a":1,}']];
	}

	#[Group('units')]
	#[DataProvider('noJsonDataProvider')]
	public function testJsonArrayOfNoDataIsEmpty(string $json): void
	{
		static::assertSame([], $this->sanitizer->jsonArray($json));
		static::assertSame([], $this->sanitizer->jsonHTML($json));
	}

	/**
	 * jsonHTML normalizes the strings of the data like string() does. It does not remove markup.
	 */
	#[Group('units')]
	public function testJsonHTMLSanitization(): void
	{
		static::assertSame(
			['<script>', '<div>Hello</div>', '"key"'],
			$this->sanitizer->jsonHTML('["<script>", " <div>Hello</div> ", "\\"key\\""]')
		);
		static::assertSame(
			['name' => 'a & b', 'count' => 3, 'nested' => ['x' => 'y'], 'flag' => true, 'none' => null],
			$this->sanitizer->jsonHTML('{"name": " a & b ", "count": 3, "nested": {"x": " y "}, "flag": true, "none": null}')
		);
		static::assertSame(['a' => 'b'], $this->sanitizer->jsonHTML('{"a": "b\\u0000"}'));
	}
}
