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

namespace App\Modules\Player\Services;

use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Playlists\Contracts\PlaylistUsageFinderInterface;

/**
 * Tells Playlists which playlists are assigned to a player.
 */
readonly class PlayerPlaylistUsageFinder implements PlaylistUsageFinderInterface
{
	public function __construct(private PlayerRepository $playerRepository)
	{
	}

	public function findUsedPlaylistIds(array $playlistIds): array
	{
		$usedIds = [];
		foreach ($this->playerRepository->findPlaylistIdsByPlaylistIds($playlistIds) as $value)
		{
			$usedIds[] = (int) $value['playlist_id'];
		}

		return $usedIds;
	}
}
