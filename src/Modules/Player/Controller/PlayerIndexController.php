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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Validation\Sanitizer;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use App\Modules\Player\Services\PlayerIndexService;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller responsible for handling the index functionality for the Player.
 * It orchestrates services and response handlers to process requests and return appropriate responses.
 */
readonly class PlayerIndexController
{
	private PlayerIndexService $indexService;
	private IndexResponseHandler $indexResponseHandler;
	private Sanitizer $sanitizer;
	private PlayerService $playerService;

	public function __construct(PlayerIndexService $indexService, IndexResponseHandler $indexResponseHandler, Sanitizer $sanitizer, PlayerService $playerService)
	{
		$this->playerService        = $playerService;
		$this->indexService         = $indexService;
		$this->indexResponseHandler = $indexResponseHandler;
		$this->sanitizer            = $sanitizer;
	}

	/**
	 * @throws ModuleException
	 */
	public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$get        = $request->getQueryParams();
		// the address is public: owner_id[]=1 is no owner
		$owner      = $get['owner_id'] ?? 0;
		$ownerId    = (is_string($owner) || is_int($owner)) ? $this->sanitizer->int($owner) : 0;
		$server     = $request->getServerParams();
//		$this->indexResponseHandler->debugHeader($request->getHeaders());
//		$this->indexResponseHandler->debugServer();

		// because JavaScript player cannot send a User-Agent.
		// no agent at all is an unknown player
		$userAgent = $server['HTTP_X_SIGNAGE_AGENT'] ?? $server['HTTP_USER_AGENT'] ?? '';

		$serverName = (string) ($server['SERVER_NAME'] ?? '');

	//	$userAgent = 'GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:9d2cefd78b3f) garlic-macOS/v0.6.0.679 (MODEL:Garlic)';
		// The name can come from the Host header of the request. A name which only contains 'localhost' is no local machine.
		$localPlayer = preg_match('/^(localhost|[a-z0-9-]+\.ddev\.site)$/i', $serverName) === 1;

		$filePath = $this->indexService->handleIndexRequest($ownerId, $userAgent, $server, $localPlayer);
		if (empty($filePath))
			return $this->indexResponseHandler->doNotFound($response);

		return $this->sendSmilHeader($response, $server, $filePath);
	}

	/**
	 * GET /smil-preview?player_id=<id>: the index for the preview of a player in the hub. Only for a logged in
	 * user who may see the player. It is the index of the device, but the request leaves no trace in the database.
	 * The address has one segment: the media in the index are relative (delivery.php/...) and stay below /.
	 * The answer is private and has no CORS headers: it needs the session, no cache between may keep it.
	 *
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws Exception
	 */
	public function preview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$playerId = $request->getQueryParams()['player_id'] ?? null;
		// only a positive whole number
		if (!is_string($playerId) || preg_match('/^[1-9]\d{0,9}$/D', $playerId) !== 1)
			return $response->withStatus(404);

		// throws when the player is missing or the user may not see it
		$this->playerService->fetchAclCheckedPlayerData((int) $playerId);

		$filePath = $this->indexService->handlePreviewRequest((int) $playerId);
		if ($filePath === '')
			return $response->withStatus(404);

		return $this->sendSmilHeader($response, $request->getServerParams(), $filePath, true);
	}

	/**
	 * OPTIONS /smil-index: the preflight of a browser player on another origin (see IndexResponseHandler::doOPTIONS).
	 */
	public function preflight(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->indexResponseHandler->doOPTIONS($response);
	}

	/**
	 * @param array<string,mixed> $server
	 * @throws ModuleException
	 */
	private function sendSmilHeader(ResponseInterface $response, array $server, string $filePath, bool $private = false): ResponseInterface
	{
		$this->indexResponseHandler->init($server, $filePath, $private);
		if ($server['REQUEST_METHOD'] === 'HEAD')
		{
			return $this->indexResponseHandler->doHEAD($response);
		}

		return $this->indexResponseHandler->doGET($response);

	}

}