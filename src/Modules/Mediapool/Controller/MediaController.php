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

namespace App\Modules\Mediapool\Controller;

use App\Framework\Controller\AbstractAsyncController;
use App\Framework\Core\CsrfToken;
use App\Modules\Mediapool\Services\MediaService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MediaController is responsible for handling media-related operations.
 * It provides endpoints for listing, fetching details, editing, deleting,
 * moving, and cloning media items.
 */
class MediaController extends AbstractAsyncController
{
	private readonly MediaService $mediaService;
	private readonly CsrfToken $csrfToken;

	public function __construct(MediaService $mediaService, CsrfToken $csrfToken)
	{
		$this->mediaService = $mediaService;
		$this->csrfToken    = $csrfToken;
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws Exception
	 */
	public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$node_id = (int) ($args['node_id'] ?? 0);
		if ($node_id === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'node is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);
		$media_list = $this->mediaService->listMedia($node_id);
		return $this->jsonResponse($response, ['success' => true, 'media_list' => $media_list]);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function getInfo(ServerRequestInterface $request, ResponseInterface $response, array $args):
	ResponseInterface
	{
		$media_id = $args['media_id'] ?? '';
		if ($media_id === '')
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'media_id is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);
		$media = $this->mediaService->fetchMedia($media_id);
		return $this->jsonResponse($response, ['success' => true, 'media' => $media]);
	}

	public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $bodyParams */
		$bodyParams = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

		if (!array_key_exists('media_id', $bodyParams))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Media id is missing']);

		if (!array_key_exists('filename', $bodyParams))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Filename is missing']);

		if (!array_key_exists('description', $bodyParams))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Description is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);

		$this->mediaService->updateMedia($bodyParams['media_id'], $bodyParams['filename'], $bodyParams['description']);
		return $this->jsonResponse($response, ['success' => true]);
	}

	/**
	 * @throws Exception
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $bodyParams */
		$bodyParams = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

		if (!isset($bodyParams['media_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'media id is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);
		$count = $this->mediaService->deleteMedia($bodyParams['media_id']);
		return $this->jsonResponse($response, ['success' => true, 'data' => ['deleted_media' => $count]]);
	}

	/**
	 * @throws Exception
	 */
	public function move(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $bodyParams */
		$bodyParams = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

		if (!isset($bodyParams['media_id']) || !isset($bodyParams['node_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'media id or node is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);
		$count = $this->mediaService->moveMedia($bodyParams['media_id'], (int)$bodyParams['node_id']);
		return $this->jsonResponse($response, ['success' => true, 'data' => ['deleted_media' => $count]]);
	}

	/**
	 * @throws Exception
	 */
	public function clone(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $bodyParams */
		$bodyParams = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

		if (!isset($bodyParams['media_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'media id is missing']);

		$this->mediaService->setUID($request->getAttribute('session')->get('user')['UID']);
		$new_media = $this->mediaService->cloneMedia($bodyParams['media_id']);
		return $this->jsonResponse($response, ['success' => true, 'new_media' => $new_media]);
	}
}