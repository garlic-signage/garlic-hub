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

namespace Tests\Unit\Framework\Controller;

use App\Framework\Controller\ReadsInput;
use App\Framework\Controller\RequestInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class RequestInputTest extends TestCase
{
	/**
	 * @return array<string,array{mixed}>
	 */
	public static function bodiesWhichAreNoArrays(): array
	{
		return ['nothing' => [null], 'a text' => ['x=1'], 'a number' => [5], 'false' => [false]];
	}

	#[DataProvider('bodiesWhichAreNoArrays')]
	#[Group('units')]
	public function testABodyWhichIsNoArrayIsEmpty(mixed $body): void
	{
		$input = RequestInput::of($body);

		static::assertSame([], $input->all());
		static::assertSame('', $input->text('x'));
		static::assertSame(0, $input->id('x'));
	}

	#[Group('units')]
	public function testTheBodyIsGivenBack(): void
	{
		static::assertSame(['a' => 'x', 'b' => ['y']], RequestInput::of(['a' => 'x', 'b' => ['y']])->all());
	}

	#[Group('units')]
	public function testATextIsOnlyATextAndNothingElse(): void
	{
		$input = new RequestInput(['text' => 'abc', 'empty' => '', 'number' => 5, 'array' => ['x'], 'null' => null, 'bool' => true]);

		static::assertSame('abc', $input->text('text'));
		static::assertSame('', $input->text('empty'));
		foreach (['number', 'array', 'null', 'bool', 'missing'] as $key)
			static::assertSame('', $input->text($key), $key);
	}

	#[Group('units')]
	public function testAnIdOfAMediaOrAPlaylistIsTextOrANumber(): void
	{
		$input = new RequestInput(['media' => 'abc-123', 'playlist' => 3, 'form' => '3', 'float' => 1.5, 'array' => [3], 'null' => null]);

		static::assertSame('abc-123', $input->scalarText('media'));
		static::assertSame('3', $input->scalarText('playlist'));
		static::assertSame('3', $input->scalarText('form'));
		foreach (['float', 'array', 'null', 'missing'] as $key)
			static::assertSame('', $input->scalarText($key), $key);
	}

	/**
	 * @return array<string,array{mixed,bool}>
	 */
	public static function ids(): array
	{
		return [
			'a number'              => [5, true],
			'zero'                  => [0, true],
			'a number as text'      => ['5', true],
			'zero as text'          => ['0', true],
			'a negative number'     => [-1, false],
			'a negative as text'    => ['-1', false],
			'text after the number' => ['1abc', false],
			'a decimal'             => ['1.5', false],
			'a float'               => [1.0, false],
			'a space'               => [' 1', false],
			'an empty text'         => ['', false],
			'an array'              => [[1], false],
			'null'                  => [null, false],
			'true'                  => [true, false],
		];
	}

	#[DataProvider('ids')]
	#[Group('units')]
	public function testWhatIsAnId(mixed $value, bool $isId): void
	{
		$input = new RequestInput(['id' => $value]);

		static::assertSame($isId, $input->hasId('id'));
		static::assertSame($isId ? (int) $value : 0, $input->id('id'));
		static::assertSame($isId ? (int) $value : null, $input->optionalInt('id'));
	}

	#[Group('units')]
	public function testAMissingIdIsNoId(): void
	{
		$input = new RequestInput([]);

		static::assertFalse($input->hasId('id'));
		static::assertSame(0, $input->id('id'));
		static::assertNull($input->optionalInt('id'));
	}

	#[Group('units')]
	public function testAListOfIds(): void
	{
		$input = new RequestInput(['ids' => ['5', 3, '0'], 'empty' => [], 'text' => 'abc', 'bad' => ['1', 'x'], 'nested' => [['1']], 'negative' => ['-1']]);

		static::assertSame([5, 3, 0], $input->idList('ids'));
		static::assertSame([], $input->idList('empty'));
		foreach (['text', 'bad', 'nested', 'negative', 'missing'] as $key)
			static::assertNull($input->idList($key), $key);
	}

	/**
	 * @return array<string,array{mixed,float|null}>
	 */
	public static function numbers(): array
	{
		return [
			'an int' => [12, 12.0], 'a float' => [12.6, 12.6], 'zero' => [0, 0.0], 'a number as text' => ['12.6', 12.6], 'zero as text' => ['0', 0.0],
			'negative' => [-5, null], 'negative as text' => ['-5', null], 'text' => ['abc', null], 'a number with text' => ['1e', null], 'an empty text' => ['', null], 'an array' => [['1'], null], 'null' => [null, null],
		];
	}

	#[DataProvider('numbers')]
	#[Group('units')]
	public function testANumberWhichIsNotNegative(mixed $value, ?float $expected): void
	{
		static::assertSame($expected, new RequestInput(['n' => $value])->number('n'));
	}

	#[Group('units')]
	public function testTheOrchestratorsReadTheInputWithTheTrait(): void
	{
		$reader = new class
		{
			use ReadsInput;

			public function read(): string
			{
				return $this->text('name') . '|' . $this->scalarText('ref') . '|' . $this->id('id') . '|' . var_export($this->hasId('id'), true) . '|' . var_export($this->optionalInt('other'), true)
					. '|' . implode(',', $this->idList('ids') ?? []) . '|' . var_export($this->number('n'), true);
			}
		};

		$reader->setInput(['name' => 'Lobby', 'ref' => 7, 'id' => '4', 'ids' => ['1', '2'], 'n' => '2.5']);

		static::assertSame('Lobby|7|4|true|NULL|1,2|2.5', $reader->read());
		static::assertSame('||0|false|NULL||NULL', $reader->setInput(['name' => ['x'], 'id' => ['4']])->read());
	}
}
