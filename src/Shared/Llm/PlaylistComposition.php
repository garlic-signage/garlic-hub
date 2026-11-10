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
use App\Framework\Llm\LlmAnswer;
use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;

/**
 * Fills a playlist on a theme with the AI of the user for texts, in two steps: the AI gives search terms for the
 * media, then it chooses and orders the media which were found. It sees only the descriptions of the media.
 */
readonly class PlaylistComposition
{
	public const int MAX_THEME_LENGTH = 300;
	public const int MAX_TERMS        = 8;

	private const string TERMS_INSTRUCTIONS = 'You help to fill a playlist of a digital signage platform on a theme. '
		. 'The media are described with keywords and short descriptions in the language of the locale %s. '
		. 'Give at most ' . self::MAX_TERMS . ' search terms in this language which find media that fit the theme: '
		. 'single words or short terms, the theme itself, synonyms and things which belong to it. '
		. 'Answer with JSON only: {"terms": ["term", ...]}.';

	private const string CHOICE_INSTRUCTIONS = 'You fill a playlist of a digital signage platform on a theme. '
		. 'You get the media which were found as a JSON list, each with its number, name, keywords, description, kind '
		. 'and seconds. Choose the media which fit the theme and put them in a good order for the screen, so that '
		. 'together they play about %d seconds. Leave out media which do not fit. Use only numbers of the list, each at '
		. 'most once. Answer with JSON only: {"items": [number, ...]}.';

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
	public function findSearchTerms(int $UID, string $theme, string $locale): array
	{
		$theme = trim($theme);
		if (mb_strlen($theme) > self::MAX_THEME_LENGTH)
			throw new LlmException('llm_input_too_long');

		$answer = $this->ask($UID, sprintf(self::TERMS_INSTRUCTIONS, $locale), $theme);

		return LlmAnswer::terms($answer['terms'] ?? null, self::MAX_TERMS);
	}

	/**
	 * @param list<array{name: string, tags: list<string>, description: string, kind: string, seconds: int}> $candidates
	 * @return list<int> the indexes of the chosen candidates in their order
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws JsonException
	 * @throws LlmException
	 * @throws UserException
	 */
	public function choose(int $UID, string $theme, int $seconds, array $candidates): array
	{
		$list = [];
		foreach ($candidates as $index => $candidate)
			$list[] = ['number' => $index + 1] + $candidate;

		$text   = 'Theme: ' . trim($theme) . "\nMedia: " . json_encode($list, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$answer = $this->ask($UID, sprintf(self::CHOICE_INSTRUCTIONS, $seconds), $text);

		return LlmAnswer::chosen($answer['items'] ?? null, count($candidates));
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
		return LlmAnswer::json($this->llmResolver->forUser($UID, LlmTask::TEXT)->complete(new LlmRequest($instructions, [LlmMessage::user($text)], true)));
	}
}
