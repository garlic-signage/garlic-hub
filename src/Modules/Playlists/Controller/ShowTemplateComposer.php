<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


namespace App\Modules\Playlists\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\CsrfToken;
use App\Modules\Playlists\Helper\Templates\Orchestrator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ShowTemplateComposer
{
	public function __construct(
		private readonly Orchestrator $orchestrator,
		private JsonResponseHandler $responseHandler,
		private CsrfToken           $csrfToken
	){}

	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ?ResponseInterface
	{
		$itemId = (int) $args['item_id'] ?? 0;
		$flash      = $request->getAttribute('flash');
		if ($itemId === 0)
		{
			$flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/playlists')->withStatus(302);
		}
		if (!$this->orchestrator->checkRights($itemId))
		{
			$flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/playlists')->withStatus(302);
		}

		$response->getBody()->write(serialize($this->orchestrator->build($itemId)));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$itemId = (int) ($args['item_id'] ?? 0);
		if ($itemId === 0)
			return $this->responseHandler->jsonError($response, 'No rights', 200);

		if (!$this->orchestrator->checkRights($itemId))
			return $this->responseHandler->jsonError($response, 'No rights', 200);


		return $this->responseHandler->jsonSuccess($response, ['content' => $this->orchestrator->getContent()]);
	}

}