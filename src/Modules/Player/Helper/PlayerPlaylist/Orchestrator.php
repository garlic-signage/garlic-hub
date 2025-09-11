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

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Orchestrator
 *
 * This class orchestrates the operations related to players and playlists.
 * It validates inputs, manages the lifecycle of a playlist with a player,
 * and interacts with services to execute these operations.
 */
class Orchestrator
{
	/** @var array<string,string>  */
	private array $input;
	private int $playerId;
	private int $playlistId;
	/** @var array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...}  */
	private array $player;

	public function __construct(
		private readonly ResponseBuilder      $responseBuilder,
		private readonly UserSession          $userSession,
		private readonly BaseValidator        $validator,
		private readonly PlayerService        $playerService,
		private readonly PlayerRestAPIService $playerRestAPIService,
	) {}

	/**
	 * @param array<string,string> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function validateForReplacePlaylist(ResponseInterface $response): ?ResponseInterface
	{
		$common = $this->validateStandardInput($response);
		if ($common !== null)
			return $common;

		$this->playlistId = (int) ($this->input['playlist_id'] ?? 0);
	//	if ($this->playlistId === 0)
	//		return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function validateStandardInput(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input[BaseEditParameters::PARAMETER_CSRF_TOKEN]))
			return $this->responseBuilder->csrfTokenMismatch($response);

		$this->playerId = (int) ($this->input['player_id'] ?? 0);
		if ($this->playerId === 0)
			return $this->responseBuilder->invalidPlayerId($response);

		return null;
	}

	/**
	 * @throws UserException
	 */
	public function replaceMasterPlaylist(ResponseInterface $response): ResponseInterface
	{
		$this->playerService->setUID($this->userSession->getUID());

		$data = $this->playerService->replaceMasterPlaylist($this->playerId, $this->playlistId);

		if (empty($data) || $data['affected'] === 0)
			return $this->responseBuilder->generalError($response, $this->playerService->getErrorMessagesAsString());

		return $this->responseBuilder->generalSuccess($response, ['playlist_name' => $data['playlist_name']]);
	}


	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 */
	public function checkPlayer(ResponseInterface $response): ?ResponseInterface
	{
		$this->playerService->setUID($this->userSession->getUID());

		/** @var array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...}|array<empty,empty> $player */
		$player = $this->playerService->fetchAclCheckedPlayerData($this->playerId);
		if ($player === [])
			return $this->responseBuilder->playerNotFound($response);

		if ($player['is_intranet'] === 0)
			return $this->responseBuilder->playerNotReachable($response);

		if ($player['playlist_id'] === 0)
			return $this->responseBuilder->noPlaylistAssigned($response);

		/** @var array{is_intranet:int, playlist_id:int, api_endpoint:string, player_name:string, ...} $player */
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
	 * @throws UserException
	 */
	public function pushPlaylist(ResponseInterface $response): ResponseInterface
	{
		$this->playerRestAPIService->setUID($this->userSession->getUID());

		$hasToken = $this->playerRestAPIService->authenticate($this->player['api_endpoint'], 'admin', '', $this->playerId);
		if (!$hasToken)
			return $this->responseBuilder->generalError($response,
				$this->playerRestAPIService->getErrorMessages()[0] ?? 'unknown player token error'
			);

		$succeed = $this->playerRestAPIService->switchToDefaultContentUrl($this->player['api_endpoint'], $this->playerId);
		if (!$succeed)
			return $this->responseBuilder->generalError($response,
				$this->playerRestAPIService->getErrorMessages()[0] ?? 'unknown player token error'
			);

		return $this->responseBuilder->generalSuccess($response,
			[ 'message' => 'Playlist pushed successfully to '.$this->player['player_name'].'.']);

	}

}