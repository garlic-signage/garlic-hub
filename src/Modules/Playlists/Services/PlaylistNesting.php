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

use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;

/**
 * Nested playlists must not contain themselves: a player would play them for ever.
 * Used by the insert of a nested playlist and by the restore of a version.
 */
readonly class PlaylistNesting
{
	public function __construct(private ItemsRepository $itemsRepository) {}

	/**
	 * Would the playlist inserted into the target contain the target, directly or deeper?
	 *
	 * @throws Exception
	 */
	public function wouldRecurse(int $targetId, int $insertId): bool
	{
		return $this->contains($insertId, $targetId, []);
	}

	/**
	 * @param array<int,true> $visited a cycle which exists already ends the search instead of looping
	 * @throws Exception
	 */
	private function contains(int $playlistId, int $searchedId, array $visited): bool
	{
		if ($playlistId === $searchedId)
			return true;

		if (isset($visited[$playlistId]))
			return false;

		// @infection-ignore-all isset() is true for false as well, only the key counts
		$visited[$playlistId] = true;
		foreach ($this->itemsRepository->findAllPlaylistItemsByPlaylistId($playlistId) as $item)
		{
			if (isset($item['file_resource']) && $this->contains((int) $item['file_resource'], $searchedId, $visited))
				return true;
		}

		return false;
	}
}
