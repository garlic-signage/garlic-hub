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

namespace App\Modules\Playlists\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\MediaService;
use App\Shared\Llm\PlaylistComposition;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use JsonException;

/**
 * The suggestion of the AI for a playlist on a theme: pictures and videos of the mediapool which the user may read,
 * found by the search terms of the AI and chosen by it. Nothing is inserted here: the user ticks the ones he wants
 * and the composer inserts them like dropped media.
 */
readonly class PlaylistFillingService
{
	public const int MAX_CANDIDATES = 60;
	public const int MAX_MINUTES    = 120;

	public function __construct(
		private PlaylistsService $playlistsService,
		private MediaService $mediaService,
		private PlaylistComposition $playlistComposition,
		private PlaylistMetricsCalculator $playlistMetricsCalculator,
		private UserSession $userSession
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isAvailable(): bool
	{
		return $this->playlistComposition->isAvailable($this->userSession->getUID());
	}

	/**
	 * @return list<array{media_id: string, filename: string, mimetype: string, seconds: int, thumbnail: string}>
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws JsonException
	 * @throws LlmException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws UserException
	 */
	public function suggest(int $playlistId, string $theme, int $minutes): array
	{
		// only for a playlist which the user may edit
		$this->playlistsService->loadPlaylistForEdit($playlistId);
		if (!$this->isAvailable())
			throw new LlmException('llm_not_configured');

		$UID        = $this->userSession->getUID();
		$terms      = $this->playlistComposition->findSearchTerms($UID, $theme, $this->userSession->getLocales());
		$candidates = $this->findCandidates($terms);
		if ($candidates === [])
			throw new TranslatableModuleException('playlists', 'ai_nothing_found');

		$described = array_map(static fn(array $media): array => [
			'name'        => (string) $media['filename'],
			'tags'        => $media['tags'],
			'description' => (string) $media['media_description'],
			'kind'        => str_starts_with((string) $media['mimetype'], 'video/') ? 'video' : 'image',
			'seconds'     => $media['seconds']
		], $candidates);
		$seconds = max(1, min(self::MAX_MINUTES, $minutes)) * 60;
		$chosen  = $this->playlistComposition->choose($UID, $theme, $seconds, $described);
		if ($chosen === [])
			throw new TranslatableModuleException('playlists', 'ai_nothing_found');

		$suggestion = [];
		foreach ($chosen as $index)
		{
			$media = $candidates[$index];
			$suggestion[] = [
				'media_id'  => (string) $media['media_id'],
				'filename'  => (string) $media['filename'],
				'mimetype'  => (string) $media['mimetype'],
				'seconds'   => $media['seconds'],
				'thumbnail' => (string) $media['urls']['thumbnail']
			];
		}

		return $suggestion;
	}

	/**
	 * The pictures and videos which the terms find, each once, in the order they were found.
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
				$mimetype = (string) $media['mimetype'];
				if (isset($candidates[$media['media_id']]) || (!str_starts_with($mimetype, 'image/') && !str_starts_with($mimetype, 'video/')))
					continue;

				$duration = (float) ($media['metadata']['duration'] ?? 0);
				$media['seconds'] = $duration > 0 ? (int) round($duration) : $this->playlistMetricsCalculator->getDefaultDuration();
				$candidates[$media['media_id']] = $media;
				if (count($candidates) >= self::MAX_CANDIDATES)
					break 2;
			}
		}

		return array_values($candidates);
	}
}
