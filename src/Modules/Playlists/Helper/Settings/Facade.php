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

namespace App\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Services\PlaylistsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * The Facade class is responsible for orchestrating operations related to the playlists feature.
 * It acts as an intermediary between several components such as the form builder, playlists service, and translator.
 */
class Facade
{
	private readonly Builder $settingsFormBuilder;
	private readonly PlaylistsService $playlistsService;
	private readonly Parameters $settingsParameters;
	/** @var array{"UID": int, "company_id": int, playlist_mode: string,...}|array<empty, empty> */
	private array $oldPlaylist;
	private Translator $translator;

	public function __construct(Builder $settingsFormBuilder, PlaylistsService $playlistsService, Parameters $settingsParameters)
	{
		$this->settingsFormBuilder = $settingsFormBuilder;
		$this->playlistsService    = $playlistsService;
		$this->settingsParameters  = $settingsParameters;
	}

	public function init(Translator $translator, Session $session): void
	{
		$this->translator = $translator;
		$this->settingsFormBuilder->init($session);
		/** @var array{UID: int} $user */
		$user = $session->get('user');
		$this->playlistsService->setUID($user['UID']);
	}

	/**
	 * @return array{"UID": int, "company_id": int, playlist_mode: string, playlist_name:string, ...}|array<empty,empty>
	 */
	public function loadPlaylistForEdit(int $playlistId): array
	{
		$this->oldPlaylist = $this->playlistsService->loadPlaylistForEdit($playlistId);

		return $this->oldPlaylist;
	}


	/**
	 * @param array{playlist_id?: int, playlist_mode: string, playlist_name:string, ...}  $post
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function configurePlaylistFormParameter(array $post): array
	{
		if (isset($post['playlist_id']) && $post['playlist_id'] > 0)
		{
			$this->oldPlaylist = $this->playlistsService->loadPlaylistForEdit((int) $post['playlist_id']);
			if ($this->oldPlaylist === [])
				throw new ModuleException('palylists', 'No playlist found for editing.');

			// @phpstan-ignore-next-line // stop phpstan bullshitting about a not empty array
			$this->settingsFormBuilder->configEditParameter($this->oldPlaylist);
		}
		else
		{
			$this->settingsFormBuilder->configNewParameter($post['playlist_mode']);
		}

		return $this->settingsFormBuilder->handleUserInput($post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function storePlaylist(array $post): int
	{
		$saveData  = array_combine(
			$this->settingsParameters->getInputParametersKeys(),
			$this->settingsParameters->getInputValuesArray()
		);

		$playlistId = (int) ($post['playlist_id'] ?? 0);
		if ($playlistId > 0)
			$id = $this->playlistsService->updateSecure($saveData);
		else
			$id = $this->playlistsService->createNew($saveData);

		return $id;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function buildCreateNewParameter(string $playlistMode): void
	{
		$this->settingsFormBuilder->configNewParameter($playlistMode);
	}

	/**
	 * @param array{"UID": int, "company_id": int, playlist_mode: string, playlist_name:string, ...} $playlist
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function buildEditParameter(array $playlist): void
	{
		$this->settingsFormBuilder->configEditParameter($playlist);
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function prepareUITemplate(array $post): array
	{
		$playlistMode = strtolower($post['playlist_mode'] ?? $this->oldPlaylist['playlist_mode']);
		$title =  $this->translator->translate('settings', 'playlists'). ' - ' .
			$this->translator->translateArrayForOptions('playlist_mode_selects', 'playlists')[$playlistMode];

		$dataSections                      = $this->settingsFormBuilder->buildForm($post);
		$dataSections['title']             = $title;
		$dataSections['additional_css']    = ['/css/playlists/settings.css'];
		$dataSections['footer_modules']    = ['/js/playlists/settings/init.js'];
		$dataSections['template_name']     = 'playlists/edit';
		$dataSections['form_action']       = '/playlists/settings';
		$dataSections['save_button_label'] = $this->translator->translate('save', 'main');

		return $dataSections;
	}

}