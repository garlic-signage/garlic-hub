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

namespace App\Modules\Playlists\Contracts;

use Doctrine\DBAL\Exception;

/**
 * Implemented by modules that reference playlists (e.g. Player), so Playlists can
 * tell whether a playlist is in use without knowing how those modules store it.
 */
interface PlaylistUsageFinderInterface
{
	/**
	 * @param int[] $playlistIds
	 * @return list<int> the given playlist ids that are referenced
	 * @throws Exception
	 */
	public function findUsedPlaylistIds(array $playlistIds): array;
}
