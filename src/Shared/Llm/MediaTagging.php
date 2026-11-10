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

/**
 * Suggests a short description and keywords for a medium with the AI of the user for this task. The AI sees one
 * image: the picture, a frame of the video or the first page of the document. The user confirms the suggestion.
 */
readonly class MediaTagging
{
	public const int MAX_TAGS = 10;

	private const string INSTRUCTIONS = 'You describe media of a digital signage platform so that they can be found '
		. 'again. The image is a picture, a frame of a video or the first page of a document. Answer with JSON only: '
		. '{"description": "one or two short sentences about what it shows", "tags": ["keyword", ...]}. '
		. 'Give at most ' . self::MAX_TAGS . ' keywords: single words or short terms for the content, the place, '
		. 'the objects, the people (never names) and the visible texts or logos. '
		. 'Write the description and the keywords in the language of the locale %s.';

	public function __construct(private LlmResolver $llmResolver)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(int $UID): bool
	{
		return $this->llmResolver->isAvailable($UID, LlmTask::IMAGE_TAGGING);
	}

	/**
	 * @param string $image the image as data url
	 * @return array{description: string, tags: list<string>}
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function suggest(int $UID, string $image, string $locale): array
	{
		$request = new LlmRequest(sprintf(self::INSTRUCTIONS, $locale), [LlmMessage::user('Describe this medium.', [$image])], true);
		$answer  = $this->llmResolver->forUser($UID, LlmTask::IMAGE_TAGGING)->complete($request);

		$suggestion = LlmAnswer::json($answer);

		$description = is_string($suggestion['description'] ?? null) ? trim($suggestion['description']) : '';
		$tags        = [];
		foreach (is_array($suggestion['tags'] ?? null) ? $suggestion['tags'] : [] as $tag)
		{
			if (is_string($tag) && trim($tag) !== '')
				$tags[] = trim($tag);
		}
		if ($description === '' && $tags === [])
			throw new LlmException('llm_no_answer');

		return ['description' => $description, 'tags' => array_slice($tags, 0, self::MAX_TAGS)];
	}
}
