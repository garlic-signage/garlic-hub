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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Controller\ReadsInput;
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
	use ReadsInput;


	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly NodesService $nodesService
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function list(ResponseInterface $response): ResponseInterface
	{
		$nodes = $this->nodesService->getNodes($this->id('parent_id'));

		return $this->responseBuilder->nodeList($response, $nodes);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateAdd(ResponseInterface $response): ?ResponseInterface
	{
		if ($this->text('name') === '')
			return $this->responseBuilder->missingName($response);

		// without a parent the directory is a root directory: a parent which is no number is no parent
		if (isset($this->input['node_id']) && !$this->hasId('node_id'))
			return $this->responseBuilder->missingNodeId($response);

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
		$newNodeId = $this->nodesService->addNode($this->id('node_id'), $this->text('name'));

		return $this->responseBuilder->generalSuccess($response, [
			'data' => ['id' => $newNodeId, 'new_name' => $this->text('name')]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		if ($this->text('name') === '' || !$this->hasId('node_id'))
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
		$visibility = $this->optionalInt('visibility');

		// 0 affected rows only means that nothing has changed
		$this->nodesService->editNode($this->id('node_id'), $this->text('name'), $visibility);

		return $this->responseBuilder->generalSuccess($response, [
			'data' => [
				'id' => $this->id('node_id'),
				'new_name' => $this->text('name'),
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
		if (!$this->hasId('src_node_id') || !$this->hasId('target_node_id') || $this->text('target_region') === '')
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
			$this->id('src_node_id'),
			$this->id('target_node_id'),
			$this->text('target_region')
		);

		return $this->responseBuilder->generalSuccess($response, ['data' => ['count_deleted_nodes' => $count]]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateDelete(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->hasId('node_id'))
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
		$count = $this->nodesService->deleteNode($this->id('node_id'));

		return $this->responseBuilder->generalSuccess($response, ['data' => ['count_deleted_nodes' => $count]]);
	}
}