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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
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
		private readonly UserSession         $userSession,
		private readonly PlayerService       $playerService,
	) {}

	/**
	 * @param array<string,string> $args
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function determineRights(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playerId = (int) ($args['player_id'] ?? 0);
		$responseData = ['can_edit' => false, 'can_delete' => false];
		if ($playerId === 0)
			return $this->responseHandler->jsonSuccess($response, $responseData);

		$this->fetchPlayerData($playerId);
		if ($this->playerData === [])
			return $this->responseHandler->jsonSuccess($response, $responseData);

	//	$responseData['can_edit']  = true;
	//	$responseData['is_delete'] = true;

		return $this->responseHandler->jsonSuccess($response, $responseData);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function fetchPlayer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playerId = (int) ($args['player_id'] ?? 0);
		if ($playerId === 0)
			return $this->responseHandler->jsonError($response, 'No playerId');

		$this->fetchPlayerData($playerId);
		if ($this->playerData === [])
			return $this->responseHandler->jsonError($response, 'No player found');

		return $this->responseHandler->jsonSuccess($response, $this->playerData);
	}

	/**
	 * @throws CoreException
	 * @throws UserException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function fetchPlayerData(int $playerId): void
	{
		$UID = $this->userSession->getUID();
		$this->playerService->setUID($UID);
		$this->playerData = $this->playerService->fetchAclCheckedPlayerData($playerId);
	}
}