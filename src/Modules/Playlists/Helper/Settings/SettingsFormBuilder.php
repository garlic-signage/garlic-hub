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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\PlaylistMode;

readonly class SettingsFormBuilder
{
	public function __construct(private Parameters          $parameters,
								private UserSession         $userSession,
								private FormElementsCreator $formElementsCreator)
	{}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function buildForm(array $playlist): array
	{
		$form = [];
		$form[Parameters::PARAMETER_PLAYLIST_NAME] = $this->formElementsCreator->createPlaylistNameField(
			$playlist[Parameters::PARAMETER_PLAYLIST_NAME] ?? ''
		);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form[BaseParameters::PARAMETER_UID] = $this->formElementsCreator->createUIDField(
				$playlist[BaseParameters::PARAMETER_UID] ?? $this->userSession->getUID(),
				$playlist['username'] ?? $this->userSession->getUsername(),
				$this->userSession->getUID()
			);
		}

		if (array_key_exists(Parameters::PARAMETER_PLAYLIST_MODE, $playlist) &&
			($playlist[Parameters::PARAMETER_PLAYLIST_MODE] === PlaylistMode::MASTER->value || $playlist[Parameters::PARAMETER_PLAYLIST_MODE] === PlaylistMode::MULTIZONE->value))
		{
			$form[Parameters::PARAMETER_SCREEN_WIDTH]  = $this->formElementsCreator->createScreenWidthField($playlist[Parameters::PARAMETER_SCREEN_WIDTH] ?? 1920);
			$form[Parameters::PARAMETER_SCREEN_HEIGHT] = $this->formElementsCreator->createScreenHeightField($playlist[Parameters::PARAMETER_SCREEN_HEIGHT] ?? 1080);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_TIME_LIMIT))
		{
			$form[Parameters::PARAMETER_TIME_LIMIT] = $this->formElementsCreator->createTimeLimitField(
				$playlist[Parameters::PARAMETER_TIME_LIMIT] ?? $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT),
				$this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT)
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_ID))
			$form[Parameters::PARAMETER_PLAYLIST_ID] = $this->formElementsCreator->createHiddenPlaylistIdField($playlist[Parameters::PARAMETER_PLAYLIST_ID]);

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_MODE))
			$form[Parameters::PARAMETER_PLAYLIST_MODE] = $this->formElementsCreator->createPlaylistModeField($playlist[Parameters::PARAMETER_PLAYLIST_MODE]);

		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}
}
