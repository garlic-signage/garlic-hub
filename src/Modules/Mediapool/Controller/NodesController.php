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
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Services\NodesService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Controller for managing operations related to nodes within the system.
 * Handles listing, adding, editing, moving, and deleting nodes with appropriate
 * validation and security checks such as CSRF token validation.
 */
class NodesController extends AbstractAsyncController
{
	private readonly NodesService $nodesService;
	private readonly CsrfToken $csrfToken;

	public function __construct(NodesService $nodesService, CsrfToken $csrfToken)
	{
		$this->nodesService = $nodesService;
		$this->csrfToken    = $csrfToken;
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$parent_id = (int) ($args['parent_id'] ?? 0);
		$this->nodesService->UID = $request->getAttribute('session')->get('user')['UID'];
		$result = $this->nodesService->getNodes($parent_id);

		// because of Wunderbau needed to return a direct response
		$json = json_encode($result);
		if ($json !== false)
			$response->getBody()->write($json);

		return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DatabaseException
	 * @throws CoreException
	 */
	public function add(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			/** @var array<string,mixed> $bodyParams */
			$bodyParams = $request->getParsedBody();

			if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
				return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

			if (!isset($bodyParams['name']))
				throw new ModuleException('mediapool','node name is missing');

			$node_id = (int) ($bodyParams['node_id'] ?? 0);
			$this->nodesService->UID = $request->getAttribute('session')->get('user')['UID'];
			$new_node_id = $this->nodesService->addNode($node_id, $bodyParams['name']);

			return $this->jsonResponse($response, [
				'success' => true,
				'data' => ['id' => $new_node_id, 'new_name' => $bodyParams['name']]
			]);
		}
		catch (Throwable $e)
		{
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $e->getMessage()]);
		}
	}

	public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			/** @var array<string,mixed> $bodyParams */
			$bodyParams = $request->getParsedBody();

			if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
				return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

			if (!isset($bodyParams['name']) || !isset($bodyParams['node_id']))
				throw new ModuleException('mediapool', 'node name or id is missing');

			$visibility = $bodyParams['visibility'] ?? null;

			$this->nodesService->UID = $request->getAttribute('session')->get('user')['UID'];
			$count = $this->nodesService->editNode((int) $bodyParams['node_id'], $bodyParams['name'], $visibility);
			if ($count === 0)
				throw new ModuleException('mediapool', 'Edit node failed');

			return $this->jsonResponse($response, [
				'success' => true,
				'data' => [
					'id' => $bodyParams['node_id'],
					'new_name' => $bodyParams['name'],
					'visibility' => $visibility
				]
			]);
		}
		catch (Throwable $e)
		{
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $e->getMessage()]);
		}
	}

	/**
	 * @throws DatabaseException
	 */
	public function move(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			/** @var array<string,mixed> $bodyParams */
			$bodyParams = $request->getParsedBody();

			if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
				return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

			if (!isset($bodyParams['src_node_id']) || !isset($bodyParams['target_node_id']) || !isset($bodyParams['target_region']))
				throw new ModuleException('mediapool','Source node, target node, or target region is missing');

			$this->nodesService->UID = $request->getAttribute('session')->get('user')['UID'];
			$count = $this->nodesService->moveNode((int) $bodyParams['src_node_id'], (int) $bodyParams['target_node_id'], $bodyParams['target_region']);
			return $this->jsonResponse($response, ['success' => true, 'data' => ['count_deleted_nodes' => $count]]);
		}
		catch (Throwable $e)
		{
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $e->getMessage()]);
		}
	}

	/**
	 * @throws CoreException
	 * @throws DatabaseException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			/** @var array<string,mixed> $bodyParams */
			$bodyParams = $request->getParsedBody();

			if (!$this->csrfToken->validateToken($bodyParams['csrf_token'] ?? ''))
				return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

			if (!isset($bodyParams['node_id']))
				throw new ModuleException('mediapool', 'NodeId is missing');

			/** @var array{UID: int} $user */
			$user = $request->getAttribute('session')->get('user');
			$this->nodesService->UID = (int)$user['UID'];
			$count = $this->nodesService->deleteNode((int) $bodyParams['node_id']);
			return $this->jsonResponse($response, ['success' => true, 'data' => ['count_deleted_nodes' => $count]]);
		}
		catch (Throwable $e)
		{
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $e->getMessage()]);
		}
	}
}