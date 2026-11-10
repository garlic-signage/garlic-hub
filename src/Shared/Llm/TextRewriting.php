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
use App\Framework\Localization\Locales\Locales;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * Shortens or translates the text of a text box with the model of the user for texts. Like the correction, the
 * answer replaces the text in the composer, undo brings the old one back.
 */
readonly class TextRewriting
{
	/** fewer characters are no text any more */
	public const int MIN_LENGTH = 10;

	private const string SHORTEN_INSTRUCTIONS = 'You shorten texts for digital signage. Shorten the text to at most %d '
		. 'characters. Keep its language, its meaning and the most important information, leave out filler words and '
		. 'details. Answer only with the shortened text: no explanations, no quotes.';

	private const string TIGHTEN_INSTRUCTIONS = 'You shorten texts for digital signage. Make the text a little more '
		. 'concise. Keep its language, its meaning and all information. Answer only with the text: no explanations, '
		. 'no quotes.';

	private const string TRANSLATE_INSTRUCTIONS = 'You translate texts for digital signage. Translate the text into '
		. 'the language of the locale %s. Keep its meaning, its tone and its line breaks, keep names as they are. '
		. 'Answer only with the translated text: no explanations, no quotes.';

	public function __construct(private LlmResolver $llmResolver, private Locales $locales)
	{}

	/**
	 * @param int $maxLength the characters which fit, the composer estimates them from the text box and the template
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function shorten(int $UID, string $text, int $maxLength): string
	{
		if (trim($text) === '')
			return $text;

		// a text which fits already becomes a little tighter
		$instructions = mb_strlen($text) > $maxLength
			? sprintf(self::SHORTEN_INSTRUCTIONS, max(self::MIN_LENGTH, $maxLength))
			: self::TIGHTEN_INSTRUCTIONS;

		return $this->rewrite($UID, $instructions, $text);
	}

	/**
	 * @param string $locale one of the languages of the interface, e.g. de_DE
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function translate(int $UID, string $text, string $locale): string
	{
		if (!in_array($locale, $this->locales->getAvailableLocales(), true))
			throw new LlmException('llm_unknown_language');
		if (trim($text) === '')
			return $text;

		return $this->rewrite($UID, sprintf(self::TRANSLATE_INSTRUCTIONS, $locale), $text);
	}

	/**
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	private function rewrite(int $UID, string $instructions, string $text): string
	{
		if (mb_strlen($text) > TextCorrection::MAX_LENGTH)
			throw new LlmException('llm_text_too_long');

		$answer = $this->llmResolver->forUser($UID, LlmTask::TEXT)->complete(new LlmRequest($instructions, [LlmMessage::user($text)]));

		$rewritten = LlmAnswer::text($answer);
		if ($rewritten === '')
			throw new LlmException('llm_no_answer');

		return $rewritten;
	}
}
