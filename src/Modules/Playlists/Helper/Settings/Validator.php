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

use App\Framework\Core\BaseValidator;
use App\Framework\Core\CsrfToken;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\PlaylistMode;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Validator
 *
 * Extends the BaseValidator to perform additional validation for user inputs,
 * specifically related to playlist management. Handles CSRF token validation
 * and checks for the presence and validity of required playlist parameters.
 */
class Validator extends BaseValidator
{
	private Parameters $inputEditParameters;

	public function __construct(Translator $translator, Parameters $inputEditParameters, CsrfToken $csrfToken)
	{
		parent::__construct($translator, $csrfToken);
		$this->inputEditParameters = $inputEditParameters;
	}

	/**
	 * @param array<string, mixed> $post
	 * @return string[]
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function validateUserInput(array $post): array
	{
		$errors = $this->validateFormCsrfToken($this->inputEditParameters);

		if (empty($this->inputEditParameters->getValueOfParameter(Parameters::PARAMETER_PLAYLIST_NAME)))
			$errors[] = $this->translator->translate('no_playlist_name', 'playlists');

		// we need userInput here as getValueOfParameter will throw an exception if not set
		if (!isset($post[Parameters::PARAMETER_PLAYLIST_MODE]) && !isset($post[Parameters::PARAMETER_PLAYLIST_ID]))
			$errors[] = $this->translator->translate('parameters_missing', 'playlists');

		if (isset($post[Parameters::PARAMETER_PLAYLIST_MODE]) && !$this->checkPlaylistMode($post))
			$errors[] = sprintf($this->translator->translate('playlist_mode_unsupported', 'playlists'), $post['playlist_mode']);

		return $errors;
	}

	/**
	 * @param array<string,mixed> $userInputs
	 */
	private function checkPlaylistMode(array $userInputs): bool
	{
		return in_array($userInputs[Parameters::PARAMETER_PLAYLIST_MODE], array_column(PlaylistMode::cases(), 'value'), true);
	}

}