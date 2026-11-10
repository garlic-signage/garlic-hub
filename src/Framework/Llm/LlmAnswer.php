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

namespace App\Framework\Llm;

/**
 * What the helpers read from the answer of a model. The model is not trusted: it may think aloud, add text around
 * the JSON, repeat itself, choose numbers which were not offered or send far more than asked.
 */
final class LlmAnswer
{
	public const int MAX_TERM_LENGTH = 50;
	private const int MIN_TERM_LENGTH = 2;

	/**
	 * The answer without the thoughts which reasoning models of a local server put in front of it.
	 */
	public static function text(string $answer): string
	{
		return trim((string) preg_replace('#<think>.*?</think>#s', '', $answer));
	}

	/**
	 * @return array<mixed>
	 * @throws LlmException without a JSON object or list
	 */
	public static function json(string $answer): array
	{
		$decoded = json_decode(self::text($answer), true);
		if (!is_array($decoded))
			throw new LlmException('llm_no_answer');

		return $decoded;
	}

	/**
	 * Search terms: texts of two characters at least, cut to MAX_TERM_LENGTH, each once (capitals do not count),
	 * at most $max.
	 *
	 * @return list<string>
	 * @throws LlmException when no term is left
	 */
	public static function terms(mixed $terms, int $max): array
	{
		$found = [];
		$seen  = [];
		foreach (is_array($terms) ? $terms : [] as $term)
		{
			if (!is_string($term))
				continue;

			$term = mb_substr(trim($term), 0, self::MAX_TERM_LENGTH);
			$key  = mb_strtolower($term);
			if (mb_strlen($term) < self::MIN_TERM_LENGTH || isset($seen[$key]))
				continue;

			$seen[$key] = true;
			$found[]    = $term;
		}
		if ($found === [])
			throw new LlmException('llm_no_answer');

		return array_slice($found, 0, $max);
	}

	/**
	 * The candidates the model chose by their numbers (1 is the first), as indexes: only offered ones, each once,
	 * in the order of the model, at most $max.
	 *
	 * @return list<int>
	 */
	public static function chosen(mixed $numbers, int $candidates, int $max = PHP_INT_MAX): array
	{
		$chosen = [];
		foreach (is_array($numbers) ? $numbers : [] as $number)
		{
			if (is_string($number) && ctype_digit($number))
				$number = (int) $number;
			if (!is_int($number) || $number < 1 || $number > $candidates || in_array($number - 1, $chosen, true))
				continue;

			$chosen[] = $number - 1;
		}

		return array_slice($chosen, 0, $max);
	}
}
