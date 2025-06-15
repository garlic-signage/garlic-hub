<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace App\Modules\Playlists\Controller;

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Services\PlaylistsDatatableService;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PlaylistsController
{
	private readonly PlaylistsService $playlistsService;
	private readonly PlaylistsDatatableService $playlistsDatatableService;
	private readonly Parameters $parameters;
	private Session $session;

	/**
	 * @param PlaylistsService $playlistsService
	 * @param PlaylistsDatatableService $playlistsDatatableService
	 * @param Parameters $parameters
	 */
	public function __construct(PlaylistsService $playlistsService, PlaylistsDatatableService $playlistsDatatableService, Parameters $parameters)
	{
		$this->playlistsService          = $playlistsService;
		$this->playlistsDatatableService = $playlistsDatatableService;
		$this->parameters                = $parameters;
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();
		$playlistId = $post['playlist_id'] ?? 0;
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		if ($this->playlistsService->delete($playlistId) === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist not found.']);

		return $this->jsonResponse($response, ['success' => true]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function toggleShuffle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();
		if (empty($post['playlist_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		$data = $this->playlistsService->toggleShuffle($post['playlist_id']);
		if ($data['affected'] === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist not found.']);

		return $this->jsonResponse($response, ['success' => true, 'playlist_metrics' => $data['playlist_metrics']]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function shufflePicking(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();
		if (empty($post['playlist_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		if (empty($post['shuffle_picking']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'No picking value found.']);

		$this->setServiceUID($request);
		$data = $this->playlistsService->shufflePicking($post['playlist_id'], $post['shuffle_picking']);

		if ($data['affected'] === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist not found.']);

		return $this->jsonResponse($response, ['success' => true, 'playlist_metrics' => $data['playlist_metrics']]);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function loadZone(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = $args['playlist_id'] ?? 0;
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		$multizone = $this->playlistsService->loadPlaylistForMultizone($playlistId);
		if ($this->playlistsService->hasErrorMessages())
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $this->playlistsService->getErrorMessages()]);

		return $this->jsonResponse($response, ['success' => true, 'zones' => $multizone]);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function saveZone(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = $args['playlist_id'] ?? 0;
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		/** @var array<string,mixed> $parsedBody */
		$parsedBody = $request->getParsedBody();

		$count = $this->playlistsService->saveZones($playlistId, $parsedBody);
		if ($count === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Multizone could not be saved']);


		return $this->jsonResponse($response, ['success' => true]);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function findByName(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$this->parameters->setUserInputs($args);
		$this->parameters->parseInputAllParameters();

		$this->session = $request->getAttribute('session');
		/** @var array<string,mixed> $user */
		$user = $this->session->get('user');
		$this->playlistsDatatableService->setUID($user['UID']);
		$this->playlistsDatatableService->loadDatatable();
		$results = [];
		foreach ($this->playlistsDatatableService->getCurrentFilterResults() as $value)
		{
			$results[] = ['id' => $value['playlist_id'], 'name' => $value['playlist_name']];
		}

		return $this->jsonResponse($response, $results);
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function findForPlayerAssignment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$args['playlist_mode'] = 'master,multizone'; // important! no space after comma
		return $this->findByName($request, $response, $args);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public function findById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = $args['playlist_id'] ?? 0;
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		$result = $this->playlistsService->loadNameById($playlistId);
		return $this->jsonResponse($response, $result);
	}

	private function setServiceUID(ServerRequestInterface $request): void
	{
		$this->session = $request->getAttribute('session');
		/** @var array<string,mixed> $user */
		$user = $this->session->get('user');
		$this->playlistsService->setUID($user['UID']);
	}

	private function jsonResponse(ResponseInterface $response, mixed $data): ResponseInterface
	{
		$json = json_encode($data);
		if ($json !== false)
			$response->getBody()->write($json);

		return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	}

}