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
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Widgets\Orchestrator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles operations related to Widgets.
 * Validation and execution are delegated to the Orchestrator.
 */
readonly class WidgetsController
{
	public function __construct(private Orchestrator $orchestrator) {}

	/**
	 * @param array<string,mixed> $args
	 * @throws UserException
	 */
	public function fetch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($args)->validate($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->fetch($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $input */
		$input = $request->getParsedBody();
		// no body, or one which could not be read: nothing was sent
		$input = is_array($input) ? $input : [];
		$answer = $this->orchestrator->setInput($input)->validateSave($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->save($response);
	}
}
