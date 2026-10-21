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
use App\Framework\Helper\Serialization;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;

/**
 * Loads the items of a playlist for the SMIL export with their stored settings
 * unserialized, together with the metrics of the playlist.
 */
readonly class ExportItemsLoader
{
	public function __construct(
		private ItemsRepository $itemsRepository,
		private PlaylistMetricsCalculator $playlistMetricsCalculator
	) {}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array{playlist_metrics: PlaylistMetrics, items: list<array<string,mixed>>}
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function loadByPlaylist(array $playlist, string $edition): array
	{
		$items   = [];
		$results = $this->itemsRepository->findAllByPlaylistIdWithJoins($playlist['playlist_id'], $edition);
		foreach ($results as $item)
		{
			$item['conditional']   = $this->sanitize($item['conditional']);
			$item['properties']    = $this->sanitize($item['properties']);
			$item['categories']    = $this->sanitize($item['categories']);
			// Todo: I do mot like this. MAybe widgets should also saved as JSON.
			if ($item['item_type'] !== ItemType::TEMPLATE->value)
				$item['content_data']  = $this->sanitize($item['content_data']);

			$item['begin_trigger'] = $this->sanitize($item['begin_trigger']);
			$item['end_trigger']   = $this->sanitize($item['end_trigger']);
			$items[] = $item;
		}

		$playlistMetrics = $this->playlistMetricsCalculator->calculateFromItems($playlist, $results);

		return ['playlist_metrics' =>  $playlistMetrics, 'items' => $items];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function sanitize(string $value): array
	{
		return Serialization::unserializeSecure($value);
	}
}
