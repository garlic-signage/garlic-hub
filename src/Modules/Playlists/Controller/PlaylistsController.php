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
use App\Modules\Playlists\Helper\Playlists\Orchestrator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Controller responsible for handling various playlist-related operations such as deletion, shuffle toggling, zone management, and data retrieval.
 * Validation and execution are delegated to the Orchestrator.
 */
readonly class PlaylistsController
{
	public function __construct(private Orchestrator $orchestrator) {}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateWithToken($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->delete($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function toggleShuffle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateWithToken($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->toggleShuffle($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function shufflePicking(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateShufflePicking($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->shufflePicking($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws UserException
	 */
	public function loadZone(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($args)->validate($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->loadZone($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function saveZone(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$input = $this->parsedBody($request);
		$input['playlist_id'] = $args['playlist_id'] ?? 0;

		$answer = $this->orchestrator->setInput($input)->validateWithToken($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->saveZone($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function findByName(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		return $this->orchestrator->setInput($args)->findByName($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function findForPlayerAssignment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		return $this->orchestrator->setInput($args)->findForPlayerAssignment($response);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws UserException
	 */
	public function findById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($args)->validate($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->findById($response);
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
