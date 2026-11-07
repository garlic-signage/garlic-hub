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

use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmMessage;
use App\Framework\Llm\LlmRequest;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * Corrects the spelling, grammar and punctuation of a text with the AI of the user for this task.
 */
readonly class TextCorrection
{
	public const int MAX_LENGTH = 5000;

	private const string INSTRUCTIONS = 'You correct texts for digital signage. Correct the spelling, the grammar and '
		. 'the punctuation. Keep the language of the text, its meaning, its wording where it is correct and its line '
		. 'breaks. Answer only with the corrected text: no explanations, no quotes.';

	public function __construct(private LlmResolver $llmResolver)
	{}

	/**
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 */
	public function correct(int $UID, string $text): string
	{
		if (trim($text) === '')
			return $text;

		if (mb_strlen($text) > self::MAX_LENGTH)
			throw new LlmException('llm_text_too_long');

		$answer = $this->llmResolver->forUser($UID, LlmTask::TEXT)->complete(new LlmRequest(self::INSTRUCTIONS, [LlmMessage::user($text)]));

		// reasoning models of a local server may put their thoughts in front of the answer
		$corrected = trim((string) preg_replace('#<think>.*?</think>#s', '', $answer));
		if ($corrected === '')
			throw new LlmException('llm_no_answer');

		return $corrected;
	}
}
