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
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Playlists\Helper\Settings\Facade;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Slim\Flash\Messages;

/**
 * Controller class responsible for handling settings related to playlists.
 */
class ShowSettingsController
{
	private readonly Facade $facade;
	private readonly FormTemplatePreparer $formElementPreparer;
	private Messages $flash;

	public function __construct(Facade $facade, FormTemplatePreparer $formElementPreparer)
	{
		$this->facade              = $facade;
		$this->formElementPreparer = $formElementPreparer;
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function newPlaylistForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlist = ['playlist_mode' => $args['playlist_mode'] ?? 'master'];

		$this->initFacade($request);
		$this->facade->buildCreateNewParameter($playlist['playlist_mode']);

		return $this->outputRenderedForm($response, $playlist);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function editPlaylistForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = (int) ($args['playlist_id'] ?? 0);

		$this->initFacade($request);

		if ($playlistId === 0)
		{
			$this->flash->addMessage('error', 'Playlist ID not valid.');
			return $response->withHeader('Location', '/playlists')->withStatus(302);
		}

		/** @var array{"UID": int, "company_id": int, playlist_mode: string, playlist_name:string, ...}|array<empty,empty> $playlist */
		$playlist = $this->facade->loadPlaylistForEdit($playlistId);
		if (empty($playlist))
		{
			$this->flash->addMessage('error', 'Playlist not found.');
			return $response->withHeader('Location', '/playlists')->withStatus(302);
		}
		/** @var array{"UID": int, "company_id": int, playlist_mode: string, playlist_name:string, ...} $playlist */
		$this->facade->buildEditParameter($playlist);

		/** @var array<string,mixed> $playlist */
		return $this->outputRenderedForm($response, $playlist);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{playlist_id?: int, playlist_mode: string, playlist_name:string, ...}  $post */
		$post = $request->getParsedBody();

		$this->initFacade($request);
		$errors = $this->facade->configurePlaylistFormParameter($post);
		foreach ($errors as $errorText)
		{
			$this->flash->addMessageNow('error', $errorText);
		}

		if (!empty($errors))
			return $this->outputRenderedForm($response, $post);

		$id = $this->facade->storePlaylist($post);
		if ($id > 0)
		{
			$this->flash->addMessage('success', 'Playlist “'.$post['playlist_name'].'“ successfully stored.');
			return $response->withHeader('Location', '/playlists')->withStatus(302);
		}

		return $this->outputRenderedForm($response, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function outputRenderedForm(ResponseInterface $response, array $post): ResponseInterface
	{
		$dataSections = $this->facade->prepareUITemplate($post);
		$templateData = $this->formElementPreparer->prepareUITemplate($dataSections);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	private function initFacade(ServerRequestInterface $request): void
	{
		$this->flash      = $request->getAttribute('flash');
		$this->facade->init($request->getAttribute('translator'), $request->getAttribute('session'));
	}
}