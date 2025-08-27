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
 * Class MultizonePlaylistBuilder
 *
 * This class extends the AbstractPlaylistBuilder to handle the construction
 * of a playlist for multiple zones. It processes zone-specific data, formats
 * the playlist items, prefetch data, and exclusive data for each zone, and
 * constructs a PlaylistStructureInterface using the aggregated information.
 */
class MultizonePlaylistBuilder extends AbstractPlaylistBuilder
{
	public function buildPlaylist(): PlaylistStructureInterface
	{
		$items     = '';
		$prefetch  = '';
		$exclusive = '';
		$zones = $this->playerEntity->getZones();
		foreach ($zones['zones'] as $screenId => $value)
		{
			$zonePlaylistId = (int) $value['zone_playlist_id'];

			$zoneItems = $this->buildHelper->collectItems($zonePlaylistId);
			$zonePrefetch = $this->buildHelper->collectPrefetches($zonePlaylistId);
			$zoneExclusive = $this->buildHelper->collectExclusives($zonePlaylistId);

			$items     .= FormatHelper::formatMultiZoneItems($screenId, $zoneItems);
			$prefetch  .= $zonePrefetch . "\n";
			$exclusive .= FormatHelper::formatMultiZoneExclusive($screenId, $zoneExclusive);
		}

		return $this->simplePlaylistStructureFactory->create($items, $prefetch, $exclusive);
	}
}
