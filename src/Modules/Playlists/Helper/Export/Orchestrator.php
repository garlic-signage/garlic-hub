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

namespace App\Modules\Playlists\Helper\Export;

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Controller\ReadsInput;
use App\Modules\Playlists\Services\ExportService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the playlist export and executes it.
 * validate() returns a response on error and null when the input is valid.
 */
class Orchestrator
{
	use ReadsInput;

	private int $playlistId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly BaseValidator $validator,
		private readonly ExportService $exportService
	) {}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->text('csrf_token')))
			return $this->responseBuilder->csrfTokenMismatch($response);

		$this->playlistId = $this->id('playlist_id');
		if ($this->playlistId === 0)
			return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function export(ResponseInterface $response): ResponseInterface
	{
		$this->exportService->exportToSmil($this->playlistId);

		return $this->responseBuilder->generalSuccess($response, []);
	}
}
