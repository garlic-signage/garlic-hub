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

use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\PlaylistsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Controller responsible for handling the display and editing of playlists in a compose interface.
 */
class ShowComposeController
{
	public function __construct(private readonly PlaylistsService $playlistsService,
								private readonly UiTemplatesPreparer $uiTemplatesPreparer,
								private readonly Messages $flash)
	{}

	/**
	 * @param array<string,mixed> $args
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = (int) ($args['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->redirectWithErrors($response, 'Playlist ID not valid.');

		try
		{
			/** @var array<string,mixed> $playlist */
			$playlist = $this->playlistsService->loadPlaylistForEdit($playlistId);
		}
		// the compose page redirects to the list with a message instead of an error page
		catch (NotFoundException)
		{
			return $this->redirectWithErrors($response, 'Playlist not found.');
		}
		catch (AccessDeniedException)
		{
			return $this->redirectWithErrors($response, 'Playlist is not editable.');
		}

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
				return $this->redirectWithErrors($response, 'Unsupported playlist mode: '.$playlist['playlist_mode']);

		}

		return new PageResponse($data);
	}

	private function redirectWithErrors(ResponseInterface $response, string $message): ResponseInterface
	{
		$this->flash->addMessage('error', $message);
		return $response->withHeader('Location', '/playlists')->withStatus(302);
	}
}