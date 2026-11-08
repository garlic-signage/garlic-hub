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

namespace App\Shared\Llm;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;

/**
 * Suggests pictures of the mediapool for the texts of a template with the AI of the user for texts, in two steps:
 * the AI gives search terms for the texts, then it chooses the pictures which were found. It sees only the
 * descriptions of the pictures, never the pictures.
 */
readonly class ImageSuggestion
{
	public const int MAX_TEXT_LENGTH = 3000;
	public const int MAX_TERMS       = 6;
	public const int MAX_CHOSEN      = 8;

	private const string TERMS_INSTRUCTIONS = 'You help to find a picture for a screen of a digital signage platform. '
		. 'You get the texts which are on the screen. The pictures are described with keywords and short descriptions '
		. 'in the language of the locale %s. Give at most ' . self::MAX_TERMS . ' search terms in this language which '
		. 'find pictures that fit the texts: single words or short terms for the subject, things which belong to it and '
		. 'synonyms. Answer with JSON only: {"terms": ["term", ...]}.';

	private const string CHOICE_INSTRUCTIONS = 'You choose a picture for a screen of a digital signage platform. '
		. 'You get the texts on the screen and the pictures which were found as a JSON list, each with its number, name, '
		. 'keywords and description. Choose at most ' . self::MAX_CHOSEN . ' pictures which fit the texts, the best '
		. 'first. Leave out pictures which do not fit. Use only numbers of the list, each at most once. '
		. 'Answer with JSON only: {"pictures": [number, ...]}.';

	public function __construct(private LlmResolver $llmResolver)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(int $UID): bool
	{
		return $this->llmResolver->isAvailable($UID, LlmTask::TEXT);
	}

	/**
	 * @return list<string>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function findSearchTerms(int $UID, string $texts, string $locale): array
	{
		$texts = trim($texts);
		if ($texts === '')
			throw new LlmException('llm_no_answer');
		if (mb_strlen($texts) > self::MAX_TEXT_LENGTH)
			throw new LlmException('llm_input_too_long');

		$answer = $this->ask($UID, sprintf(self::TERMS_INSTRUCTIONS, $locale), $texts);
		$terms  = [];
		foreach (is_array($answer['terms'] ?? null) ? $answer['terms'] : [] as $term)
		{
			if (is_string($term) && mb_strlen(trim($term)) >= 2 && !in_array(mb_strtolower(trim($term)), array_map(mb_strtolower(...), $terms), true))
				$terms[] = mb_substr(trim($term), 0, 50);
		}
		if ($terms === [])
			throw new LlmException('llm_no_answer');

		return array_slice($terms, 0, self::MAX_TERMS);
	}

	/**
	 * @param list<array{name: string, tags: list<string>, description: string}> $candidates
	 * @return list<int> the indexes of the chosen candidates, the best first
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws JsonException
	 * @throws LlmException
	 * @throws UserException
	 */
	public function choose(int $UID, string $texts, array $candidates): array
	{
		$list = [];
		foreach ($candidates as $index => $candidate)
			$list[] = ['number' => $index + 1] + $candidate;

		$text   = 'Texts: ' . trim($texts) . "\nPictures: " . json_encode($list, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$answer = $this->ask($UID, self::CHOICE_INSTRUCTIONS, $text);

		$chosen = [];
		foreach (is_array($answer['pictures'] ?? null) ? $answer['pictures'] : [] as $number)
		{
			$index = is_int($number) || (is_string($number) && ctype_digit($number)) ? (int) $number - 1 : -1;
			if (isset($candidates[$index]) && !in_array($index, $chosen, true))
				$chosen[] = $index;
		}

		return array_slice($chosen, 0, self::MAX_CHOSEN);
	}

	/**
	 * @return array<mixed>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	private function ask(int $UID, string $instructions, string $text): array
	{
		$answer = $this->llmResolver->forUser($UID, LlmTask::TEXT)->complete(new LlmRequest($instructions, [LlmMessage::user($text)], true));

		// reasoning models of a local server may put their thoughts in front of the answer
		$decoded = json_decode(trim((string) preg_replace('#<think>.*?</think>#s', '', $answer)), true);
		if (!is_array($decoded))
			throw new LlmException('llm_no_answer');

		return $decoded;
	}
}
