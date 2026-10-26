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


namespace App\Modules\Player\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PlayerController
{
	/**
	 * @var array<string,mixed>|array<empty,empty>
	 */
	private array $playerData = [];

	public function __construct(
		private readonly JsonResponseHandler $responseHandler,
		private readonly PlayerService       $playerService
	) {}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function determineRights(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playerId = (int) ($args['player_id'] ?? 0);
		$responseData = ['can_edit' => false, 'can_delete' => false];
		if ($playerId === 0)
			return $this->responseHandler->jsonSuccess($response, $responseData);

		try
		{
			$this->fetchPlayerData($playerId);
		}
		catch (NotFoundException|AccessDeniedException)
		{
			// asking for rights is no error, the user simply has none for this player
			return $this->responseHandler->jsonSuccess($response, $responseData);
		}

		if ((int) $this->playerData['status'] >= PlayerStatus::RELEASED->value && (int) $this->playerData['licence_id'] > 0)
		{
			$responseData['can_edit']      = true;
			$responseData['can_delete']    = true;
			$responseData['has_playlist']  = (int) $this->playerData['playlist_id'] > 0;
			$responseData['playlist_id']   = $this->playerData['playlist_id'];
			$responseData['is_intranet']   = (int) $this->playerData['is_intranet'] === 1;
		}

		return $this->responseHandler->jsonSuccess($response, $responseData);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws UserException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function fetchPlayer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playerId = (int) ($args['player_id'] ?? 0);
		if ($playerId === 0)
			return $this->responseHandler->jsonError($response, 'No playerId');

		$this->fetchPlayerData($playerId);

		return $this->responseHandler->jsonSuccess($response, $this->playerData);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$requestData = RequestInput::of($request->getParsedBody());

		$playerId = $requestData->id('player_id');
		if ($playerId === 0)
			return $this->responseHandler->jsonError($response, 'No playerId');

		if ($this->playerService->delete($playerId) === 0)
			return $this->responseHandler->jsonError($response, 'Player not deleted.');

		return $this->responseHandler->jsonSuccess($response, $this->playerData);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function fetchPlayerData(int $playerId): void
	{
		$this->playerData = $this->playerService->fetchAclCheckedPlayerData($playerId);
	}
}