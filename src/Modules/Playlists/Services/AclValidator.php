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

namespace App\Modules\Playlists\Services;

use App\Shared\Acl\AbstractAclValidator;
use App\Shared\Acl\AclHelper;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Doctrine\DBAL\Exception;

class AclValidator extends AbstractAclValidator
{

	public function __construct(AclHelper $aclHelper)
	{
		parent::__construct('playlists', $aclHelper);
	}

	/**
	 * @param int $UID
	 * @param array{"UID": int, "company_id": int, "playlist_id": int, ...} $playlist
	 * @return bool
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isPlaylistEditable(int $UID, array $playlist): bool
	{
		if ($this->isAllowedToDeletePlaylist($UID, $playlist))
			return true;

		if($this->isEditorWithAccessOnUnit($UID, $playlist['playlist_id']))
			return true;

		return false;
	}

	/**
	 * @param int $UID
	 * @param array{"UID": int, "company_id": int, "playlist_id": int, ...} $playlist
	 * @return bool
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function isAllowedToDeletePlaylist(int $UID, array $playlist): bool
	{
		if ($UID == $playlist['UID'])
			return true;

		return $this->isAdmin($UID, $playlist);
	}
}