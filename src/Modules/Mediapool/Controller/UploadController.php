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
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Helper\Uploads\Orchestrator;
use Doctrine\DBAL\Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class responsible for handling upload-related operations, including uploading files
 * from local storage, external links, and integrating with third-party stock image providers.
 * Validation and execution are delegated to the Orchestrator.
 */
readonly class UploadController
{
	public function __construct(private Orchestrator $orchestrator) {}

	/**
	 * @throws GuzzleException
	 */
	public function searchStockImages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateSearchStockImages($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->searchStockImages($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws Exception
	 */
	public function uploadLocalFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator
			->setInput($this->parsedBody($request))
			->setUploadedFiles($request->getUploadedFiles())
			->validateUploadLocalFile($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->uploadLocalFile($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function uploadFromUrl(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$answer = $this->orchestrator->setInput($this->parsedBody($request))->validateUploadFromUrl($response);
		if ($answer !== null)
			return $answer;

		return $this->orchestrator->uploadFromUrl($response);
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
