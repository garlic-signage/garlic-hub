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
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;

/**+
 * Todo Concept:
 * Adding, deleting or duration change playlist needs to check for time limit directly
 * Exporting will set recursively item times and should stop, if a higher leveled playlist time limit will exceed
 */
class ItemsService extends AbstractBaseService
{
	private readonly ItemsRepository $itemsRepository;
	private readonly PlaylistsService $playlistsService;
	private readonly MediaService $mediaService;
	private readonly PlaylistMetricsCalculator $playlistMetricsCalculator;
	private int $itemDuration = 0;

	public function __construct(ItemsRepository $itemsRepository,
								MediaService $mediaService,
								PlaylistsService $playlistsService,
								PlaylistMetricsCalculator $playlistMetricsCalculator,
								LoggerInterface $logger)
	{
		$this->itemsRepository  = $itemsRepository;
		$this->playlistsService = $playlistsService;
		$this->mediaService     = $mediaService;
		$this->playlistMetricsCalculator = $playlistMetricsCalculator;

		parent::__construct($logger);
	}

	public function getItemsRepository(): ItemsRepository
	{
		return $this->itemsRepository;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function loadByPlaylistForExport(array $playlist, string $edition): array
	{
		$items   = [];
		$results = $this->itemsRepository->findAllByPlaylistIdWithJoins($playlist['playlist_id'], $edition);
		foreach ($results as $item)
		{
			$item['conditional']   = $this->sanitize($item['conditional']);
			$item['properties']    = $this->sanitize($item['properties']);
			$item['categories']    = $this->sanitize($item['categories']);
			$item['content_data']  = $this->sanitize($item['content_data']);
			$item['begin_trigger'] = $this->sanitize($item['begin_trigger']);
			$item['end_trigger']   = $this->sanitize($item['end_trigger']);
			$items[] = $item;
		}

		$playlistMetrics = $this->playlistMetricsCalculator
			->reset()
			->calculateFromItems($playlist, $results)
			->getMetricsForPlaylistTable();

		return ['playlist_metrics' =>  $playlistMetrics, 'items' => $items];
	}

	public function getItemDuration(): int
	{
		return $this->itemDuration;
	}

	/**
	 * @return list<array{item_id:int, item_name:string}>
	 * @throws Exception
	 */
	public function findMediaInPlaylist(int $playlistId): array
	{
		return $this->itemsRepository->findMediaInPlaylistId($playlistId);
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function fetchItemById(int $itemId): array
	{
		$item = $this->itemsRepository->findFirstById($itemId);
		$this->playlistsService->setUID($this->UID);
		$this->playlistsService->loadPureById($item['playlist_id']); // check rights

		switch ($item['item_type'])
		{
			case ItemType::MEDIAPOOL->value:
				$this->mediaService->setUID($this->UID);
				$media = $this->mediaService->fetchMediaByChecksum($item['file_resource']);
				if (empty($media))
					throw new ModuleException('items', 'Item not found');
				$item['config_data'] = $media['config_data'];
				if (str_starts_with($item['mimetype'], 'video/'))
					$item['default_duration'] = $media['metadata']['duration'];
				else
					$item['default_duration'] = $this->playlistMetricsCalculator->getDefaultDuration();
				break;
			case ItemType::PLAYLIST->value:
				$playlist = $this->playlistsService->fetchById((int) $item['file_resource']); // check rights
				$this->playlistMetricsCalculator->setUID($this->UID);
				$defaultDuration = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->getDuration();
				if ($playlist['time_limit'] > 0 && $defaultDuration > $playlist['time_limit'])
					$item['default_duration'] = $playlist['time_limit'];
				else
					$item['default_duration'] = $defaultDuration;
				break;
			default:
				$item['default_duration'] = $this->playlistMetricsCalculator->getDefaultDuration();
		}

		return $item;
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loadItemsByPlaylistIdForComposer(int $playlistId): array
	{
		$this->playlistsService->setUID($this->UID);

		$playlistData  = $this->playlistsService->loadPureById($playlistId); // checks rights, too
		$items         = [];
		$result        = $this->itemsRepository->findAllByPlaylistId($playlistId);

		foreach($result as $value)
		{
			switch ($value['item_type'])
			{
				case ItemType::MEDIAPOOL->value:
					$thumbnailPath = $this->mediaService->getPathThumbnails();
					$ext = 'jpg';
					$extMap = ['svg'  => 'svg', 'svg+xml' => 'svg',	'gif'  => 'jpg', 'jpeg' => 'jpg'];
					if (str_starts_with($value['mimetype'], 'image/'))
					{
						$s = (string) strrchr($value['mimetype'], '/');
						$mimeTypePart = substr($s, 1);

						if (isset($extMap[$mimeTypePart]))
							$ext = $extMap[$mimeTypePart];
						 else
							$ext = $mimeTypePart;
					}

					$filename = $value['file_resource'];
					$isSvgWidget = ($value['mimetype'] === 'application/widget' && $value['content_data'] == '');
					$finalExt = $isSvgWidget ? 'svg' : $ext;

					$tmp = $value;
					$tmp['paths']['thumbnail'] = $thumbnailPath . '/' . $filename . '.' . $finalExt;
					$items[] = $tmp;					break;
				case ItemType::PLAYLIST->value:
					$tmp = $value;
					$tmp['paths']['thumbnail'] = 'public/images/icons/playlist.svg';
					$items[] = $tmp;
					break;

			}
		}

		$this->playlistMetricsCalculator->setUID($this->UID);
		$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->getMetricsForFrontend();

		return ['playlist_metrics' =>  $playlistMetrics, 'playlist' => $playlistData, 'items' => $items];
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function updateField(mixed $itemId, string $fieldName, string|int $fieldValue): int
	{
		$this->playlistsService->setUID($this->UID);
		$item = $this->itemsRepository->findFirstById($itemId);
		if ($item === [])
			return 0;

		$this->playlistsService->loadPureById($item['playlist_id']); // will check for rights

		$saveData = [strip_tags($fieldName) => strip_tags((string)$fieldValue)];

		return $this->itemsRepository->update($itemId, $saveData);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function updateItemDuration(int $itemId, int $wantedDuration): int
	{
		$this->playlistMetricsCalculator->setUID($this->UID);
		$this->playlistsService->setUID($this->UID);
		$item = $this->itemsRepository->findFirstById($itemId);
		if ($item === [])
			return 0;

		$playlist = $this->playlistsService->loadPureById($item['playlist_id']); // will check for rights
		// if an item is a playlist, we need to get this limit first
		if ($item['item_type'] === ItemType::PLAYLIST->value)
		{
			$itemPlaylist = $this->playlistsService->fetchById((int) $item['file_resource']);
			$wantedDuration   = $this->playlistMetricsCalculator->calculateRemainingDuration($itemPlaylist, $wantedDuration);
		}

		$this->itemDuration = $this->playlistMetricsCalculator->calculateRemainingDuration($playlist, $wantedDuration);

		$saveData = ['item_duration' => $this->itemDuration];

		return $this->itemsRepository->update($itemId, $saveData);
	}



	/**
	 * @param int $playlistId
	 * @param array<int,string> $itemsOrder
	 * @return bool
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function updateItemOrder(int $playlistId, array $itemsOrder): bool
	{
		try
		{
			$this->playlistsService->setUID($this->UID);
			$this->itemsRepository->beginTransaction();
			$this->playlistsService->loadPureById($playlistId); // will check for rights

			foreach ($itemsOrder as $key => $itemId)
			{
				if ($this->itemsRepository->updateItemOrder((int)$itemId, $key) === 0)
					throw new ModuleException('items', 'Item order for item_id '.$itemId.' could not be updated');
			}
			$this->itemsRepository->commitTransaction();
			return true;
		}
		catch (Exception | ModuleException | CoreException | PhpfastcacheSimpleCacheException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error item reorder: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function delete(int $playlistId, int $itemId): array
	{
		try
		{
			$this->itemsRepository->beginTransaction();

			$playlistData = $this->checkPlaylistAcl($playlistId);
			$item         = $this->itemsRepository->findFirstBy(['item_id' => $itemId]);
			if (empty($item))
				throw new ModuleException('items', 'Item with idem_id: '.$itemId.' not found');

			// todo for Core / Enterprise: Check if item belongs to an admin

			$deleteId = $this->itemsRepository->delete($itemId);
			if ($deleteId === 0)
				throw new ModuleException('items', 'Item could not deleted');

			$this->itemsRepository->updatePositionsWhenDeleted($playlistId, $item['item_order']);

			$this->playlistMetricsCalculator->setUID($this->UID);
			$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->getMetricsForFrontend();

			$this->itemsRepository->commitTransaction();

			return ['playlist_metrics' => $playlistMetrics, 'delete_id' => $deleteId];
		}
		catch (Exception | ModuleException | CoreException | PhpfastcacheSimpleCacheException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error delete item: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @throws Exception
	 */
	public function updateItemsMetrics(int $playlistId): void
	{
		$saveItem = [
			'item_duration' => $this->playlistMetricsCalculator->getDuration(),
			'item_filesize' => $this->playlistMetricsCalculator->getFileSize()
		];
		$this->itemsRepository->updateWithWhere($saveItem, ['file_resource' => $playlistId]);
	}

	/**
	 *
	 * 1. Save metrics in exported playlist (did in export-class)
	 * 2. Save metrics in all items that represent this playlist. (did in export-class)
	 * 3. find all playlists that have the exported playlist nested (here we go)
	 * 4. calculate metrics for the new playlists
	 * 5. save metrics in new playlists
	 * 6. save metrics in all items that represent the new playlist
	 * 7. recurse to 3.
	 *
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function updateMetricsRecursively(int $playlistId): void
	{
		$tmp = $this->itemsRepository->findAllPlaylistsContainingPlaylist($playlistId);
		foreach($tmp as $playlistData)
		{
			$this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData);
			$this->updateItemsMetrics($playlistData['playlist_id']);
			$this->updatePlaylistMetrics($playlistData['playlist_id']);

			$this->updateMetricsRecursively($playlistData['playlist_id']);
		}
	}

	/**
	 * @throws Exception
	 */
	private function updatePlaylistMetrics(int $playlistId): void
	{
		$this->playlistsService->update($playlistId, $this->playlistMetricsCalculator->getMetricsForPlaylistTable());
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 */
	private function checkPlaylistAcl(int $playlistId): array
	{
		$this->playlistsService->setUID($this->UID);
		/** @var array<string,mixed> $playlistData */
		$playlistData = $this->playlistsService->loadPlaylistForEdit($playlistId); // also checks rights
		if (empty($playlistData))
			throw new ModuleException('items', 'Playlist is not accessible');

		return $playlistData;
	}


	/**
	 * @return array<string,mixed>
	 */
	private function sanitize(string $value): array
	{
		if ($value === '')
			return [];

		return unserialize($value);
	}
}