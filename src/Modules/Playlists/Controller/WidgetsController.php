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

namespace App\Modules\Playlists\Controller;

use App\Framework\Controller\AbstractAsyncController;
use App\Framework\Core\CsrfToken;
use App\Modules\Playlists\Services\WidgetsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles operations related to Widgets.
 *
 * This controller is responsible for fetching and saving widget data.
 */
class WidgetsController extends AbstractAsyncController
{
	private readonly WidgetsService $widgetsService;
	private readonly CsrfToken $csrfToken;


	public function __construct(WidgetsService $itemsService, CsrfToken $csrfToken)
	{
		$this->widgetsService = $itemsService;
		$this->csrfToken = $csrfToken;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function fetch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$itemId = (int) ($args['item_id'] ?? 0);
		if ($itemId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item ID not valid.']);

		$this->widgetsService->setUID($request->getAttribute('session')->get('user')['UID']);
		$data = $this->widgetsService->fetchWidgetByItemId($itemId);
		if (empty($data))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Widget load failed.']);

		return $this->jsonResponse($response, ['success' => true, 'data' => $data]);
	}

	public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'CSRF token mismatch.']);

		$itemId = (int) ($requestData['item_id'] ?? 0);
		if ($itemId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item ID not valid.']);

		$this->widgetsService->setUID($request->getAttribute('session')->get('user')['UID']);

		if (!$this->widgetsService->saveWidget($itemId, $requestData))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $this->widgetsService->getErrorText()]);

		return $this->jsonResponse($response, ['success' => true]);
	}
}