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

namespace App\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\ReleaseStatus;
use Doctrine\DBAL\Exception;

/**
 * The release of the playlists, a switch in the settings of the module which is off by default.
 * While it is on, the owner and the editors ask for the release and only an admin exports.
 * The status is written while it is off, too (see ReleaseStatus).
 */
readonly class PlaylistRelease
{
	public function __construct(
		private bool $enabled,
		private PlaylistsService $playlistsService,
		private AclValidator $aclValidator,
		private UserSession $userSession
	) {}

	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * @param array<string,mixed> $playlist with the company of the owner
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function mayExport(array $playlist): bool
	{
		if (!$this->enabled)
			return true;

		$owner = ['UID' => (int) ($playlist['UID'] ?? 0), 'company_id' => (int) ($playlist['company_id'] ?? 0)];

		return $this->aclValidator->isAdmin($this->userSession->getUID(), $owner);
	}

	/**
	 * Whoever may edit the playlist asks for its release.
	 *
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws NotFoundException
	 * @throws TranslatableModuleException
	 * @throws UserException
	 */
	public function requestRelease(int $playlistId): void
	{
		if (!$this->enabled)
			throw new TranslatableModuleException('playlists', 'release_not_enabled', ['playlist_id' => $playlistId]);

		$this->playlistsService->loadWithUserById($playlistId); // checks the rights
		$this->playlistsService->update($playlistId, ['release_status' => ReleaseStatus::READY->value]);
	}

	/**
	 * The number of the playlists which wait for the release, for the dashboard of the module admin.
	 * Null when there is nothing to show: the release is off or the user is no module admin.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	public function countWaiting(): ?int
	{
		if (!$this->enabled || !$this->aclValidator->isModuleAdmin($this->userSession->getUID()))
			return null;

		return $this->playlistsService->countByReleaseStatus(ReleaseStatus::READY);
	}
}
