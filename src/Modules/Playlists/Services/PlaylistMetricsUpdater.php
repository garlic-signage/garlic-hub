<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * Transfers the metrics of an exported playlist to the playlists it is nested in,
 * and to the items which represent a playlist inside other playlists.
 */
readonly class PlaylistMetricsUpdater
{
	public function __construct(
		private ItemsRepository $itemsRepository,
		private PlaylistsService $playlistsService,
		private PlaylistMetricsCalculator $playlistMetricsCalculator,
		private LoggerInterface $logger
	) {}

	/**
	 * @throws Exception
	 */
	public function updateItemsMetrics(int $playlistId, PlaylistMetrics $metrics): void
	{
		$this->itemsRepository->updateWithWhere($metrics->toItemArray(), ['file_resource' => $playlistId]);
	}

	/**
	 *
	 * 1. Save metrics in the exported playlist (did in export-class)
	 * 2. Save metrics in all items that represent this playlist. (did in export-class)
	 * 3. Collect all playlists that have the exported playlist nested, on every level
	 * 4. Calculate and save the metrics of each of them once, a playlist only
	 *    after all of its nested playlists are updated
	 *
	 * A playlist reachable on several paths is calculated only once. Playlists in a
	 * nesting cycle are not updated, but logged, instead of recursing endlessly.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function updateMetricsRecursively(int $playlistId): void
	{
		[$parents, $playlists] = $this->collectContainingPlaylists($playlistId);

		// count for every containing playlist the nested playlists which must be updated before
		// the playlist itself is already updated, so a nesting back to it is a cycle
		$pending = [];
		$cyclic  = [];
		foreach ($parents as $parentIds)
		{
			foreach ($parentIds as $parentId)
			{
				if ($parentId === $playlistId)
					$cyclic[] = $playlistId;
				else
					$pending[$parentId] = ($pending[$parentId] ?? 0) + 1;
			}
		}

		$updated = [$playlistId];
		while ($updated !== [])
		{
			$childId = array_shift($updated);
			foreach ($parents[$childId] as $parentId)
			{
				if ($parentId === $playlistId || --$pending[$parentId] !== 0)
					continue;

				$metrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlists[$parentId]);
				$this->updateItemsMetrics($parentId, $metrics);
				$this->updatePlaylistMetrics($parentId, $metrics);
				$updated[] = $parentId;
			}
		}

		$cyclic = array_unique([...$cyclic, ...array_keys(array_filter($pending, fn(int $count) => $count > 0))]);
		if ($cyclic !== [])
			$this->logger->warning('Metrics not updated for playlists in a nesting cycle: ' . implode(', ', $cyclic));
	}

	/**
	 * Finds all playlists which contain the given playlist directly or nested,
	 * querying each playlist only once.
	 *
	 * @return array{0: array<int,list<int>>, 1: array<int,array<string,mixed>>}
	 *         parent ids by playlist id, and the data of the containing playlists
	 * @throws Exception
	 */
	private function collectContainingPlaylists(int $playlistId): array
	{
		$parents   = [];
		$playlists = [];
		$queue     = [$playlistId];
		while ($queue !== [])
		{
			$childId = array_shift($queue);
			if (isset($parents[$childId]))
				continue;

			$parents[$childId] = [];
			foreach ($this->itemsRepository->findAllPlaylistsContainingPlaylist($childId) as $playlistData)
			{
				$parentId = (int) $playlistData['playlist_id'];
				$parents[$childId][]  = $parentId;
				$playlists[$parentId] = $playlistData;
				$queue[]              = $parentId;
			}
		}

		return [$parents, $playlists];
	}

	/**
	 * @throws Exception
	 */
	private function updatePlaylistMetrics(int $playlistId, PlaylistMetrics $metrics): void
	{
		$this->playlistsService->update($playlistId, $metrics->toPlaylistTableArray());
	}
}
