<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Llm;

use App\Framework\Llm\LlmAnswer;
use App\Framework\Llm\LlmException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class LlmAnswerTest extends TestCase
{
	#[Group('units')]
	public function testTheThoughtsAndTheSpacesAroundAreRemoved(): void
	{
		static::assertSame("Der Text\nzwei", LlmAnswer::text("<think>erst\ndenken</think>\n  Der Text\nzwei \n"));
		static::assertSame('a  b', LlmAnswer::text('<think>x</think>a <think>y</think> b'));
	}

	#[Group('units')]
	public function testTheJsonAfterTheThoughts(): void
	{
		static::assertSame(['terms' => ['Haus']], LlmAnswer::json("<think>\n{\"no\": 1}\n</think>\n {\"terms\": [\"Haus\"]} "));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function noJson(): array
	{
		return ['text' => ['Gerne, hier ist es'], 'empty' => [''], 'a number' => ['5'], 'only thoughts' => ['<think>{}</think>']];
	}

	#[Group('units')]
	#[DataProvider('noJson')]
	public function testAnAnswerWithoutJsonIsNoAnswer(string $answer): void
	{
		$this->expectException(LlmException::class);
		$this->expectExceptionMessage('llm_no_answer');

		LlmAnswer::json($answer);
	}

	#[Group('units')]
	public function testTheTermsAreTextsOfTwoCharactersAtLeastEachOnce(): void
	{
		$terms = LlmAnswer::terms([' Haus ', 'x', 'ä', 'Öl', 5, null, ['Baum'], 'HAUS', 'öl', 'Baum'], 10);

		static::assertSame(['Haus', 'Öl', 'Baum'], $terms);
	}

	#[Group('units')]
	public function testALongTermIsCutByCharacters(): void
	{
		$terms = LlmAnswer::terms([str_repeat('ü', LlmAnswer::MAX_TERM_LENGTH + 5), ' ' . str_repeat('a', LlmAnswer::MAX_TERM_LENGTH)], 10);

		static::assertSame([str_repeat('ü', LlmAnswer::MAX_TERM_LENGTH), str_repeat('a', LlmAnswer::MAX_TERM_LENGTH)], $terms);
	}

	#[Group('units')]
	public function testAtMostTheAskedNumberOfTerms(): void
	{
		static::assertSame(['eins', 'zwei'], LlmAnswer::terms(['eins', 'zwei', 'drei'], 2));
	}

	/**
	 * @return array<string,array{mixed}>
	 */
	public static function noTerms(): array
	{
		return ['missing' => [null], 'a text' => ['Haus'], 'only short ones' => [['a', ' b ', '']]];
	}

	#[Group('units')]
	#[DataProvider('noTerms')]
	public function testWithoutATermThereIsNoAnswer(mixed $terms): void
	{
		$this->expectException(LlmException::class);
		$this->expectExceptionMessage('llm_no_answer');

		LlmAnswer::terms($terms, 5);
	}

	#[Group('units')]
	public function testTheChosenAreTheOfferedNumbersAsIndexesEachOnceInTheOrderOfTheModel(): void
	{
		static::assertSame([2, 0, 1], LlmAnswer::chosen([3, '1', 0, 4, -1, '2', 3, 'x', '1.5', 2.0, null], 3));
	}

	#[Group('units')]
	public function testAtMostTheAskedNumberOfChosen(): void
	{
		static::assertSame([1, 0], LlmAnswer::chosen([2, 1, 3], 3, 2));
		static::assertSame([], LlmAnswer::chosen('1', 3), 'not a list');
	}
}
