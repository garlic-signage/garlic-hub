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

namespace App\Modules\Playlists\Helper\Playlists;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the playlist endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $input = [];
	private int $playlistId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly BaseValidator $validator,
		private readonly PlaylistsService $playlistsService,
		private readonly PlaylistsDatatableService $playlistsDatatableService,
		private readonly Parameters $parameters
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	public function validate(ResponseInterface $response): ?ResponseInterface
	{
		$this->playlistId = (int) ($this->input['playlist_id'] ?? 0);
		if ($this->playlistId === 0)
			return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateWithToken(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return $this->validate($response);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function validateShufflePicking(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateWithToken($response);
		if ($answer !== null)
			return $answer;

		if (empty($this->input['shuffle_picking']))
			return $this->responseBuilder->missingPickingValue($response);

		return null;
	}

	/**
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		if ($this->playlistsService->delete($this->playlistId) === 0)
			return $this->responseBuilder->playlistNotFound($response);

		return $this->responseBuilder->generalSuccess($response, []);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function toggleShuffle(ResponseInterface $response): ResponseInterface
	{
		$data = $this->playlistsService->toggleShuffle($this->playlistId);
		if ($data['affected'] === 0)
			return $this->responseBuilder->playlistNotFound($response);

		return $this->responseBuilder->generalSuccess($response, ['playlist_metrics' => $data['playlist_metrics']]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function shufflePicking(ResponseInterface $response): ResponseInterface
	{
		$data = $this->playlistsService->shufflePicking($this->playlistId, (int) $this->input['shuffle_picking']);
		if ($data['affected'] === 0)
			return $this->responseBuilder->playlistNotFound($response);

		return $this->responseBuilder->generalSuccess($response, ['playlist_metrics' => $data['playlist_metrics']]);
	}

	/**
	 * @throws UserException
	 */
	public function loadZone(ResponseInterface $response): ResponseInterface
	{
		$multizone = $this->playlistsService->loadPlaylistForMultizone($this->playlistId);

		return $this->responseBuilder->generalSuccess($response, ['zones' => $multizone]);
	}

	/**
	 * @throws UserException
	 */
	public function saveZone(ResponseInterface $response): ResponseInterface
	{

		$zones = $this->input;
		unset($zones['csrf_token'], $zones['playlist_id']);

		if ($this->playlistsService->saveZones($this->playlistId, $zones) === 0)
			return $this->responseBuilder->zoneSaveFailed($response);

		return $this->responseBuilder->generalSuccess($response, []);
	}

	/**
	 * @throws UserException
	 */
	public function findById(ResponseInterface $response): ResponseInterface
	{

		return $this->responseBuilder->plainData($response, $this->playlistsService->loadNameById($this->playlistId));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function findByName(ResponseInterface $response): ResponseInterface
	{
		$this->parameters->setUserInputs(
			array_merge($this->input, [
				BaseFilterParametersInterface::PARAMETER_ELEMENTS_PER_PAGE => 0,
				BaseFilterParametersInterface::PARAMETER_SORT_COLUMN => 'playlist_id',
				BaseFilterParametersInterface::PARAMETER_SORT_ORDER => 'DESC',
			])
		);
		$this->parameters->parseInputAllParameters();

		$this->playlistsDatatableService->loadDatatable();
		$results = [];
		foreach ($this->playlistsDatatableService->getCurrentFilterResults() as $value)
		{
			$results[] = ['id' => $value['playlist_id'], 'name' => $value['playlist_name']];
		}

		return $this->responseBuilder->plainData($response, $results);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function findForPlayerAssignment(ResponseInterface $response): ResponseInterface
	{
		$this->input['playlist_mode'] = 'master,multizone'; // important! no space after comma

		return $this->findByName($response);
	}
}
