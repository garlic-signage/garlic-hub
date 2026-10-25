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


namespace App\Modules\Player\Helper\PlayerPlaylist;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Controller\ReadsInput;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Orchestrator
 *
 * This class orchestrates the operations related to players and playlists.
 * It validates inputs, manages the lifecycle of a playlist with a player,
 * and interacts with services to execute these operations.
 */
class Orchestrator
{
	use ReadsInput;

	private int $playerId;
	private int $playlistId;
	/** @var array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...}  */
	private array $player;

	public function __construct(
		private readonly ResponseBuilder      $responseBuilder,
		private readonly BaseValidator        $validator,
		private readonly PlayerService        $playerService,
		private readonly PlayerRestAPIService $playerRestAPIService,
	) {}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateForReplacePlaylist(ResponseInterface $response): ?ResponseInterface
	{
		$common = $this->validateStandardInput($response);
		if ($common !== null)
			return $common;

		// the id 0 takes the playlist off; nothing, or a value which is no number, is not asked for that
		if (!$this->hasId('playlist_id'))
			return $this->responseBuilder->invalidPlaylistId($response);

		$this->playlistId = $this->id('playlist_id');
	//	if ($this->playlistId === 0)
	//		return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateStandardInput(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->text(BaseEditParameters::PARAMETER_CSRF_TOKEN)))
			return $this->responseBuilder->csrfTokenMismatch($response);

		$this->playerId = $this->id('player_id');
		if ($this->playerId === 0)
			return $this->responseBuilder->invalidPlayerId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function replaceMasterPlaylist(ResponseInterface $response): ResponseInterface
	{
		// 0 affected rows only means that the playlist was already assigned
		$data = $this->playerService->replaceMasterPlaylist($this->playerId, $this->playlistId);

		return $this->responseBuilder->generalSuccess($response, ['playlist_name' => $data['playlist_name']]);
	}


	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function checkPlayer(ResponseInterface $response): ?ResponseInterface
	{

		/** @var array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...} $player */
		$player = $this->playerService->fetchAclCheckedPlayerData($this->playerId);

		if ($player['is_intranet'] === 0)
			return $this->responseBuilder->playerNotReachable($response);

		if ($player['playlist_id'] === 0)
			return $this->responseBuilder->noPlaylistAssigned($response);

		$this->setPlayer($player);
		return null;
	}

	/**
	 * @param array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...} $player
	 */
	public function setPlayer(array $player): void
	{
		$this->player = $player;
	}

	/**
	 * @throws Exception
	 * @throws TranslatableModuleException
	 */
	public function pushPlaylist(ResponseInterface $response): ResponseInterface
	{
		$this->playerRestAPIService->authenticate($this->player['api_endpoint'], 'admin', '', $this->playerId);
		$this->playerRestAPIService->switchToDefaultContentUrl($this->player['api_endpoint'], $this->playerId);

		return $this->responseBuilder->generalSuccess($response,
			[ 'message' => 'Playlist pushed successfully to '.$this->player['player_name'].'.']);
	}

}