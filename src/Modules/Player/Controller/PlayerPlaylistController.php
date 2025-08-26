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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Helper\PlayerPlaylist\Orchestrator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Handles operations related to the management of a player's playlist.
 */
readonly class PlayerPlaylistController
{
	public function __construct(private Orchestrator $orchestrator)
	{
	}

	/**
	 * @throws UserException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function replacePlaylist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,string> $input */
		$input = $request->getParsedBody();

		$answer = $this->orchestrator->setInput($input)->validateForReplacePlaylist($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->replaceMasterPlaylist($response);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException|UserException
	 */
	public function pushPlaylist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,string> $input */
		$input = $request->getParsedBody();

		$answer = $this->orchestrator->setInput($input)->validateStandardInput($response);
		if ($answer !== null)
			return $answer;

		$answer = $this->orchestrator->checkPlayer($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->pushPlaylist($response);
	}



}