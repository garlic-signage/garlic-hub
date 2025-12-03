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

namespace App\Modules\Playlists\Services\InsertItems;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Playlists\Services\WidgetsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

class Media extends AbstractInsertItem
{
	private MediaService $mediaService;
	private WidgetsService $widgetsService;

	public function __construct(
		ItemsRepository $itemsRepository,
		MediaService $mediaService,
		PlaylistsService $playlistsService,
		PlaylistMetricsCalculator $playlistMetricsCalculator,
		WidgetsService $widgetsService,
		LoggerInterface $logger)
	{
		$this->itemsRepository  = $itemsRepository;
		$this->playlistsService = $playlistsService;
		$this->mediaService     = $mediaService;
		$this->widgetsService   = $widgetsService;
		$this->playlistMetricsCalculator = $playlistMetricsCalculator;

		parent::__construct($logger);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function insert(int $playlistId, int|string $insertId, int $position): array
	{
		try
		{
			$this->itemsRepository->beginTransaction();
			$this->mediaService->setUID($this->UID);
			$playlistData = $this->checkPlaylistAcl($playlistId);

			$media = $this->mediaService->fetchMedia((string) $insertId); // checks rights, too
			if (empty($media))
				throw new ModuleException('items', 'Media is not accessible');

			/*			if (!$this->allowedByTimeLimit($playlistId, $playlistData['time_limit']))
							throw new ModuleException('items', 'Playlist time limit exceeds');
			*/
			$itemDuration =  $this->playlistMetricsCalculator->calculateRemainingMediaDuration($playlistData, $media);
			$this->itemsRepository->updatePositionsWhenInserted($playlistId, $position);

			$saveItem = [
				'playlist_id'   => $playlistId,
				'datasource'    => 'file',
				'UID'           => $this->UID,
				'item_duration' => $itemDuration,
				'item_filesize' => $media['metadata']['size'],
				'item_order'    => $position,
				'item_name'     => $media['filename'],
				'item_type'     => ItemType::MEDIAPOOL->value,
				'file_resource' => $media['checksum'],
				'mimetype'      => $media['mimetype'],
			];
			if ($media['mimetype'] === 'application/widget' && !empty($media['config_data']))
				$saveItem['content_data'] = $this->widgetsService->prepareContentData($media['config_data'], [], true);
			else
				$saveItem['content_data'] = '';

			$insertId = $this->itemsRepository->insert($saveItem);
			if ($insertId === 0)
				throw new ModuleException('items', 'Playlist item could not be inserted.');

			$saveItem['item_id'] = $insertId;
			$saveItem['paths'] = $media['paths'];

			$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->getMetricsForFrontend();

			$this->itemsRepository->commitTransaction();

			return ['playlist_metrics' => $playlistMetrics, 'item' => $saveItem];
		}
		catch (Exception | ModuleException | CoreException | PhpfastcacheSimpleCacheException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error insert media: ' . $e->getMessage());
			return [];
		}
	}

}