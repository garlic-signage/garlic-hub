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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Helper\Nodes\Orchestrator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Controller for managing operations related to nodes within the system.
 * Handles listing, adding, editing, moving, and deleting nodes.
 * Validation and execution are delegated to the Orchestrator.
 */
readonly class NodesController
{
	public function __construct(private Orchestrator $orchestrator) {}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		return $this->orchestrator->setInput($args)->list($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function add(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateAdd($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->add($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateEdit($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->edit($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function move(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateMove($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->move($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateDelete($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->delete($response);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function parsedBody(ServerRequestInterface $request): array
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		return $input;
	}
}
