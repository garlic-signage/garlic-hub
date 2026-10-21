<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\PlaylistMode;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;

class ExportService extends AbstractBaseService
{
	private readonly Config $config;
	private readonly PlaylistsService $playlistsService;
	private readonly ItemsService $itemsService;
	private readonly LocalWriter $localSmilWriter;
	private readonly PlaylistContent $playlistContent;

	public function __construct(Config $config, PlaylistsService $playlistsService, ItemsService $itemsService, LocalWriter $localSmilWriter, PlaylistContent $playlistContent, LoggerInterface $logger )
	{
		$this->config          = $config;
		$this->playlistsService = $playlistsService;
		$this->itemsService    = $itemsService;
		$this->localSmilWriter = $localSmilWriter;
		$this->playlistContent = $playlistContent;

		parent::__construct($logger);
	}

	/**
	 * @param int $playlistId
	 * @return int
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function exportToSmil(int $playlistId): int
	{
		try
		{
			$this->itemsService->getItemsRepository()->beginTransaction();

			$playlist = $this->playlistsService->loadPureById($playlistId); // checks rights

			$count = 0;
			if ($playlist['playlist_mode'] === PlaylistMode::MULTIZONE->value && !empty($playlist['multizone']))
			{
				$zones = unserialize($playlist['multizone']);
				$metrics = ['filesize' => 0, 'duration' => 0, 'owner_duration' => 0];
				foreach ($zones as $zone)
				{
					$tmp = $this->export($this->playlistsService->loadPureById($zone['zones']['zone_playlist_id']));
					$count++;
					// use the highest values for the duration of multizone.
					$metrics['filesize'] = max($metrics['filesize'], $tmp['filesize']);
					$metrics['duration'] = max($metrics['duration'], $tmp['duration']);
					$metrics['owner_duration'] = max($metrics['owner_duration'], $tmp['owner_duration']);
				}
			}
			else
			{
				$metrics = $this->export($playlist);
				$count++;
			}

			if ($this->playlistsService->updateExport($playlistId, $metrics) === 0)
				throw new ModuleException('export_playlist', 'Export '.$playlistId.' failed. Could not update playlist metrics.');

			$this->itemsService->getItemsRepository()->commitTransaction();
			return $count;
		}
		catch (ModuleException|CoreException|Exception|FilesystemException $e)
		{
			$this->itemsService->getItemsRepository()->rollBackTransaction();
			$this->logger->error('Error export SMIL playlist: ' . $e->getMessage());
			return 0;
		}
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	public function export(array $playlist): array
	{
		$results = $this->itemsService->loadByPlaylistForExport($playlist, $this->config->getEdition());
		$this->itemsService->updateItemsMetrics($playlist['playlist_id'], $results['playlist_metrics']);
		/*
			    1. Save metrics in the exported playlist (did with results as last, but doesn't matter)
				2. Save metrics in all items wich represent this playlist.
				3. find all playlists that have the exported playlist nested
				4. calculate metrics for the new playlists
				5. save metrics in new playlists
				6. save metrics in all items that represent the new playlist
				7. recurse to 3.
		 */
		$this->itemsService->updateMetricsRecursively($playlist['playlist_id']);

		$this->playlistContent->init($playlist, $results['items'])->build();
		$this->localSmilWriter->initExport($playlist['playlist_id']);
		$this->localSmilWriter->writeSMILFiles($this->playlistContent);

		return $results['playlist_metrics']->toPlaylistTableArray();
	}

}