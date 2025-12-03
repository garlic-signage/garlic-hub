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
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

class Playlist extends AbstractInsertItem
{
	public function __construct(ItemsRepository $itemsRepository, PlaylistsService $playlistsService, PlaylistMetricsCalculator $playlistMetricsCalculator, LoggerInterface $logger)
	{
		$this->itemsRepository  = $itemsRepository;
		$this->playlistsService = $playlistsService;
		$this->playlistMetricsCalculator = $playlistMetricsCalculator;

		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function insert(int $playlistId, string|int $insertId, int $position): array
	{
		try
		{
			$this->itemsRepository->beginTransaction();

			$playlistTargetData = $this->checkPlaylistAcl($playlistId);
			$playlistInsertData = $this->checkPlaylistAcl((int) $insertId);

			if ($this->checkForRecursiveInserts($playlistTargetData['playlist_id'], $playlistInsertData['playlist_id']))
				throw new ModuleException('items', 'Playlist recursion alert.');

			/*			if (!$this->allowedByTimeLimit($targetId, $playlistTargetData['time_limit']))
							throw new ModuleException('items', 'Playlist time limit exceeds');
			*/
			$this->itemsRepository->updatePositionsWhenInserted($playlistId, $position);

			$saveItem = [
				'playlist_id'   => $playlistId,
				'datasource'    => 'file',
				'UID'           => $this->UID,
				'item_duration' => $playlistInsertData['duration'],
				'item_filesize' => $playlistInsertData['filesize'],
				'item_order'    => $position,
				'item_name'     => $playlistInsertData['playlist_name'],
				'item_type'     => ItemType::PLAYLIST->value,
				'file_resource' => $insertId,
				'mimetype'      => ''
			];
			$id = $this->itemsRepository->insert($saveItem);
			if ($id === 0)
				throw new ModuleException('items', 'Playlist item could not inserted.');

			$saveItem['item_id'] = $id;
			$saveItem['paths']['thumbnail'] = 'public/images/icons/playlist.svg';

			$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistTargetData)->getMetricsForFrontend();

			$this->itemsRepository->commitTransaction();

			return ['playlist_metrics' => $playlistMetrics, 'item' => $saveItem];
		}
		catch (Exception | ModuleException | CoreException | PhpfastcacheSimpleCacheException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error insert playlist: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @throws Exception
	 */
	private function checkForRecursiveInserts(int $targetId, int $insertId): bool
	{
		if ($targetId == $insertId)
			return true;

		foreach ($this->itemsRepository->findAllPlaylistItemsByPlaylistId($insertId) as $value)
		{
			if (isset($value['file_resource']) && $this->checkForRecursiveInserts($targetId, $value['file_resource']) === true)
				return true;
		}

		return false;
	}
}