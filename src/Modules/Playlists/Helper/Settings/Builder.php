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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class Builder
 *
 * This class is responsible for handling the configuration of parameters,
 * building forms, and processing user inputs for playlists. It encapsulates
 * ACL validation, parameter management, and form creation functionalities.
 */
class Builder
{
	private readonly FormElementsCreator $formElementsCreator;
	private readonly AclValidator $aclValidator;
	private readonly Validator $validator;
	private readonly Parameters $parameters;
	private int $UID;
	private string $username;
	private string $edition;

	public function __construct(AclValidator $aclValidator, Parameters $parameters, Validator $validator, FormElementsCreator $formElementsCreator)
	{
		$this->aclValidator         = $aclValidator;
		$this->parameters           = $parameters;
		$this->validator            = $validator;
		$this->formElementsCreator  = $formElementsCreator;
		$this->edition              = $this->aclValidator->getConfig()->getEdition();
	}

	public function init(Session $session): static
	{
		/** @var array{UID: int, username: string} $user */
		$user = $session->get('user');
		$this->UID      = $user['UID'];
		$this->username = $user['username'];

		return $this;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws Exception
	 */
	public function configNewParameter(string $playlistMode): void
	{
		$this->parameters->addPlaylistMode();
		if (!$this->aclValidator->isSimpleAdmin($this->UID))
			return;

		$this->parameters->addOwner();

		if ($this->edition !== Config::PLATFORM_EDITION_EDGE && $this->isTimeLimitPlaylist($playlistMode))
			$this->parameters->addTimeLimit();
	}

	/**
	 * @param array{"UID": int, "company_id": int, playlist_mode: string, playlist_name:string, ...}$playlist
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	public function configEditParameter(array $playlist): void
	{
		$this->parameters->addPlaylistId();
		if (!$this->aclValidator->isAdmin($this->UID, $playlist))
			return;

		$this->parameters->addOwner();

		if ($this->edition !== Config::PLATFORM_EDITION_EDGE && $this->isTimeLimitPlaylist($playlist['playlist_mode']))
			$this->parameters->addTimeLimit();
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws ModuleException
	 */
	public function buildForm(array $playlist): array
	{
		$form       = [];
		$form['playlist_name'] = $this->formElementsCreator->createPlaylistNameField(
			$playlist[Parameters::PARAMETER_PLAYLIST_NAME] ?? ''
		);

		if ($this->parameters->hasParameter(BaseParameters::PARAMETER_UID))
		{
			$form['UID'] = $this->formElementsCreator->createUIDField(
				$playlist[BaseParameters::PARAMETER_UID] ?? $this->UID,
				$playlist['username'] ?? $this->username,
				$this->UID
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_TIME_LIMIT))
		{
			$form['time_limit'] = $this->formElementsCreator->createTimeLimitField(
				$playlist[Parameters::PARAMETER_TIME_LIMIT] ?? $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT),
				$this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TIME_LIMIT)
			);
		}

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_ID))
			$form['playlist_id'] = $this->formElementsCreator->createHiddenPlaylistIdField($playlist[Parameters::PARAMETER_PLAYLIST_ID]);

		if ($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_MODE))
			$form['playlist_mode'] = $this->formElementsCreator->createPlaylistModeField($playlist[Parameters::PARAMETER_PLAYLIST_MODE]);

		$form['csrf_token'] = $this->formElementsCreator->createCSRFTokenField();

		return $this->formElementsCreator->prepareForm($form);
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function handleUserInput(array $post): array
	{
		$this->parameters->setUserInputs($post)
			->parseInputAllParameters();

		return $this->validator->validateUserInput($post);
	}


	private function isTimeLimitPlaylist(string $playlistMode): bool
	{
		return ($playlistMode == PlaylistMode::INTERNAL->value || $playlistMode == PlaylistMode::MASTER->value);
	}

}