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
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Slim\Flash\Messages;

/**
 * Controller responsible for handling the display and editing of playlists in a compose interface.
 */
class ShowComposeController
{
	private readonly PlaylistsService $playlistsService;
	private readonly UiTemplatesPreparer $uiTemplatesPreparer;
	private Messages $flash;

	public function __construct(PlaylistsService $playlistsService, UiTemplatesPreparer $uiTemplatesPreparer)
	{
		$this->playlistsService = $playlistsService;
		$this->uiTemplatesPreparer = $uiTemplatesPreparer;
	}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$this->setImportantAttributes($request);
		$playlistId = (int) ($args['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->redirectWithErrors($response, 'Playlist ID not valid.');

		/** @var array<string,mixed>|array<empty,empty> $playlist */
		$playlist = $this->playlistsService->loadPlaylistForEdit($playlistId);
		if (empty($playlist))
			return $this->redirectWithErrors($response, 'Playlist not found.');

		switch ($playlist['playlist_mode'])
		{
			case PlaylistMode::MULTIZONE->value:
				$data = $this->uiTemplatesPreparer->buildMultizoneEditor($playlist);
			break;
			case PlaylistMode::EXTERNAL->value:
				$data = $this->uiTemplatesPreparer->buildExternalEditor($playlist);
				break;
			case PlaylistMode::CHANNEL->value:
			case PlaylistMode::INTERNAL->value:
			case PlaylistMode::MASTER->value:
				$data = $this->uiTemplatesPreparer->buildCircularEditor($playlist);
				break;
			default:
				return $this->redirectWithErrors($response, 'Unsupported playlist mode: .'.$playlist['playlist_mode']);

		}

		$response->getBody()->write(serialize($data));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	private function setImportantAttributes(ServerRequestInterface $request): void
	{
		$session = $request->getAttribute('session');
		$this->playlistsService->setUID($session->get('user')['UID']);
		$this->flash      = $request->getAttribute('flash');
	}

	private function redirectWithErrors(ResponseInterface $response, string $defaultMessage = 'Unknown error.'): ResponseInterface
	{
		if ($this->playlistsService->hasErrorMessages())
		{
			foreach ($this->playlistsService->getErrorMessages() as $message)
			{
				$this->flash->addMessage('error', $message);
			}
		}
		else
		{
			$this->flash->addMessage('error', $defaultMessage);
		}
		return $response->withHeader('Location', '/playlists')->withStatus(302);
	}
}