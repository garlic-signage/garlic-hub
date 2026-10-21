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
use App\Modules\Playlists\Helper\Compose\ItemThumbnailResolver;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**+
 * Todo Concept:
 * Adding, deleting or duration change playlist needs to check for time limit directly
 * Exporting will set recursively item times and should stop, if a higher leveled playlist time limit will exceed
 */
class ItemsService extends AbstractBaseService
{

	public function __construct(private readonly ItemsRepository           $itemsRepository,
								private readonly MediaService              $mediaService,
								private readonly PlaylistsService          $playlistsService,
								private readonly PlaylistMetricsCalculator $playlistMetricsCalculator,
								private readonly ItemThumbnailResolver     $itemThumbnailResolver,
								LoggerInterface                            $logger)
	{
		parent::__construct($logger);
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
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function fetchItemById(int $itemId): array
	{
		$item = $this->itemsRepository->findFirstById($itemId);
		if ($item === [])
			throw new ModuleException('items', 'Item not found');

		/** @var array<string,mixed> $item */

		$destinationPlaylist = $this->playlistsService->loadPureById($item['playlist_id']); // check rights

		switch ($item['item_type'])
		{
			case ItemType::MEDIAPOOL->value:
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
				$defaultDuration = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->duration;
				if ($playlist['time_limit'] > 0 && $defaultDuration > $playlist['time_limit'])
					$item['default_duration'] = $playlist['time_limit'];
				else
					$item['default_duration'] = $defaultDuration;
				break;
			case ItemType::TEMPLATE->value:
				$item['playlist_name'] = $destinationPlaylist['playlist_name'];
			default:
				$item['default_duration'] = $this->playlistMetricsCalculator->getDefaultDuration();
		}

		return $item;
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loadItemsByPlaylistIdForComposer(int $playlistId): array
	{

		$playlistData  = $this->playlistsService->loadPureById($playlistId); // checks rights, too
		$items         = [];
		$result        = $this->itemsRepository->findAllByPlaylistId($playlistId);

		foreach($result as $value)
		{
			$thumbnail = $this->itemThumbnailResolver->resolve($value);
			if ($thumbnail === null)
				continue;

			$value['paths']['thumbnail'] = $thumbnail['path'];
			$value['urls']['thumbnail']  = $thumbnail['url'];
			$items[] = $value;
		}

		$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->toFrontendArray();

		return ['playlist_metrics' =>  $playlistMetrics, 'playlist' => $playlistData, 'items' => $items];
	}

	/**
     */
	public function updateField(mixed $itemId, string $fieldName, string|int $fieldValue): int
	{
		$saveData = [strip_tags($fieldName) => strip_tags((string)$fieldValue)];

		return $this->update($itemId, $saveData);
	}

    /**
     * @param int $itemId
     * @param array<string,mixed> $saveData
     * @return int
     * @throws CoreException
     * @throws Exception
     * @throws FrameworkException
     * @throws ModuleException
     */
	public function update(int $itemId, array $saveData): int
	{
		$item = $this->itemsRepository->findFirstById($itemId);
		if ($item === [])
			return 0;

		$this->playlistsService->loadPureById($item['playlist_id']); // will check for rights

		return $this->itemsRepository->update($itemId, $saveData);
	}


	/**
	 * @return int|null the stored duration, which can be shorter than the wanted one because of time limits,
	 *                  null when the item could not be updated
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function updateItemDuration(int $itemId, int $wantedDuration): ?int
	{
		$item = $this->itemsRepository->findFirstById($itemId);
		if ($item === [])
			return null;

		$playlist = $this->playlistsService->loadPureById($item['playlist_id']); // will check for rights
		// if an item is a playlist, we need to get this limit first
		if ($item['item_type'] === ItemType::PLAYLIST->value)
		{
			$itemPlaylist = $this->playlistsService->fetchById((int) $item['file_resource']);
			$wantedDuration   = $this->playlistMetricsCalculator->calculateRemainingDuration($itemPlaylist, $wantedDuration);
		}

		$itemDuration = $this->playlistMetricsCalculator->calculateRemainingDuration($playlist, $wantedDuration);
		if ($this->itemsRepository->update($itemId, ['item_duration' => $itemDuration]) === 0)
			return null;

		return $itemDuration;
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
		catch (Exception | ModuleException | CoreException $e)
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

			$playlistMetrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlistData)->toFrontendArray();

			$this->itemsRepository->commitTransaction();

			return ['playlist_metrics' => $playlistMetrics, 'delete_id' => $deleteId];
		}
		catch (Exception | ModuleException | CoreException $e)
		{
			$this->itemsRepository->rollBackTransaction();
			$this->logger->error('Error delete item: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 */
	private function checkPlaylistAcl(int $playlistId): array
	{
		/** @var array<string,mixed> $playlistData */
		$playlistData = $this->playlistsService->loadPlaylistForEdit($playlistId); // also checks rights
		if (empty($playlistData))
			throw new ModuleException('items', 'Playlist is not accessible');

		return $playlistData;
	}
}