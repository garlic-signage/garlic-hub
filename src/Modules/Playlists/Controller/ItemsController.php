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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Items\Orchestrator;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller responsible for handling operations on items.
 * Validation and execution are delegated to the Orchestrator.
 */
readonly class ItemsController
{
	public function __construct(private Orchestrator $orchestrator) {}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function loadItems(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($args)->validateLoadItems($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->loadItems($response);
	}

	/**
	 * @throws UserException
	 */
	public function insert(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		// no body, or one which could not be read: nothing was sent
		$input = is_array($input) ? $input : [];
		$answer = $this->orchestrator->setInput($input)->validateInsert($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->insert($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		// no body, or one which could not be read: nothing was sent
		$input = is_array($input) ? $input : [];
		$answer = $this->orchestrator->setInput($input)->validateEdit($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->edit($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function fetch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($args)->validateFetch($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->fetch($response);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function updateItemOrders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		// no body, or one which could not be read: nothing was sent
		$input = is_array($input) ? $input : [];
		$answer = $this->orchestrator->setInput($input)->validateUpdateItemOrders($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->updateItemOrders($response);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		// no body, or one which could not be read: nothing was sent
		$input = is_array($input) ? $input : [];
		$answer = $this->orchestrator->setInput($input)->validateDelete($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->delete($response);
	}

}
