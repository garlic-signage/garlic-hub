<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Playlists\Helper\Settings;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;

/**
 * Handles orchestration of validation, fetching, and saving of playlist settings.
 */
class Orchestrator
{
	/** @var array{UID: int, company_id: int, playlist_mode: string, playlist_name: string, ...}|array{} */
	private array $playlist = [];

	public function __construct(
		private readonly PlaylistsService         $playlistsService,
		private readonly SettingsParametersPolicy $settingsParametersPolicy,
		private readonly FormInputHandler         $formInputHandler,
		private readonly SettingsFormBuilder      $settingsFormBuilder
	) {}

	/**
	 * Returns an empty array when the playlist does not exist or is not editable for the current user.
	 *
	 * @return array{UID: int, company_id: int, playlist_mode: string, playlist_name: string, ...}|array{}
	 * @throws UserException
	 */
	public function loadPlaylistForEdit(int $playlistId): array
	{
		try
		{
			/** @var array{UID: int, company_id: int, playlist_mode: string, playlist_name: string, ...} $playlist */
			$playlist = $this->playlistsService->loadPlaylistForEdit($playlistId);
		}
		catch (NotFoundException|AccessDeniedException)
		{
			// the settings page redirects to the list with a message instead of an error page
			$playlist = [];
		}
		$this->playlist = $playlist;

		return $this->playlist;
	}

	/**
	 * @param array<string,mixed> $userInput
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function buildCreateForm(string $playlistMode, array $userInput = []): array
	{
		$this->settingsParametersPolicy->addCreateFormElements($playlistMode);

		return $this->settingsFormBuilder->buildForm($userInput + [Parameters::PARAMETER_PLAYLIST_MODE => $playlistMode]);
	}

	/**
	 * The playlist must be loaded with loadPlaylistForEdit() before.
	 * Without user input, the form is filled with the loaded playlist.
	 *
	 * @param array<string,mixed> $userInput
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function buildEditForm(array $userInput = []): array
	{
		$this->settingsParametersPolicy->addEditFormElements($this->getLoadedPlaylist());

		return $this->settingsFormBuilder->buildForm($userInput !== [] ? $userInput : $this->playlist);
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function storeCreateSettings(array $post): array
	{
		$playlistMode = $post[Parameters::PARAMETER_PLAYLIST_MODE] ?? '';
		$this->settingsParametersPolicy->addCreateFormElements(is_string($playlistMode) ? $playlistMode : '');

		$errors = $this->formInputHandler->validate($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$saveData = $this->collectSaveData([], $post);
		if ($this->playlistsService->createNew($saveData) === 0)
			return ['success' => false, 'errors' => ['Playlist “' . $this->getPlaylistName($post) . '“ could not be stored.']];

		return ['success' => true];
	}

	/**
	 * The playlist must be loaded with loadPlaylistForEdit() before, so the ACL is checked.
	 *
	 * @param array<string,mixed> $post
	 * @return array{success: bool, errors?: string[]}
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function storeEditSettings(array $post): array
	{
		$playlist = $this->getLoadedPlaylist();
		$this->settingsParametersPolicy->addEditFormElements($playlist);

		$errors = $this->formInputHandler->validate($post);
		if ($errors !== [])
			return ['success' => false, 'errors' => $errors];

		$layout   = $playlist['layout'] ?? [];
		$saveData = $this->collectSaveData(is_array($layout) ? $layout : [], $post);
		try
		{
			// a save without changes affects no rows but is not a failure
			$this->playlistsService->updateSecure($saveData);
		}
		catch (ModuleException $e)
		{
			return ['success' => false, 'errors' => [$e->getMessage()]];
		}

		return ['success' => true];
	}

	/**
	 * @param array<string,mixed> $layout
	 * @param array<string,mixed> $post
	 * @return array<string,mixed>
	 */
	private function collectSaveData(array $layout, array $post): array
	{
		$saveData = $this->formInputHandler->getParsed();
		if (!array_key_exists(Parameters::PARAMETER_SCREEN_WIDTH, $post) || !array_key_exists(Parameters::PARAMETER_SCREEN_HEIGHT, $post))
			return $saveData;

		$layout['resolution'] = $post[Parameters::PARAMETER_SCREEN_WIDTH] . 'x' . $post[Parameters::PARAMETER_SCREEN_HEIGHT];
		// complete the layout, so the multizone editor gets a valid structure for new playlists
		$layout += ['zoom' => 70, 'export_unit' => 'pixel', 'zones' => []];
		$saveData['layout'] = serialize($layout);

		return $saveData;
	}

	/**
	 * @return array{UID: int, company_id: int, playlist_mode: string, playlist_name: string, ...}
	 * @throws ModuleException
	 */
	private function getLoadedPlaylist(): array
	{
		if ($this->playlist === [])
			throw new ModuleException('playlists', 'No playlist loaded for editing.');

		return $this->playlist;
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function getPlaylistName(array $post): string
	{
		$name = $post[Parameters::PARAMETER_PLAYLIST_NAME] ?? '';
		return is_string($name) ? $name : '';
	}
}
