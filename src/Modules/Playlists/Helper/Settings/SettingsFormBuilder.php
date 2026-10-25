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
		// the values may come from the request of the user: only values of the right kind are shown
		$form[Parameters::PARAMETER_PLAYLIST_NAME] = $this->formElementsCreator->createPlaylistNameField(
			$this->text($playlist, Parameters::PARAMETER_PLAYLIST_NAME)
		);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form[BaseParameters::PARAMETER_UID] = $this->formElementsCreator->createUIDField(
				$this->number($playlist, BaseParameters::PARAMETER_UID) ?? $this->userSession->getUID(),
				$this->text($playlist, 'username') ?: $this->userSession->getUsername(),
				$this->userSession->getUID()
			);
		}

		if (array_key_exists(Parameters::PARAMETER_PLAYLIST_MODE, $playlist) &&
			($playlist[Parameters::PARAMETER_PLAYLIST_MODE] === PlaylistMode::MASTER->value || $playlist[Parameters::PARAMETER_PLAYLIST_MODE] === PlaylistMode::MULTIZONE->value))
		{
			$form[Parameters::PARAMETER_SCREEN_WIDTH]  = $this->formElementsCreator->createScreenWidthField($this->number($playlist, Parameters::PARAMETER_SCREEN_WIDTH) ?? 1920);
			$form[Parameters::PARAMETER_SCREEN_HEIGHT] = $this->formElementsCreator->createScreenHeightField($this->number($playlist, Parameters::PARAMETER_SCREEN_HEIGHT) ?? 1080);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_TIME_LIMIT))
		{
			$form[Parameters::PARAMETER_TIME_LIMIT] = $this->formElementsCreator->createTimeLimitField(
				$this->number($playlist, Parameters::PARAMETER_TIME_LIMIT) ?? $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT),
				$this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT)
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_ID))
			$form[Parameters::PARAMETER_PLAYLIST_ID] = $this->formElementsCreator->createHiddenPlaylistIdField($this->number($playlist, Parameters::PARAMETER_PLAYLIST_ID) ?? 0);

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_MODE))
			$form[Parameters::PARAMETER_PLAYLIST_MODE] = $this->formElementsCreator->createPlaylistModeField($this->text($playlist, Parameters::PARAMETER_PLAYLIST_MODE));

		$form[BaseEditParameters::PARAMETER_CSRF_TOKEN] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function text(array $values, string $key): string
	{
		$value = $values[$key] ?? '';

		return is_string($value) ? $value : '';
	}

	/**
	 * A number from the database (5) or from a form ("5"), null when the value is missing or no number.
	 *
	 * @param array<string,mixed> $values
	 */
	private function number(array $values, string $key): ?int
	{
		$value = $values[$key] ?? null;

		return is_int($value) || (is_string($value) && preg_match('/^-?\d{1,9}$/D', $value) === 1) ? (int) $value : null;
	}
}
