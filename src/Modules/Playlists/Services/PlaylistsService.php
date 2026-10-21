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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Framework\Helper\Serialization;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Repositories\PlaylistsRepository;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class PlaylistsService extends AbstractBaseService
{
	public function __construct(private readonly PlaylistsRepository $playlistsRepository,
								private readonly PlaylistMetricsCalculator $playlistMetricsCalculator,
								private readonly AclValidator $aclValidator,
								private readonly UserSession $userSession,
								LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @param array<string,mixed> $postData
	 * @throws Exception
	 * @throws UserException
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
		$playlistMetrics     =  $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->toFrontendArray();

		return ['affected' => $affected, 'playlist_metrics' => $playlistMetrics];

	}

	/**
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function shufflePicking(int $playlistId, int $shufflePicking): array
	{
		$playlist                    = $this->loadPureById($playlistId);
		$affected                    = $this->update($playlist['playlist_id'], ['shuffle_picking' => $shufflePicking]);
		$playlist['shuffle_picking'] = $shufflePicking; // prevent reloading playlist with new values
		$playlistMetrics             =  $this->playlistMetricsCalculator->calculateFromPlaylistData($playlist)->toFrontendArray();

		return ['affected' => $affected, 'playlist_metrics' => $playlistMetrics];
	}

	/**
	 * @param array<string,mixed> $postData
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
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
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function delete(int $playlistId): int
	{
		$this->loadWithUserById($playlistId);

		return $this->playlistsRepository->delete($playlistId);
	}

	/**
	 * @return array<string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadPlaylistForMultizone(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_id: int, layout:string, ...} $playlist */
		$playlist = $this->loadWithUserById($playlistId);

		// new playlists have no layout yet
		return Serialization::unserializeSecure($playlist['layout']);
	}

	/**
	 * @param int $playlistId
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 */
	public function loadPureById(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_id: int, ...} $playlist */
		$playlist = $this->fetchById($playlistId); // the playlist is sure not empty here

		if (!$this->aclValidator->isPlaylistEditable($this->userSession->getUID(), $playlist))
			throw new AccessDeniedException('playlists', 'playlist_not_editable', ['playlist_id' => $playlistId]);

		return $playlist;
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 * @throws NotFoundException
	 */
	public function fetchById(int $playlistId): array
	{
		/** @var array<string,mixed> $playlist */
		$playlist = $this->playlistsRepository->findFirstBy(['playlist_id' => $playlistId]);
		if ($playlist === [])
			throw new NotFoundException('playlists', 'playlist_not_found', ['playlist_id' => $playlistId]);

		return $playlist;
	}

	/**
	 * @return array{UID: int, company_id: int, playlist_id: int, ...}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws AccessDeniedException
	 * @throws NotFoundException
	 */
	public function loadWithUserById(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_id: int, ...} $playlist */
		$playlist = $this->playlistsRepository->findFirstWithUserName($playlistId);
		if ($playlist === [])
			throw new NotFoundException('playlists', 'playlist_not_found', ['playlist_id' => $playlistId]);

		if (!$this->aclValidator->isPlaylistEditable($this->userSession->getUID(), $playlist))
			throw new AccessDeniedException('playlists', 'playlist_not_editable', ['playlist_id' => $playlistId]);

		return $playlist;
	}

	/**
	 * @return array{playlist_id:int, name:mixed}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadNameById(int $playlistId): array
	{
		$playlist = $this->loadPureById($playlistId);

		return ['playlist_id' => $playlistId, 'name' => $playlist['playlist_name']];
	}

	/**
	 * @param array<string,mixed> $zones
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function saveZones(int $playlistId, array $zones): int
	{
		$playlist = $this->loadWithUserById($playlistId);
		if ($zones === [])
			return 0;

		return $this->playlistsRepository->update($playlist['playlist_id'], ['layout' => serialize($zones)]);
	}

	/**
	 * @return array{"UID": int, "company_id": int, playlist_mode: string,...}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadPlaylistForEdit(int $playlistId): array
	{
		/** @var array{UID: int, company_id: int, playlist_mode: string, ...} $playlist */
		$playlist = $this->loadWithUserById($playlistId);
		$layout = Serialization::unserializeSecure($playlist['layout'] ?? null);
		if ($layout !== [])
		{
			$exploded = explode('x', $layout['resolution']);
			$playlist['screen_width'] = (int) $exploded[0];
			$playlist['screen_height'] = (int) $exploded[1];
			$playlist['layout'] = $layout;
		}
		else
			$playlist['layout'] = [];

		return $playlist;
	}

	/**
	 * @param array<string,mixed> $postData
	 * @return array<string,mixed>
	 * @throws UserException
	 */
	private function collectDataForInsert(array $postData): array
	{
		if (array_key_exists('UID', $postData))
			$saveData['UID'] = $postData['UID'];
		else
			$saveData['UID'] = $this->userSession->getUID();

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

		if (isset($postData['layout']))
			$saveData['layout'] = $postData['layout'];

		return $saveData;
	}
}