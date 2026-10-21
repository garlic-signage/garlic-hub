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
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;

/**
 * Calculates the metrics of a playlist. The calculator keeps no state,
 * every calculation returns its own PlaylistMetrics.
 */
class PlaylistMetricsCalculator
{
	private readonly ItemsRepository $itemsRepository;
	private readonly AclValidator $aclValidator;
	private readonly Config $config;
	private readonly UserSession $userSession;

	public function __construct(ItemsRepository $itemsRepository, AclValidator $aclValidator, Config $config, UserSession $userSession)
	{
		$this->itemsRepository     = $itemsRepository;
		$this->aclValidator     = $aclValidator;
		$this->config           = $config;
		$this->userSession      = $userSession;
	}

    /**
     * @return int
     */
	public function getDefaultDuration(): int
	{
		return (int) $this->config->getConfigValue('duration', 'playlists', 'Defaults');
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param list<array<string,mixed>> $items
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function calculateFromItems(array $playlist, array $items): PlaylistMetrics
	{
		$countOwnerEntries = 0;
		$fileSize          = 0;
		$duration          = 0;
		$ownerDuration     = 0;

		foreach ($items as $item)
		{
			$fileSize += $item['item_filesize'];
			$duration += (int) round($item['item_duration']);

			if ($playlist['UID'] === $item['UID'])
			{
				$countOwnerEntries++;
				$ownerDuration += (int) round($item['item_duration']);
			}
		}

		return $this->createMetrics($playlist, count($items), $countOwnerEntries, $fileSize, $duration, $ownerDuration);
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function calculateFromPlaylistData(array $playlist): PlaylistMetrics
	{
		// nothing to do?
		// sometimes we get an item just with default values, but no playlist id, so ignore them as well
		if (empty($playlist) || !isset($playlist['playlist_id']))
			return new PlaylistMetrics();

		$tmp = $this->itemsRepository->sumAndCountMetricsByPlaylistIdAndOwner($playlist['playlist_id'], $playlist['UID']);

		return $this->createMetrics(
			$playlist,
			$tmp['count_items'] ?? 0,
			$tmp['count_owner_items'] ?? 0,
			$tmp['filesize'] ?? 0,
			(int) $tmp['duration'],
			(int) $tmp['owner_duration'] // because some videos are floats!
		);
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param array<string,mixed> $media
	 * @throws CoreException
	 * @throws Exception|ModuleException
	 * @throws UserException
	 */
	public function calculateRemainingMediaDuration(array $playlist, array $media = []): int
	{
		// only videos / audio have a duration > 0. Using the constant for other media.
		// this is only used when inserting new items
		$duration = (array_key_exists('duration', $media['metadata']) && $media['metadata']['duration'] > 0) ? $media['metadata']['duration'] : $this->getDefaultDuration();

		return $this->calculateRemainingDuration($playlist, (int) round($duration));
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function calculateRemainingDuration(array $playlist, int $wantedDuration): int
	{
		if ($playlist['time_limit'] > 0 && !$this->aclValidator->isSimpleAdmin($this->userSession->getUID()))
		{
			$ownerDuration      = $this->calculateFromPlaylistData($playlist)->ownerDuration;
			$remaining_duration = $playlist['time_limit'] - $ownerDuration;

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
	 * @throws UserException
	 */
	private function exceedTimeLimit(array $playlist, int $ownerDuration): bool
	{
		if ($playlist['time_limit'] > 0 &&
			$ownerDuration > $playlist['time_limit'] &&
			!$this->aclValidator->isSimpleAdmin($this->userSession->getUID())) // Check more intensely for real admin needs company_id
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
	 * @throws UserException
	 */
	private function createMetrics(array $playlist, int $countEntries, int $countOwnerEntries, int $fileSize, int $duration, int $ownerDuration): PlaylistMetrics
	{
		// calculate average durations if shuffle only when results to avoid division through 0.
		if ($playlist['shuffle'] > 0 && $countEntries > 0)
			[$duration, $ownerDuration] = $this->adjustForShuffle($playlist['shuffle_picking'], $countEntries, $countOwnerEntries, $duration, $ownerDuration);

		if ($this->exceedTimeLimit($playlist, $ownerDuration))
			throw new ModuleException('playlist_export',
				'Exceeds time limit '.$playlist['time_limit'].'s of playlist: '.$playlist['name']);

		return new PlaylistMetrics($countEntries, $countOwnerEntries, $fileSize, $duration, $ownerDuration);
	}

	/**
	 * @return array{0:int, 1:int} duration and owner duration
	 */
	private function adjustForShuffle(int $shufflePicking, int $countEntries, int $countOwnerEntries, int $duration, int $ownerDuration): array
	{
		if ($shufflePicking === 0 || $shufflePicking > $countEntries)
			$shufflePicking = $countEntries;

		$duration = (int) round($duration / $countEntries * $shufflePicking);

		if ($countOwnerEntries === 0)
			return [$duration, 0];

		$avgOwnerItemDuration = $ownerDuration / $countOwnerEntries;
		$probability          = $countOwnerEntries / $countEntries;
		$expectedOwnerItems   = $probability * max($shufflePicking, 1);

		// secure owner duration cannot be higher than avg duration
		return [$duration, min((int) round($avgOwnerItemDuration * $expectedOwnerItems), $duration)];
	}

}