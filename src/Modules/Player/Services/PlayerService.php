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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;

/**
 * PlayerService class provides functionalities for managing players, including updating player information,
 * replacing master playlists, and fetching player data with access control validation.
 *
 * This service interacts with the PlayerRepository for data persistence, the PlaylistsService for handling
 * associated playlists, and utilizes an AclValidator for enforcing access control logic.
 */
class PlayerService
{
	private readonly PlayerRepository $playerRepository;
	private readonly PlaylistsService $playlistService;
	private readonly AclValidator $playerValidator;
	private readonly UserSession $userSession;

	public function __construct(PlayerRepository $playerRepository, PlaylistsService $playlistService, AclValidator $playerValidator, UserSession $userSession)
	{
		$this->playerRepository = $playerRepository;
		$this->playlistService  = $playlistService;
		$this->playerValidator  = $playerValidator;
		$this->userSession     = $userSession;
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
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function delete(int $playerId): int
	{
		$this->fetchAclCheckedPlayerData($playerId);

		return $this->playerRepository->delete($playerId);
	}

	/**
	 * @return array{affected:int, playlist_name:string, is_intranet:int}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function replaceMasterPlaylist(int $playerId, int $playlistId): array
	{
		$this->fetchAclCheckedPlayerData($playerId);

		$playlistName = '';
		$isIntranet   = 0;
		if ($playlistId > 0)
		{
			$playlist = $this->playlistService->loadPureById($playlistId);
			if ($playlist['playlist_mode'] !==  PlaylistMode::MASTER->value && $playlist['playlist_mode'] !==  PlaylistMode::MULTIZONE->value)
				throw new TranslatableModuleException('player', 'playlist_not_master', ['playlist_name' => (string) $playlist['playlist_name']]);

			$playlistName = (string) $playlist['playlist_name'];
			$isIntranet   = (int) ($playlist['is_intranet'] ?? 0);
		}
		$affected = $this->playerRepository->update($playerId, ['playlist_id' => $playlistId]);

		return ['affected' => $affected, 'playlist_name' => $playlistName, 'is_intranet' => $isIntranet];
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
	public function fetchAclCheckedPlayerData(int $playerId): array
	{
		$player = $this->playerRepository->findFirstById($playerId);
		if ($player === [])
			throw new NotFoundException('player', 'player_not_found', ['player_id' => $playerId]);

		/** @var array{UID: int, company_id: int, player_id: int, ...} $player */
		if (!$this->playerValidator->isPlayerEditable($this->userSession->getUID(), $player))
			throw new AccessDeniedException('player', 'player_not_editable', ['player_id' => $playerId]);

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