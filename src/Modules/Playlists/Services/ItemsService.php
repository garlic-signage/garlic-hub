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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMetrics;
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
								private readonly MimeTypeExtensionMapper   $mimeTypeExtensionMapper,
								private readonly MediaUrlSigner            $mediaUrlSigner,
								private readonly Config                    $config,
								LoggerInterface                            $logger)
	{
		parent::__construct($logger);
	}

	public function getItemsRepository(): ItemsRepository
	{
		return $this->itemsRepository;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array{playlist_metrics: PlaylistMetrics, items: list<array<string,mixed>>}
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
			switch ($value['item_type'])
			{
				case ItemType::MEDIAPOOL->value:
					$thumbnailPath = $this->mediaService->getPathThumbnails();

					$ext = 'jpg';
					if (str_starts_with($value['mimetype'], 'image/'))
						$ext = $this->mimeTypeExtensionMapper->determineExtension($value['mimetype']);

					$filename = $value['file_resource'];
					$isSvgWidget = ($value['mimetype'] === 'application/widget' && $value['content_data'] == '');
					$finalExt = $isSvgWidget ? 'svg' : $ext;

					// ugly workaround fix this later.
					if ($value['mimetype'] === 'application/widget'
						&& !file_exists($thumbnailPath . '/' . $filename . '.' . $finalExt))
						$finalExt = 'png';

					$tmp = $value;
					$tmp['paths']['thumbnail'] = $thumbnailPath . '/' . $filename . '.' . $finalExt;
					// mediapool files are stored outside the document root
					$tmp['urls']['thumbnail'] = $this->mediaUrlSigner->thumbUrl($filename, $finalExt);

					$items[] = $tmp;
					break;
				case ItemType::PLAYLIST->value:
					$tmp = $value;
					$tmp['paths']['thumbnail'] = 'public/images/icons/playlist.svg';
					$tmp['urls']['thumbnail']  = '/images/icons/playlist.svg';
					$items[] = $tmp;
					break;
				case ItemType::TEMPLATE->value:
					$tmp = $value;
					// thumbnails are always created as jpg, whatever mimetype the rendered original has
					$thumbDir = $this->config->getConfigValue('thumbnails', 'playlists', 'directories');
					$tmp['paths']['thumbnail'] = $thumbDir . '/' . $value['item_id'].'.jpg';
					$tmp['urls']['thumbnail']  = $this->mediaUrlSigner->itemThumbUrl((int) $value['item_id'], 'jpg');
					$items[] = $tmp;
					break;

			}
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
	 * @throws Exception
	 */
	public function updateItemsMetrics(int $playlistId, PlaylistMetrics $metrics): void
	{
		$this->itemsRepository->updateWithWhere($metrics->toItemArray(), ['file_resource' => $playlistId]);
	}

	/**
	 *
	 * 1. Save metrics in the exported playlist (did in export-class)
	 * 2. Save metrics in all items that represent this playlist. (did in export-class)
	 * 3. Collect all playlists that have the exported playlist nested, on every level
	 * 4. Calculate and save the metrics of each of them once, a playlist only
	 *    after all of its nested playlists are updated
	 *
	 * A playlist reachable on several paths is calculated only once. Playlists in a
	 * nesting cycle are not updated, but logged, instead of recursing endlessly.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function updateMetricsRecursively(int $playlistId): void
	{
		[$parents, $playlists] = $this->collectContainingPlaylists($playlistId);

		// count for every containing playlist the nested playlists which must be updated before
		// the playlist itself is already updated, so a nesting back to it is a cycle
		$pending = [];
		$cyclic  = [];
		foreach ($parents as $parentIds)
		{
			foreach ($parentIds as $parentId)
			{
				if ($parentId === $playlistId)
					$cyclic[] = $playlistId;
				else
					$pending[$parentId] = ($pending[$parentId] ?? 0) + 1;
			}
		}

		$updated = [$playlistId];
		while ($updated !== [])
		{
			$childId = array_shift($updated);
			foreach ($parents[$childId] as $parentId)
			{
				if ($parentId === $playlistId || --$pending[$parentId] !== 0)
					continue;

				$metrics = $this->playlistMetricsCalculator->calculateFromPlaylistData($playlists[$parentId]);
				$this->updateItemsMetrics($parentId, $metrics);
				$this->updatePlaylistMetrics($parentId, $metrics);
				$updated[] = $parentId;
			}
		}

		$cyclic = array_unique([...$cyclic, ...array_keys(array_filter($pending, fn(int $count) => $count > 0))]);
		if ($cyclic !== [])
			$this->logger->warning('Metrics not updated for playlists in a nesting cycle: ' . implode(', ', $cyclic));
	}

	/**
	 * Finds all playlists which contain the given playlist directly or nested,
	 * querying each playlist only once.
	 *
	 * @return array{0: array<int,list<int>>, 1: array<int,array<string,mixed>>}
	 *         parent ids by playlist id, and the data of the containing playlists
	 * @throws Exception
	 */
	private function collectContainingPlaylists(int $playlistId): array
	{
		$parents   = [];
		$playlists = [];
		$queue     = [$playlistId];
		while ($queue !== [])
		{
			$childId = array_shift($queue);
			if (isset($parents[$childId]))
				continue;

			$parents[$childId] = [];
			foreach ($this->itemsRepository->findAllPlaylistsContainingPlaylist($childId) as $playlistData)
			{
				$parentId = (int) $playlistData['playlist_id'];
				$parents[$childId][]  = $parentId;
				$playlists[$parentId] = $playlistData;
				$queue[]              = $parentId;
			}
		}

		return [$parents, $playlists];
	}

	/**
	 * @throws Exception
	 */
	private function updatePlaylistMetrics(int $playlistId, PlaylistMetrics $metrics): void
	{
		$this->playlistsService->update($playlistId, $metrics->toPlaylistTableArray());
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