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

namespace App\Modules\Playlists\Services;

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\Versioning\UnitVersions;
use Doctrine\DBAL\Exception;
use JsonException;
use Throwable;

/**
 * The versions of a playlist: every export records the exported state, the settings of the playlist and its items.
 * A restore puts the items back with their ids, as triggers, conditional play and the images of
 * template items point to them. Items whose media or nested playlist is gone are left out.
 */
class PlaylistVersionsService
{
	private const string MODULE_NAME = 'playlists';
	// what the composer changes, not the name, the owner or the mode
	private const array PLAYLIST_FIELDS = ['time_limit', 'shuffle', 'shuffle_picking', 'layout', 'external_playlist_link'];

	public function __construct(
		private readonly PlaylistsService $playlistsService,
		private readonly PlaylistsRepository $playlistsRepository,
		private readonly ItemsRepository $itemsRepository,
		private readonly UnitVersions $unitVersions,
		private readonly Transactions $transactions,
		private readonly UserSession $userSession,
		private readonly AuditLog $auditLog
	) {}

	public function isEnabled(): bool
	{
		return $this->unitVersions->isEnabled();
	}

	/**
	 * @param array<string,mixed> $playlist the exported playlist
	 * @throws Exception
	 * @throws JsonException
	 * @throws UserException
	 */
	public function recordExport(array $playlist): void
	{
		$this->unitVersions->record(self::MODULE_NAME, (int) $playlist['playlist_id'], $this->userSession->getUID(), $this->snapshot($playlist));
	}

	/**
	 * @return list<array{version:int, created_at:string, username:string}> the newest first
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function findVersions(int $playlistId): array
	{
		$this->playlistsService->loadWithUserById($playlistId);

		return $this->unitVersions->findVersions(self::MODULE_NAME, $playlistId);
	}

	/**
	 * The current state becomes a version first: a restore can be undone. The playlist is not exported.
	 *
	 * @return list<string> the names of the items which were left out
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws Throwable
	 * @throws UserException
	 */
	public function restoreVersion(int $playlistId, int $version): array
	{
		$playlist = $this->playlistsService->loadWithUserById($playlistId);
		$snapshot = $this->unitVersions->findSnapshot(self::MODULE_NAME, $playlistId, $version);
		if ($snapshot === null || !is_array($snapshot['playlist'] ?? null) || !is_array($snapshot['items'] ?? null))
			throw new NotFoundException(self::MODULE_NAME, 'version_not_found', ['playlist_id' => $playlistId, 'version' => $version]);

		$this->transactions->begin();
		try
		{
			$this->unitVersions->record(self::MODULE_NAME, $playlistId, $this->userSession->getUID(), $this->snapshot($playlist));

			[$items, $omitted] = $this->splitItems($playlistId, array_values(array_filter($snapshot['items'], 'is_array')));
			$this->itemsRepository->deleteBy(['playlist_id' => $playlistId]);
			$this->insertItems($playlistId, $items);
			$this->playlistsRepository->update($playlistId, array_intersect_key($snapshot['playlist'], array_flip(self::PLAYLIST_FIELDS)));

			$this->auditLog->record($this->userSession->getUID(), self::MODULE_NAME, AuditAction::VERSION_RESTORED, $playlistId, null, ['version' => $version]);
			$this->transactions->commit();
		}
		catch (Throwable $e)
		{
			$this->transactions->rollBack();
			throw $e;
		}

		return $omitted;
	}

	/**
	 * The versions go with the playlist.
	 *
	 * @throws Exception
	 */
	public function removeVersions(int $playlistId): void
	{
		$this->unitVersions->removeUnits(self::MODULE_NAME, [$playlistId]);
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array{playlist: array<string,mixed>, items: list<array<string,mixed>>}
	 * @throws Exception
	 */
	private function snapshot(array $playlist): array
	{
		$items = [];
		foreach ($this->itemsRepository->findAllByPlaylistId((int) $playlist['playlist_id']) as $item)
		{
			// the database sets it
			unset($item['last_update']);
			$items[] = $item;
		}

		return ['playlist' => array_intersect_key($playlist, array_flip(self::PLAYLIST_FIELDS)), 'items' => $items];
	}

	/**
	 * Media which are marked as deleted still exist. Templates items have their own content, external
	 * resources are not checked.
	 *
	 * @param list<array<mixed>> $items
	 * @return array{list<array<mixed>>, list<string>} the items to restore and the names of the ones left out
	 * @throws Exception
	 */
	private function splitItems(int $playlistId, array $items): array
	{
		$checksums   = [];
		$playlistIds = [];
		foreach ($items as $item)
		{
			if (($item['item_type'] ?? '') === ItemType::MEDIAPOOL->value)
				$checksums[] = (string) ($item['file_resource'] ?? '');
			elseif (($item['item_type'] ?? '') === ItemType::PLAYLIST->value)
				$playlistIds[] = (int) ($item['file_resource'] ?? 0);
		}

		$existingChecksums = $this->itemsRepository->findExistingChecksums(array_values(array_unique($checksums)));
		$existingPlaylists = $this->playlistsRepository->findExistingIds(array_values(array_unique($playlistIds)));

		$kept    = [];
		$omitted = [];
		foreach ($items as $item)
		{
			$exists = match ($item['item_type'] ?? '')
			{
				ItemType::MEDIAPOOL->value => in_array((string) ($item['file_resource'] ?? ''), $existingChecksums, true),
				ItemType::PLAYLIST->value  => (int) ($item['file_resource'] ?? 0) !== $playlistId && in_array((int) ($item['file_resource'] ?? 0), $existingPlaylists, true),
				default                    => true
			};

			if ($exists)
				$kept[] = $item;
			else
				$omitted[] = is_scalar($item['item_name'] ?? null) ? (string) $item['item_name'] : '';
		}

		return [$kept, $omitted];
	}

	/**
	 * In the order of the snapshot, without gaps of the left out items.
	 *
	 * @param list<array<mixed>> $items
	 * @throws Exception
	 */
	private function insertItems(int $playlistId, array $items): void
	{
		$itemIds = array_map(static fn(array $item): int => is_numeric($item['item_id'] ?? null) ? (int) $item['item_id'] : 0, $items);
		$taken   = $this->itemsRepository->findItemIdsOfOtherPlaylists($playlistId, array_values(array_filter($itemIds)));
		// the positions count from the first one of the snapshot
		$orders  = array_map(static fn(array $item): int => is_numeric($item['item_order'] ?? null) ? (int) $item['item_order'] : 0, $items);
		$first   = $orders === [] ? 0 : min($orders);

		foreach ($items as $position => $item)
		{
			$row = [];
			foreach ($item as $field => $value)
			{
				if (is_string($field))
					$row[$field] = $value;
			}
			$row['playlist_id'] = $playlistId;
			$row['item_order']  = $first + $position;
			if (in_array($itemIds[$position], $taken, true) || $itemIds[$position] === 0)
				unset($row['item_id']);

			$this->itemsRepository->insert($row);
		}
	}
}
