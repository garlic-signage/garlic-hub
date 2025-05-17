<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


namespace App\Modules\Playlists\Services\InsertItems;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;

abstract class AbstractInsertItem extends AbstractBaseService
{
	protected ItemsRepository $itemsRepository;
	protected PlaylistsService $playlistsService;
	protected PlaylistMetricsCalculator $playlistMetricsCalculator;

	abstract public function insert(int $playlistId, int|string $insertId, int $position): array;

	/**
	 * @throws ModuleException
	 */
	protected function checkPlaylistAcl(int $playlistId): array
	{
		$this->playlistsService->setUID($this->UID);
		$this->playlistMetricsCalculator->setUID($this->UID);
		$playlistData = $this->playlistsService->loadPlaylistForEdit($playlistId); // also checks rights
		if (empty($playlistData))
			throw new ModuleException('items', 'Playlist is not accessible');

		return $playlistData;
	}
}