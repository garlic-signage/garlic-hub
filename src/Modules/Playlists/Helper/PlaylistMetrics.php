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

namespace App\Modules\Playlists\Helper;

/**
 * Immutable result of a playlist metrics calculation.
 */
final readonly class PlaylistMetrics
{
	public function __construct(
		public int $countEntries = 0,
		public int $countOwnerEntries = 0,
		public int $fileSize = 0,
		public int $duration = 0,
		public int $ownerDuration = 0
	) {}

	/**
	 * @return array{count_items:int, count_owner_items:int, filesize:int, duration:int, owner_duration:int}
	 */
	public function toFrontendArray(): array
	{
		return [
			'count_items'       => $this->countEntries,
			'count_owner_items' => $this->countOwnerEntries,
			'filesize'          => $this->fileSize,
			'duration'          => $this->duration,
			'owner_duration'    => $this->ownerDuration
		];
	}

	/**
	 * @return array{filesize:int, duration:int, owner_duration:int}
	 */
	public function toPlaylistTableArray(): array
	{
		return [
			'filesize'       => $this->fileSize,
			'duration'       => $this->duration,
			'owner_duration' => $this->ownerDuration
		];
	}

	/**
	 * Metrics of the items which represent the playlist inside other playlists.
	 *
	 * @return array{item_duration:int, item_filesize:int}
	 */
	public function toItemArray(): array
	{
		return [
			'item_duration' => $this->duration,
			'item_filesize' => $this->fileSize
		];
	}
}
