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

namespace App\Modules\Mediapool\Helper\Uploads;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\UploadService;
use Doctrine\DBAL\Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\UploadedFile;

/**
 * Validates the input for the mediapool upload endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	private const array ALLOWED_STOCK_HOSTS = ['api.unsplash.com', 'api.pexels.com', 'pixabay.com'];

	/** @var array<string,mixed> */
	private array $input = [];
	/** @var array<string,mixed> */
	private array $uploadedFiles = [];
	private int $nodeId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly UserSession $userSession,
		private readonly BaseValidator $validator,
		private readonly UploadService $uploadService
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
	 * @param array<string,mixed> $uploadedFiles
	 */
	public function setUploadedFiles(array $uploadedFiles): static
	{
		$this->uploadedFiles = $uploadedFiles;
		return $this;
	}

	public function validateSearchStockImages(ResponseInterface $response): ?ResponseInterface
	{
		$host = parse_url((string) ($this->input['api_url'] ?? ''), PHP_URL_HOST);
		if (!in_array($host, self::ALLOWED_STOCK_HOSTS, true))
			return $this->responseBuilder->hostNotAllowed($response);

		return null;
	}

	/**
	 * @throws GuzzleException
	 */
	public function searchStockImages(ResponseInterface $response): ResponseInterface
	{
		$body = $this->uploadService->requestApi($this->input['api_url'], $this->input['headers'] ?? []);

		return $this->responseBuilder->generalSuccess($response, ['data' => $body]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateUploadLocalFile(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (empty($this->uploadedFiles))
			return $this->responseBuilder->noFilesToUpload($response);

		$answer = $this->validateNode($response);
		if ($answer !== null)
			return $answer;

		if (empty($this->uploadedFiles['file']))
			return $this->responseBuilder->noFiles($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function uploadLocalFile(ResponseInterface $response): ResponseInterface
	{
		/** @var UploadedFile $file */
		$file     = $this->uploadedFiles['file'];
		$metadata = $this->decodeMetadata();
		$results  = $this->uploadService->uploadMediaFiles($this->nodeId, $this->userSession->getUID(), $file, $metadata);

		return $this->responseBuilder->uploadResult($response, $results[0]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateUploadFromUrl(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response) ?? $this->validateNode($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['external_link']))
			return $this->responseBuilder->missingExternalLink($response);

		return null;
	}

	/**
	 * @throws UserException
	 */
	public function uploadFromUrl(ResponseInterface $response): ResponseInterface
	{
		$result = $this->uploadService->uploadExternalMedia(
			$this->nodeId,
			$this->userSession->getUID(),
			$this->input['external_link'],
			$this->decodeMetadata()
		);

		return $this->responseBuilder->uploadResult($response, $result);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeMetadata(): array
	{
		$metadata = json_decode((string) ($this->input['metadata'] ?? '[]'), true);

		return is_array($metadata) ? $metadata : [];
	}

	private function validateNode(ResponseInterface $response): ?ResponseInterface
	{
		$this->nodeId = (int) ($this->input['node_id'] ?? 0);
		if ($this->nodeId === 0)
			return $this->responseBuilder->missingNode($response);

		return null;
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
