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

namespace App\Modules\Playlists\Collector;

use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;

/**
 * Represents a simple playlist structure that implements the PlaylistStructureInterface.
 * This class handles items, prefetch, and exclusive properties for a playlist.
 */
class SimplePlaylistStructure implements PlaylistStructureInterface
{
	private string $items;
	private string $prefetch;
	private string $exclusive;

	public function __construct(string $items = '', string $prefetch = '', string $exclusive = '')
	{
		$this->items = $items;
		$this->prefetch = $prefetch;
		$this->exclusive = $exclusive;
	}

	public function getItems(): string
	{
		return $this->items;
	}

	public function getPrefetch(): string
	{
		return $this->prefetch;
	}

	public function getExclusive(): string
	{
		return $this->exclusive;
	}
}
