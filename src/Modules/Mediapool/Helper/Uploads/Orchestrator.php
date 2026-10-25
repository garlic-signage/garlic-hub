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

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Framework\Controller\ReadsInput;
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
	use ReadsInput;

	private const array ALLOWED_STOCK_HOSTS = ['api.unsplash.com', 'api.pexels.com', 'pixabay.com'];
	/** the headers of a request to a stock service which the user may set (the token of the service) */
	private const array ALLOWED_STOCK_HEADERS = ['authorization', 'accept', 'x-api-key'];

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
	 * @param array<string,mixed> $uploadedFiles
	 */
	public function setUploadedFiles(array $uploadedFiles): static
	{
		$this->uploadedFiles = $uploadedFiles;
		return $this;
	}

	public function validateSearchStockImages(ResponseInterface $response): ?ResponseInterface
	{
		$parts = parse_url($this->text('api_url'));
		if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), self::ALLOWED_STOCK_HOSTS, true))
			return $this->responseBuilder->hostNotAllowed($response);

		if ($this->stockHeaders() === null)
			return $this->responseBuilder->headerNotAllowed($response);

		return null;
	}

	/**
	 * @throws GuzzleException
	 */
	public function searchStockImages(ResponseInterface $response): ResponseInterface
	{
		$body = $this->uploadService->requestApi($this->text('api_url'), $this->stockHeaders() ?? []);

		return $this->responseBuilder->generalSuccess($response, ['data' => $body]);
	}

	/**
	 * The headers for the stock service: the token of the user for the service, nothing else.
	 *
	 * @return array<string,string>|null null when there is a header which is not allowed or no text
	 */
	private function stockHeaders(): ?array
	{
		$headers = $this->input['headers'] ?? [];
		if (!is_array($headers))
			return null;

		foreach ($headers as $name => $value)
		{
			if (!is_string($name) || !is_string($value) || !in_array(strtolower($name), self::ALLOWED_STOCK_HEADERS, true))
				return null;
		}

		return $headers;
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

		// a field with several files (file[]) is a list: the upload takes one file
		if (!($this->uploadedFiles['file'] ?? null) instanceof UploadedFile)
			return $this->responseBuilder->noFiles($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function uploadLocalFile(ResponseInterface $response): ResponseInterface
	{
		/** @var UploadedFile $file checked by validateUploadLocalFile() */
		$file     = $this->uploadedFiles['file'];

		$metadata = $this->decodeMetadata();
		$result   = $this->uploadService->uploadMediaFile($this->nodeId, $this->userSession->getUID(), $file, $metadata);

		return $this->responseBuilder->uploadResult($response, $result);
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

		if ($this->text('external_link') === '')
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
			$this->text('external_link'),
			$this->decodeMetadata()
		);

		return $this->responseBuilder->uploadResult($response, $result);
	}

	/**
	 * @return array<string,mixed>
	 */
	/**
	 * The metadata of the media come as JSON text from the client. Only values of one kind are taken (text, number,
	 * yes/no), no lists or objects, and the description is a text.
	 *
	 * @return array<string,string|int|float|bool>
	 */
	private function decodeMetadata(): array
	{
		$decoded = json_decode($this->text('metadata') ?: '[]', true);
		if (!is_array($decoded))
			return [];

		$metadata = [];
		foreach ($decoded as $key => $value)
		{
			if (is_string($key) && is_scalar($value))
				$metadata[$key] = $key === 'description' ? (string) $value : $value;
		}

		return $metadata;
	}

	private function validateNode(ResponseInterface $response): ?ResponseInterface
	{
		$this->nodeId = $this->id('node_id');
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
		if (!$this->validator->validateCsrfToken($this->text('csrf_token')))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return null;
	}
}
