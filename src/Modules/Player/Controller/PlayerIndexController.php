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

use App\Framework\Core\Sanitizer;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use App\Modules\Player\Services\PlayerIndexService;
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

	public function __construct(PlayerIndexService $indexService, IndexResponseHandler $indexResponseHandler, Sanitizer $sanitizer)
	{
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
		$ownerId    = $this->sanitizer->int($get['owner_id'] ?? 0);
		$server     = $request->getServerParams();
//		$this->indexResponseHandler->debugHeader($request->getHeaders());
//		$this->indexResponseHandler->debugServer();

		// because JavaScript player cannot send a User-Agent.
		$userAgent = $server['HTTP_X_SIGNAGE_AGENT'] ?? $server['HTTP_USER_AGENT'];

		$serverName = $server['SERVER_NAME'];

	//	$userAgent = 'GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:9d2cefd78b3f) garlic-macOS/v0.6.0.679 (MODEL:Garlic)';
		$this->indexService->setUID($ownerId);
		if (str_contains($serverName, 'localhost') || str_contains($serverName, 'ddev'))
			$localPlayer = true;
		else
			$localPlayer = false;

		$filePath = $this->indexService->handleIndexRequest($userAgent, $server, $localPlayer);
		if (empty($filePath))
			return $response->withHeader('Content-Type', 'application/smil+xml')->withStatus(404);

		return $this->sendSmilHeader($response, $server, $filePath);
	}

	/**
	 * @param array<string,mixed> $server
	 * @throws ModuleException
	 */
	private function sendSmilHeader(ResponseInterface $response, array $server, string $filePath): ResponseInterface
	{
		$this->indexResponseHandler->init($server, $filePath);
		if ($server['REQUEST_METHOD'] === 'HEAD')
		{
			return $this->indexResponseHandler->doHEAD($response);
		}

		return $this->indexResponseHandler->doGET($response);

	}

}