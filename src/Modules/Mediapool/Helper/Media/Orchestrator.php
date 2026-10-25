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

use App\Framework\Validation\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Helper\ReadsInput;
use App\Modules\Mediapool\Services\MediaService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the mediapool media endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	use ReadsInput;

	private const array FILTER_MAP = [
		'images'    => 'image',
		'videos'    => 'video',
		'audios'    => 'audio',
		'documents' => 'application/pdf',
	];

	private int $nodeId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly BaseValidator $validator,
		private readonly MediaService $mediaService
	) {}

	public function validateList(ResponseInterface $response): ?ResponseInterface
	{
		$this->nodeId = $this->id('node_id');
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
		$filter = self::FILTER_MAP[$this->text('filter')] ?? '';

		$mediaList = $this->mediaService->listMedia($this->nodeId, $filter);

		return $this->responseBuilder->generalSuccess($response, ['media_list' => $mediaList]);
	}

	public function validateGetInfo(ResponseInterface $response): ?ResponseInterface
	{
		if ($this->text('media_id') === '')
			return $this->responseBuilder->missingMediaId($response);

		return null;
	}

	/**
	 * @throws DatabaseException
	 * @throws UserException
	 */
	public function getInfo(ResponseInterface $response): ResponseInterface
	{
		$media = $this->mediaService->fetchMedia($this->text('media_id'));

		return $this->responseBuilder->generalSuccess($response, ['media' => $media]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if ($this->text('media_id') === '')
			return $this->responseBuilder->missingMediaId($response);

		if (!is_string($this->input['filename'] ?? null))
			return $this->responseBuilder->missingFilename($response);

		if (!is_string($this->input['description'] ?? null))
			return $this->responseBuilder->missingDescription($response);

		return null;
	}

	/**
	 * @throws DatabaseException
	 * @throws UserException
	 */
	public function edit(ResponseInterface $response): ResponseInterface
	{
		$this->mediaService->updateMedia($this->text('media_id'), $this->text('filename'), $this->text('description'));

		return $this->responseBuilder->generalSuccess($response, []);
	}

	/**
	 * For delete and clone, which need only the media id.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateWithMediaId(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if ($this->text('media_id') === '')
			return $this->responseBuilder->missingMediaId($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		$count = $this->mediaService->deleteMedia($this->text('media_id'));

		return $this->responseBuilder->generalSuccess($response, ['data' => ['deleted_media' => $count]]);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function clone(ResponseInterface $response): ResponseInterface
	{
		$newMedia = $this->mediaService->cloneMedia($this->text('media_id'));

		return $this->responseBuilder->generalSuccess($response, ['new_media' => $newMedia]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateMove(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response);
		if ($answer !== null)
			return $answer;

		if ($this->text('media_id') === '' || !$this->hasId('node_id'))
			return $this->responseBuilder->missingMediaIdOrNode($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function move(ResponseInterface $response): ResponseInterface
	{
		$count = $this->mediaService->moveMedia($this->text('media_id'), $this->id('node_id'));

		return $this->responseBuilder->generalSuccess($response, ['data' => ['deleted_media' => $count]]);
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
