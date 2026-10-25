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
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Helper\Settings\Orchestrator;
use App\Modules\Playlists\Helper\Settings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Controller class responsible for handling settings related to playlists.
 */
class ShowSettingsController
{
	public function __construct(private readonly Orchestrator $orchestrator,
								private readonly TemplatePreparer $templatePreparer,
								private readonly Messages $flash)
	{}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function newPlaylistForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		return $this->outputRenderedCreateForm($args['playlist_mode'] ?? 'master', []);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function editPlaylistForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = (int) ($args['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->redirectWithError($response, 'Playlist ID not valid.');

		$playlist = $this->orchestrator->loadPlaylistForEdit($playlistId);
		if ($playlist === [])
			return $this->redirectWithError($response, 'Playlist not found.');

		return $this->outputRenderedEditForm($playlist['playlist_mode'], []);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input      = RequestInput::of($request->getParsedBody());
		$post       = $input->all();
		$playlistId = $input->id('playlist_id');

		if ($playlistId === 0)
			return $this->insert($response, $post);

		$playlist = $this->orchestrator->loadPlaylistForEdit($playlistId);
		if ($playlist === [])
			return $this->redirectWithError($response, 'Playlist not found.');

		return $this->update($response, $playlist['playlist_mode'], $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function insert(ResponseInterface $response, array $post): ResponseInterface
	{
		$result = $this->orchestrator->storeCreateSettings($post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			$playlistMode = $post['playlist_mode'] ?? '';
			return $this->outputRenderedCreateForm(is_string($playlistMode) ? $playlistMode : '', $post);
		}

		return $this->redirectWithSuccess($response, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function update(ResponseInterface $response, string $playlistMode, array $post): ResponseInterface
	{
		$result = $this->orchestrator->storeEditSettings($post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			return $this->outputRenderedEditForm($playlistMode, $post);
		}

		return $this->redirectWithSuccess($response, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function outputRenderedCreateForm(string $playlistMode, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildCreateForm($playlistMode, $post);
		$templateData = $this->templatePreparer->prepare($formData, $playlistMode);

		return new PageResponse($templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function outputRenderedEditForm(string $playlistMode, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildEditForm($post);
		$templateData = $this->templatePreparer->prepare($formData, $playlistMode);

		return new PageResponse($templateData);
	}

	/**
	 * @param string[] $errors
	 */
	private function addErrorsNow(array $errors): void
	{
		foreach ($errors as $errorText)
		{
			$this->flash->addMessageNow('error', $errorText);
		}
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function redirectWithSuccess(ResponseInterface $response, array $post): ResponseInterface
	{
		$playlistName = $post['playlist_name'] ?? '';
		$this->flash->addMessage('success', 'Playlist “' . (is_string($playlistName) ? $playlistName : '') . '“ successfully stored.');
		return $response->withHeader('Location', '/playlists')->withStatus(302);
	}

	private function redirectWithError(ResponseInterface $response, string $message): ResponseInterface
	{
		$this->flash->addMessage('error', $message);
		return $response->withHeader('Location', '/playlists')->withStatus(302);
	}
}
