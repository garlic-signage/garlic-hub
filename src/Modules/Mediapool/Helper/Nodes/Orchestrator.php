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

namespace App\Modules\Mediapool\Helper\Nodes;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Services\NodesService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the mediapool node endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $input = [];

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly BaseValidator $validator,
		private readonly NodesService $nodesService
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function list(ResponseInterface $response): ResponseInterface
	{
		$nodes = $this->nodesService->getNodes((int) ($this->input['parent_id'] ?? 0));

		return $this->responseBuilder->nodeList($response, $nodes);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateAdd(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['name']))
			return $this->responseBuilder->missingName($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function add(ResponseInterface $response): ResponseInterface
	{
		$newNodeId = $this->nodesService->addNode((int) ($this->input['node_id'] ?? 0), $this->input['name']);

		return $this->responseBuilder->generalSuccess($response, [
			'data' => ['id' => $newNodeId, 'new_name' => $this->input['name']]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['name']) || !isset($this->input['node_id']))
			return $this->responseBuilder->missingNameOrId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function edit(ResponseInterface $response): ResponseInterface
	{
		$visibility = $this->input['visibility'] ?? null;

		// 0 affected rows only means that nothing has changed
		$this->nodesService->editNode((int) $this->input['node_id'], $this->input['name'], $visibility);

		return $this->responseBuilder->generalSuccess($response, [
			'data' => [
				'id' => $this->input['node_id'],
				'new_name' => $this->input['name'],
				'visibility' => $visibility
			]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateMove(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['src_node_id'], $this->input['target_node_id'], $this->input['target_region']))
			return $this->responseBuilder->missingMoveParameters($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function move(ResponseInterface $response): ResponseInterface
	{
		$count = $this->nodesService->moveNode(
			(int) $this->input['src_node_id'],
			(int) $this->input['target_node_id'],
			$this->input['target_region']
		);

		return $this->responseBuilder->generalSuccess($response, ['data' => ['count_deleted_nodes' => $count]]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateDelete(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['node_id']))
			return $this->responseBuilder->missingNodeId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		$count = $this->nodesService->deleteNode((int) $this->input['node_id']);

		return $this->responseBuilder->generalSuccess($response, ['data' => ['count_deleted_nodes' => $count]]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function validateToken(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return null;
	}
}
