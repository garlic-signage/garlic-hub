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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use Doctrine\DBAL\Exception;

/**
 * Decides which form parameters are available, depending on the playlist mode,
 * the rights of the current user and the edition.
 */
class SettingsParametersPolicy
{
	public function __construct(private readonly Parameters $parameters,
								private readonly AclValidator $aclValidator,
								private readonly UserSession $userSession)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function addCreateFormElements(string $playlistMode): void
	{
		$this->parameters->addPlaylistMode();

		if ($this->hasResolution($playlistMode))
			$this->parameters->addResolution();

		if (!$this->aclValidator->isSimpleAdmin($this->userSession->getUID()))
			return;

		$this->addAdminFormElements($playlistMode);
	}

	/**
	 * @param array{UID: int, company_id: int, playlist_mode: string, ...} $playlist
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function addEditFormElements(array $playlist): void
	{
		$this->parameters->addPlaylistId();

		if ($this->hasResolution($playlist['playlist_mode']))
			$this->parameters->addResolution();

		if (!$this->aclValidator->isAdmin($this->userSession->getUID(), $playlist))
			return;

		$this->addAdminFormElements($playlist['playlist_mode']);
	}

	private function addAdminFormElements(string $playlistMode): void
	{
		$this->parameters->addOwner();

		if ($this->aclValidator->getConfig()->getEdition() !== Config::PLATFORM_EDITION_EDGE && $this->isTimeLimitPlaylist($playlistMode))
			$this->parameters->addTimeLimit();
	}

	private function hasResolution(string $playlistMode): bool
	{
		return ($playlistMode === PlaylistMode::MASTER->value || $playlistMode === PlaylistMode::MULTIZONE->value);
	}

	private function isTimeLimitPlaylist(string $playlistMode): bool
	{
		return ($playlistMode === PlaylistMode::INTERNAL->value || $playlistMode === PlaylistMode::MASTER->value);
	}
}
