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

namespace App\Modules\Mediapool\Helper\Media;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Services\MediaService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Validates the input for the mediapool media endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	private const array FILTER_MAP = [
		'images'    => 'image',
		'videos'    => 'video',
		'audios'    => 'audio',
		'documents' => 'application/pdf',
	];

	/** @var array<string,mixed> */
	private array $input = [];
	private int $nodeId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly UserSession $userSession,
		private readonly BaseValidator $validator,
		private readonly MediaService $mediaService
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	public function validateList(ResponseInterface $response): ?ResponseInterface
	{
		$this->nodeId = (int) ($this->input['node_id'] ?? 0);
		if ($this->nodeId === 0)
			return $this->responseBuilder->missingNode($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function list(ResponseInterface $response): ResponseInterface
	{
		$filter = self::FILTER_MAP[$this->input['filter'] ?? ''] ?? '';

		$this->mediaService->setUID($this->userSession->getUID());
		$mediaList = $this->mediaService->listMedia($this->nodeId, $filter);

		return $this->responseBuilder->generalSuccess($response, ['media_list' => $mediaList]);
	}

	public function validateGetInfo(ResponseInterface $response): ?ResponseInterface
	{
		if (($this->input['media_id'] ?? '') === '')
			return $this->responseBuilder->missingMediaId($response);

		return null;
	}

	/**
	 * @throws DatabaseException
	 * @throws UserException
	 */
	public function getInfo(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->setUID($this->userSession->getUID());
		$media = $this->mediaService->fetchMedia($this->input['media_id']);

		return $this->responseBuilder->generalSuccess($response, ['media' => $media]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!array_key_exists('media_id', $this->input))
			return $this->responseBuilder->missingMediaId($response);

		if (!array_key_exists('filename', $this->input))
			return $this->responseBuilder->missingFilename($response);

		if (!array_key_exists('description', $this->input))
			return $this->responseBuilder->missingDescription($response);

		return null;
	}

	/**
	 * @throws DatabaseException
	 * @throws UserException
	 */
	public function edit(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->setUID($this->userSession->getUID());
		$this->mediaService->updateMedia($this->input['media_id'], $this->input['filename'], $this->input['description']);

		return $this->responseBuilder->generalSuccess($response, []);
	}

	/**
	 * For delete and clone, which need only the media id.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function validateWithMediaId(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['media_id']))
			return $this->responseBuilder->missingMediaId($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->setUID($this->userSession->getUID());
		$count = $this->mediaService->deleteMedia($this->input['media_id']);

		return $this->responseBuilder->generalSuccess($response, ['data' => ['deleted_media' => $count]]);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function clone(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->setUID($this->userSession->getUID());
		$newMedia = $this->mediaService->cloneMedia($this->input['media_id']);

		return $this->responseBuilder->generalSuccess($response, ['new_media' => $newMedia]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function validateMove(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if (!isset($this->input['media_id'], $this->input['node_id']))
			return $this->responseBuilder->missingMediaIdOrNode($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function move(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->setUID($this->userSession->getUID());
		$count = $this->mediaService->moveMedia($this->input['media_id'], (int) $this->input['node_id']);

		return $this->responseBuilder->generalSuccess($response, ['data' => ['deleted_media' => $count]]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function validateToken(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return null;
	}
}
