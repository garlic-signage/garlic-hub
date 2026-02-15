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
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;
use Throwable;

class PlaylistsService extends AbstractBaseService
{
	public function __construct(private readonly PlaylistsRepository $playlistsRepository,
								private readonly PlaylistMetricsCalculator $playlistMetricsCalculator,
								private readonly AclValidator $aclValidator,
								LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @throws Exception
	 */
	public function createNew(array $postData): int
	{
		$saveData = $this->collectDataForInsert($postData);
		// No acl checks required as every logged user can create playlists
		return (int) $this->playlistsRepository->insert($saveData);
	}


	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	public function toggleShuffle(int $playlistId): array
	{
		$playlist = $this->loadPureById($playlistId);

		if ($playlist['shuffle'] === 0)
			$saveData['shuffle'] = 1;
		else
			$saveData['shuffle'] = 0;

		$affected            = $this->update($playlistId, $saveData);
		$playlist['shuffle'] = $saveData['shuffle']; // prevent reloading playlist with new values
		$playlistMetrics     =  $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->getMetricsForFrontend();

		return ['affected' => $affected, 'playlist_metrics' => $playlistMetrics];

	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	public function shufflePicking(int $playlistId, int $shufflePicking): array
	{
		$playlist                    = $this->loadPureById($playlistId);
		$affected                    = $this->update($playlist['playlist_id'], ['shuffle_picking' => $shufflePicking]);
		$playlist['shuffle_picking'] = $shufflePicking; // prevent reloading playlist with new values
		$playlistMetrics             =  $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->getMetricsForFrontend();

		return ['affected' => $affected, 'playlist_metrics' => $playlistMetrics];
	}

	/**
	 * @param array<string,mixed> $postData
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	public function updateSecure(array $postData): int
	{
		$playlistId = $postData['playlist_id'];
		$this->loadWithUserById($playlistId);
		$saveData = $this->collectDataForUpdate($postData);

		return $this->update($playlistId, $saveData);
	}

	/**
	 * @param array<string,mixed> $saveData
	 * @throws Exception
	 */
	public function update(int $playlistId, array $saveData): int
	{
		return $this->playlistsRepository->update($playlistId, $saveData);
	}

	/**
	 * @param array<string,mixed> $saveData
	 * @throws Exception
	 */
	public function updateExport(int $playlistId, array $saveData): int
	{
		return (int) $this->playlistsRepository->updateExport($playlistId, $saveData);
	}

	/**
	 * @param int $playlistId
	 * @return int
	 */
	public function delete(int $playlistId): int
	{
		try
		{
			$this->loadWithUserById($playlistId);

			return $this->playlistsRepository->delete($playlistId);
		}
		catch(Throwable $e)
		{
			$this->logger->error('Error delete playlist: '.$e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return 0;
		}

	}

	/**
	 * @return array<string,mixed>
	 */
	public function loadPlaylistForMultizone(int $playlistId): array
	{
		try
		{
			/** @var array{UID: int, company_id: int, playlist_id: int, multizone:string, ...} $playlist */
			$playlist = $this->loadWithUserById($playlistId);

			if ($playlist['multizone'] !== null)
				return unserialize($playlist['multizone']);

			return [];
		}
		catch(Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return [];
		}
	}

	/**
	 * @param int $playlistId
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function loadPureById(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_id: int, ...} $playlist */
		$playlist = $this->fetchById($playlistId); // the playlist is sure not empty here

		if (!$this->aclValidator->isPlaylistEditable($this->UID, $playlist))
			throw new ModuleException('playlists', 'Error loading playlist: Is not editable');

		return $playlist;
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws Exception
	 */
	public function fetchById(int $playlistId): array
	{
		/** @var array<string,mixed> $playlist */
		$playlist = $this->playlistsRepository->findFirstBy(['playlist_id' => $playlistId]);
		if ($playlist === [])
			throw new ModuleException('playlists', 'Error loading playlist. Playlist with Id: '.$playlistId.' not found');

		return $playlist;
	}

	/**
	 * @return array{UID: int, company_id: int, playlist_id: int, ...}
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function loadWithUserById(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_id: int, ...} $playlist */
		$playlist = $this->playlistsRepository->findFirstWithUserName($playlistId);
		if ($playlist === [])
			throw new ModuleException('playlists', 'No playlist with Id: '.$playlistId.' found.');

		if (!$this->aclValidator->isPlaylistEditable($this->UID, $playlist))
			throw new ModuleException('playlists', 'Playlist is not editable.');

		return $playlist;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function loadNameById(int $playlistId): array
	{
		try
		{
			$playlist = $this->loadPureById($playlistId);

			return ['playlist_id' => $playlistId, 'name' => $playlist['playlist_name']];
		}
		catch(Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return [];
		}
	}

	/**
	 * @param array<string,mixed> $zones
	 */
	public function saveZones(int $playlistId, array $zones): int
	{
		try
		{
			$count = 0;
			$playlist = $this->loadWithUserById($playlistId);

			if ($zones !== [])
				$count = $this->playlistsRepository->update($playlist['playlist_id'], ['multizone' => serialize($zones)]);

			return $count;
		}
		catch(Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return 0;
		}
	}

	/**
	 * @return array{"UID": int, "company_id": int, playlist_mode: string,...}|array<empty,empty>
	 */
	public function loadPlaylistForEdit(int $playlistId): array
	{
		try
		{
			return $this->loadWithUserById($playlistId);
		}
		catch(Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return [];
		}
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 */
	private function collectDataForInsert(array $postData): array
	{
		if (array_key_exists('UID', $postData))
			$saveData['UID'] = $postData['UID'];
		else
			$saveData['UID'] = $this->UID;

		$saveData['playlist_mode'] = $postData['playlist_mode'];

		return $this->collectCommon($postData, $saveData);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 */
	private function collectDataForUpdate(array $postData): array
	{
		$saveData = [];
		// only module admin is allowed to change UID
		if (array_key_exists('UID', $postData))
			$saveData['UID'] = $postData['UID'];

		return $this->collectCommon($postData, $saveData);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @param array<string,mixed> $saveData
	 * @return array<string,mixed>
	 */
	private function collectCommon(array $postData, array $saveData): array
	{
		if (isset($postData['playlist_name']))
			$saveData['playlist_name'] = $postData['playlist_name'];

		if (isset($postData['time_limit']))
			$saveData['time_limit'] = $postData['time_limit'];

		if (isset($postData['multizone']))
			$saveData['multizone'] = $postData['multizone'];

		return $saveData;
	}
}