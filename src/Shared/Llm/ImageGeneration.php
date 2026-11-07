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
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use finfo;

/**
 * An image of the AI of the user for this task from a description. It is only shown: the user uploads it into the
 * mediapool like a photo of the camera.
 */
readonly class ImageGeneration
{
	public const int MAX_PROMPT_LENGTH = 1000;

	/** the formats of the form and their sizes, which the gpt-image models and the Flux models accept */
	public const array SIZES = [
		'landscape' => '1536x1024',
		'portrait'  => '1024x1536',
		'square'    => '1024x1024'
	];

	private const array MIMETYPES = ['image/png', 'image/jpeg', 'image/webp'];

	public function __construct(private LlmResolver $llmResolver)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(int $UID): bool
	{
		return $this->llmResolver->isAvailable($UID, LlmTask::IMAGE_GENERATION);
	}

	/**
	 * @return string the image as data url
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws UserException
	 */
	public function generate(int $UID, string $prompt, string $format): string
	{
		$prompt = trim($prompt);
		if ($prompt === '')
			throw new LlmException('llm_prompt_missing');
		if (mb_strlen($prompt) > self::MAX_PROMPT_LENGTH)
			throw new LlmException('llm_input_too_long');

		$image = $this->llmResolver->forUser($UID, LlmTask::IMAGE_GENERATION)->generateImage($prompt, self::SIZES[$format] ?? self::SIZES['landscape']);

		// only an image goes into the browser and from there into the mediapool
		$mimetype = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($image);
		if (!in_array($mimetype, self::MIMETYPES, true))
			throw new LlmException('llm_no_answer');

		return 'data:' . $mimetype . ';base64,' . base64_encode($image);
	}
}
