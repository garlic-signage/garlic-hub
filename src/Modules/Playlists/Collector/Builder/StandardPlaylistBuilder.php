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

namespace App\Modules\Playlists\Collector\Builder;

use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;

/**
 * Builds a playlist structure by gathering playlist items, prefetches, and exclusives
 * associated with the current playlist identifier. It formats the collected items
 * and uses a factory to create the playlist structure.
 */
class StandardPlaylistBuilder extends AbstractPlaylistBuilder
{
	public function buildPlaylist(): PlaylistStructureInterface
	{
		$playlistId = $this->playerEntity->getPlaylistId();

		$items     = $this->buildHelper->collectItems($playlistId);
		$prefetch  = $this->buildHelper->collectPrefetches($playlistId);
		$exclusive = $this->buildHelper->collectExclusives($playlistId);

		$formattedItems = FormatHelper::wrapWithSequence($items);

		return $this->simplePlaylistStructureFactory->create($formattedItems, $prefetch, $exclusive);
	}
}
