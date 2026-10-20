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
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;

/**
 * class for calculating and manipulating durations of
 * playlist
 */
class PlaylistMetricsCalculator
{
	private readonly ItemsRepository $itemsRepository;
	private readonly AclValidator $aclValidator;
	private readonly Config $config;
	private int $UID;
	private int $countEntries;
	private int $countOwnerEntries;
	private int $ownerDuration;
	private int $duration;
	private int $fileSize;

	public function __construct(ItemsRepository $itemsRepository, AclValidator $aclValidator, Config $config)
	{
		$this->itemsRepository     = $itemsRepository;
		$this->aclValidator     = $aclValidator;
		$this->config           = $config;
	}

	/**
	 * @throws CoreException
	 */
	public function getDefaultDuration(): int
	{
		return (int) $this->config->getConfigValue('duration', 'playlists', 'Defaults');
	}

	public function getCountEntries(): int
	{
		return $this->countEntries;
	}

	public function getDuration(): int
	{
		return $this->duration;
	}

	public function getOwnerDuration(): int
	{
		return $this->ownerDuration;
	}

	public function getFileSize(): int
	{
		return $this->fileSize;
	}

	public function setUID(int $UID): static
	{
		$this->UID = $UID;
		return $this;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMetricsForFrontend(): array
	{
		return [
			'count_items'       => $this->getCountEntries(),
			'count_owner_items' => $this->countOwnerEntries,
			'filesize'          => $this->getFileSize(),
			'duration'          => $this->getDuration(),
			'owner_duration'    => $this->getOwnerDuration()
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getMetricsForPlaylistTable(): array
	{
		return [
			'filesize'          => $this->getFileSize(),
			'duration'          => $this->getDuration(),
			'owner_duration'    => $this->getOwnerDuration()
		];
	}


	public function reset(): static
	{
		$this->countEntries      = 0;
		$this->countOwnerEntries = 0;
		$this->fileSize          = 0;
		$this->duration          = 0;
		$this->ownerDuration     = 0;

		return $this;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param list<array<string,mixed>> $items
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function calculateFromItems(array $playlist, array $items): static
	{
		$this->reset();
		$this->countEntries = count($items);

		foreach ($items as $item)
		{
			$this->fileSize += $item['item_filesize'];
			$this->duration += (int) round($item['item_duration']);

			if ($playlist['UID'] === $item['UID'])
			{
				$this->countOwnerEntries++;
				$this->ownerDuration += (int) round($item['item_duration']);
			}
		}

		$this->calculateAverageDuration($playlist);

		return $this;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function calculateFromPlaylistData(array $playlist): static
	{
		// nothing to do?
		// sometimes we get an item just with default values, but no playlist id, so ignore them as well
		if (empty($playlist) || !isset($playlist['playlist_id']))
		{
			$this->reset();
			return $this;
		}

		$tmp = $this->itemsRepository->sumAndCountMetricsByPlaylistIdAndOwner($playlist['playlist_id'], $playlist['UID']);

		$this->countEntries      = $tmp['count_items'] ?? 0;
		$this->countOwnerEntries = $tmp['count_owner_items'] ?? 0;
		$this->fileSize          = $tmp['filesize'] ?? 0;
		$this->duration          = (int) $tmp['duration'];
		$this->ownerDuration     = (int) $tmp['owner_duration']; // because some videos are floats!

		$this->calculateAverageDuration($playlist);

		return $this;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param array<string,mixed> $media
	 * @throws CoreException
	 * @throws Exception|ModuleException
	 */
	public function calculateRemainingMediaDuration(array $playlist, array $media = []): int
	{
		// only videos / audio have a duration > 0. Using the constant for other media.
		// this is only used, when inserting new items
		$duration = (array_key_exists('duration', $media['metadata']) && $media['metadata']['duration'] > 0) ? $media['metadata']['duration'] : $this->getDefaultDuration();

		return $this->calculateRemainingDuration($playlist, (int) round($duration));
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function calculateRemainingDuration(array $playlist, int $wantedDuration): int
	{
		if ($playlist['time_limit'] > 0 && !$this->aclValidator->isSimpleAdmin($this->UID))
		{
			$this->calculateFromPlaylistData($playlist);
			$remaining_duration = $playlist['time_limit'] - $this->getOwnerDuration();

			if ($remaining_duration <= 0)
				$wantedDuration = 0;
			elseif ($remaining_duration < $wantedDuration)
				$wantedDuration = $remaining_duration;
		}

		return (int) round($wantedDuration);
	}


	/**
	 * @param array<string,mixed> $playlist
	 * @throws CoreException
	 * @throws Exception
	 */
	private function exceedTimeLimit(array $playlist): bool
	{
		if ($playlist['time_limit'] > 0 &&
			$this->ownerDuration > $playlist['time_limit'] &&
			!$this->aclValidator->isSimpleAdmin($this->UID)) // Check more intense for real Admin needs company_id
		{
			return true;
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	private function calculateAverageDuration(array $playlist): void
	{
		// calculate average durations if shuffle only when results to avoid division through 0.
		if ($playlist['shuffle'] > 0 && $this->countEntries > 0)
			$this->adjustForShuffle($playlist['shuffle_picking']);

		if ($this->exceedTimeLimit($playlist))
			throw new ModuleException('playlist_export',
				'Exceeds time limit '.$playlist['time_limit'].'s of playlist: '.$playlist['name']);
	}

	private function adjustForShuffle(int $shufflePicking): void
	{
		if ($shufflePicking === 0 || $shufflePicking > $this->countEntries)
			$shufflePicking = $this->countEntries;

		$this->duration = (int) round($this->duration / $this->countEntries * $shufflePicking);

		if ($this->countOwnerEntries > 0)
		{
			$avgOwnerItemDuration = $this->ownerDuration / $this->countOwnerEntries;
			$probability          = $this->countOwnerEntries / $this->countEntries;
			$expectedOwnerItems   = $probability * max($shufflePicking, 1);

			// secure owner duration cannot be higher than avg duration
			$this->ownerDuration  = min((int) round($avgOwnerItemDuration * $expectedOwnerItems), $this->duration);
		}
		else
			$this->ownerDuration = 0;
	}

}