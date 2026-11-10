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

namespace App\Modules\Mediapool\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Utils\TaggingImage;
use App\Shared\Llm\MediaTagging;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;

/**
 * Suggests the description and the tags of one medium which the user may edit. Nothing is stored here: the
 * user sees the suggestion in the edit dialog and saves it from there.
 */
readonly class MediaTaggingService
{
	public function __construct(
		private MediaService $mediaService,
		private TaggingImage $taggingImage,
		private MediaTagging $mediaTagging,
		private UserSession $userSession
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(): bool
	{
		return $this->mediaTagging->isAvailable($this->userSession->getUID());
	}

	/**
	 * @return array{description: string, tags: list<string>}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws LlmException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws UserException
	 */
	public function suggest(string $mediaId): array
	{
		$media = $this->mediaService->fetchEditableMedia($mediaId);
		// the image of a video takes a while: not without an AI to send it to
		if (!$this->isAvailable())
			throw new LlmException('llm_not_configured');

		// @infection-ignore-all the mimetype of the database is a text
		$mimetype = (string) $media['mimetype'];
		if (!TaggingImage::supports($mimetype))
			throw new TranslatableModuleException('mediapool', 'media_not_taggable');

		try
		{
			$image = $this->taggingImage->create((string) $media['paths']['original'], $mimetype);
		}
		catch (\Exception $e)
		{
			// a broken file or a video too short for its frame
			throw new TranslatableModuleException('mediapool', 'media_not_taggable', [], $e);
		}

		return $this->mediaTagging->suggest($this->userSession->getUID(), $image, $this->userSession->getLocales());
	}
}
