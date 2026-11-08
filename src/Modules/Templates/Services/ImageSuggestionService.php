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

namespace App\Modules\Templates\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\MediaService;
use App\Shared\Llm\ImageSuggestion;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;

/**
 * Pictures of the mediapool which the user may read and which fit the texts of a template: found by the search terms
 * of the AI and chosen by it. Nothing is changed here, the composer replaces the selected image with the one the user
 * clicks.
 */
readonly class ImageSuggestionService
{
	public const int MAX_CANDIDATES = 40;

	public function __construct(
		private MediaService $mediaService,
		private ImageSuggestion $imageSuggestion,
		private UserSession $userSession
	) {}

	/**
	 * @return list<array{media_id: string, filename: string, file_name: string, original: string, thumbnail: string}>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws JsonException
	 * @throws LlmException
	 * @throws ModuleException
	 * @throws TranslatableModuleException
	 * @throws UserException
	 */
	public function suggest(string $texts): array
	{
		$UID = $this->userSession->getUID();
		if (!$this->imageSuggestion->isAvailable($UID))
			throw new LlmException('llm_not_configured');

		$terms      = $this->imageSuggestion->findSearchTerms($UID, $texts, $this->userSession->getLocales());
		$candidates = $this->findCandidates($terms);
		if ($candidates === [])
			throw new TranslatableModuleException('templates', 'ai_no_image_found');

		$described = array_map(static fn(array $media): array => [
			'name'        => (string) $media['filename'],
			'tags'        => $media['tags'],
			'description' => (string) $media['media_description']
		], $candidates);
		$chosen = $this->imageSuggestion->choose($UID, $texts, $described);
		if ($chosen === [])
			throw new TranslatableModuleException('templates', 'ai_no_image_found');

		$suggestion = [];
		foreach ($chosen as $index)
		{
			$media = $candidates[$index];
			$suggestion[] = [
				'media_id'  => (string) $media['media_id'],
				'filename'  => (string) $media['filename'],
				// the composer stores the plain file name and builds the signed url on every load
				'file_name' => $media['checksum'] . '.' . $media['extension'],
				'original'  => (string) $media['urls']['original'],
				'thumbnail' => (string) $media['urls']['thumbnail']
			];
		}

		return $suggestion;
	}

	/**
	 * The pictures which the terms find, each once, in the order they were found. Videos and other files can not
	 * replace an image of the composer.
	 *
	 * @param list<string> $terms
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function findCandidates(array $terms): array
	{
		$candidates = [];
		foreach ($terms as $term)
		{
			foreach ($this->mediaService->searchMedia($term) as $media)
			{
				if (isset($candidates[$media['media_id']]) || !str_starts_with((string) $media['mimetype'], 'image/'))
					continue;

				$candidates[$media['media_id']] = $media;
				if (count($candidates) >= self::MAX_CANDIDATES)
					break 2;
			}
		}

		return array_values($candidates);
	}
}
