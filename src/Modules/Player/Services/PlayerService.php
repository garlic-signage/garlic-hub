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

namespace App\Modules\Player\Services;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * PlayerService class provides functionalities for managing players, including updating player information,
 * replacing master playlists, and fetching player data with access control validation.
 *
 * This service interacts with the PlayerRepository for data persistence, the PlaylistsService for handling
 * associated playlists, and utilizes an AclValidator for enforcing access control logic.
 */
class PlayerService extends AbstractBaseService
{
	private readonly PlayerRepository $playerRepository;
	private readonly PlaylistsService $playlistService;
	private readonly AclValidator $playerValidator;

	public function __construct(PlayerRepository $playerRepository, PlaylistsService $playlistService, AclValidator $playerValidator, LoggerInterface $logger)
	{
		$this->playerRepository = $playerRepository;
		$this->playlistService  = $playlistService;
		$this->playerValidator  = $playerValidator;

		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function findAllForDashboard(): array
	{
		$ret = $this->playerRepository->findAllForDashboard();
		if (empty($ret))
			$ret = ['active' => 0, 'inactive' => 0, 'pending' => 0];

		return $ret;
	}

	/**
	 * @param array{api_endpoint:string, is_intranet: int} $saveData
	 * @throws Exception
	 */
	public function updatePlayer(int $playerId, array $saveData): int
	{
		return $this->playerRepository->update($playerId, $this->collectData($saveData));
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function delete(int $playerId): int
	{
		$player = $this->fetchAclCheckedPlayerData($playerId);
		if ($player === [])
			return 0;

		return 1;
		return $this->playerRepository->delete($playerId);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function replaceMasterPlaylist(int $playerId, int $playlistId): array
	{
		try
		{
			$player = $this->fetchAclCheckedPlayerData($playerId);
			if ($player === [])
				return []; // error is handled in fetchAclCheckedPlayerData

			$playlistName = '';
			if ($playlistId > 0)
			{
				$this->playlistService->setUID($this->UID);
				$playlist = $this->playlistService->loadPureById($playlistId);
				if ($playlist['playlist_mode'] !==  PlaylistMode::MASTER->value && $playlist['playlist_mode'] !==  PlaylistMode::MULTIZONE->value)
					throw new ModuleException('player', $playlist['playlist_name'] . ' is not a master playlist');

				$playlistName = $playlist['playlist_name'];
			}
			$affected = $this->playerRepository->update($playerId, ['playlist_id' => $playlistId]);

			$is_intranet = $playlist['is_intranet'] ?? 0;

			return ['affected' => $affected, 'playlist_name' => $playlistName, 'is_intranet' => $is_intranet];
		}
		catch (Throwable $e)
		{
			$this->logger->error($e->getMessage());
			$this->addErrorMessage($e->getMessage());
			return [];
		}
	}

	/**
	 * @param int $playerId
	 * @return array<string,mixed>|array<empty,empty>
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function fetchAclCheckedPlayerData(int $playerId): array
	{
		$player = $this->playerRepository->findFirstById($playerId);
		if ($player === [])
		{
			$this->logger->error('Error loading player: '.$playerId.' is not found.');
			$this->addErrorMessage('Error loading player: '.$playerId.' is not found.');

			return [];
		}

		/** @var array{UID: int, company_id: int, player_id: int, ...} $player */
		if (!$this->playerValidator->isPlayerEditable($this->UID, $player))
		{
			$this->logger->error('Error loading player: '.$playerId.' is not editable for User '.$this->UID.'.');
			return [];
		}

		return $player;
	}

	/**
	 * @param array<string, int|string> $saveData
	 * @return array<string, int|string>
	 */
	private function collectData(array $saveData): array
	{
		$ret = [];
		if (isset($saveData['api_endpoint']))
			$ret['api_endpoint'] = $saveData['api_endpoint'];
		if (isset($saveData['is_intranet']))
			$ret['is_intranet'] = $saveData['is_intranet'];

		return $ret;
	}

}