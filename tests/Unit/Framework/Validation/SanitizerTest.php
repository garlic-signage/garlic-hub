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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
	private Sanitizer $sanitizer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sanitizer = new Sanitizer('<b><i>');
	}

	#[Group('units')]
	public function testStringSanitizationDoesNotEscape(): void
	{
		static::assertSame('<script>alert(1)</script>', $this->sanitizer->string('<script>alert(1)</script>'));
		static::assertSame('Tom & "Jerry\'s"', $this->sanitizer->string('Tom & "Jerry\'s"'));
	}

	#[Group('units')]
	public function testStringSanitizationNormalizes(): void
	{
		static::assertSame('Hello World', $this->sanitizer->string("  Hello\x00 World\x07\n "));
		static::assertSame('a?b', $this->sanitizer->string("a\xC3b"));
		static::assertSame('Müller', $this->sanitizer->string('Müller'));
	}

	#[Group('units')]
	public function testHtmlSanitization(): void
	{
		static::assertSame('<b>bold</b>', $this->sanitizer->html('<b>bold</b>'));
		static::assertSame('alert()', $this->sanitizer->html('<script>alert()</script>'));
	}

	#[Group('units')]
	public function testIntSanitization(): void
	{
		static::assertSame(123, $this->sanitizer->int('123abc'));
		static::assertSame(0, $this->sanitizer->int('abc'));
	}

	#[Group('units')]
	public function testFloatSanitization(): void
	{
		static::assertSame(12.34, $this->sanitizer->float('12.34abc'));
		static::assertSame(0.0, $this->sanitizer->float('abc'));
	}

	#[Group('units')]
	public function testBoolSanitization(): void
	{
		static::assertTrue($this->sanitizer->bool('1'));
		static::assertFalse($this->sanitizer->bool());
		static::assertTrue($this->sanitizer->bool('true'));
		static::assertFalse($this->sanitizer->bool('false'));
		static::assertFalse($this->sanitizer->bool('0'));
		static::assertFalse($this->sanitizer->bool('off'));
	}

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
	public function testStringArrayDropsNestedArrays(): void
	{
		static::assertSame([0 => 'a', 2 => 'c'], $this->sanitizer->stringArray(['a', ['nested'], 'c']));
	}

	#[Group('units')]
	public function testIntArraySanitization(): void
	{
		static::assertSame([123, 456, 789], $this->sanitizer->intArray([123, 456, 789]));
		static::assertSame([1, 0, 12], $this->sanitizer->intArray(['1', 'abc', '12x']));
		static::assertSame([], $this->sanitizer->intArray());
	}

	#[Group('units')]
	public function testFloatArraySanitization(): void
	{
		static::assertSame([12.34, 56.78, 90.12], $this->sanitizer->floatArray([12.34, 56.78, 90.12]));
		static::assertSame([1.5, 0.0], $this->sanitizer->floatArray(['1.5', 'abc']));
		static::assertSame([], $this->sanitizer->floatArray());
	}

	#[Group('units')]
	public function testJsonArraySanitization(): void
	{
		static::assertSame(
			['key' => 'value', 'number' => 123, 'bool' => true],
			$this->sanitizer->jsonArray('{"key": "value", "number": 123, "bool": true}')
		);

		static::assertSame(
			[['id' => 1], ['id' => 2], ['id' => 3]],
			$this->sanitizer->jsonArray('[{"id": 1}, {"id": 2}, {"id": 3}]')
		);

		static::assertSame([], $this->sanitizer->jsonArray('invalid json'));

		static::assertSame([], $this->sanitizer->jsonArray('null'));

		static::assertSame([], $this->sanitizer->jsonArray('{"incomplete":'));

		static::assertSame([], $this->sanitizer->jsonArray(''));
	}

	#[Group('units')]
	public function testJsonHTMLSanitization(): void
	{
		static::assertSame(
			['<script>', '<div>Hello</div>', '"key"'],
			$this->sanitizer->jsonHTML('["<script>", " <div>Hello</div> ", "\\"key\\""]')
		);

		static::assertSame(
			['name' => 'a & b', 'count' => 3, 'nested' => ['x' => 'y']],
			$this->sanitizer->jsonHTML('{"name": "a & b", "count": 3, "nested": {"x": " y "}}')
		);

		static::assertSame([], $this->sanitizer->jsonHTML('invalid json'));
		static::assertSame([], $this->sanitizer->jsonHTML('{"incomplete":'));
		static::assertSame([], $this->sanitizer->jsonHTML(''));
	}
}
