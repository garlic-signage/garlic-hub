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

namespace App\Modules\Player\IndexCreation\Builder\Preparers;


use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;

/**
 * Handles the preparation of playlist data based on the provided structure and configuration.
 */
class PlaylistPreparer extends AbstractPreparer implements PreparerInterface
{
	private PlaylistStructureInterface $playlistStructure;
	private bool $isSimple = true;

	public function setPlaylistStructure(PlaylistStructureInterface $playlistStructure): static
	{
		$this->playlistStructure = $playlistStructure;
		return $this;
	}

	public function setIsSimple(bool $isSimple): static
	{
		$this->isSimple = $isSimple;
		return $this;
	}

	/**
	 * @return array<array<string,int|string>>
	 */
	public function prepare(): array
	{
		if (!$this->isSimple)
			return [[
				'INSERT_PRIORITY_CLASSES'  => $this->playlistStructure->getExclusive(),
				'INSERT_ELEMENTS'          => $this->playlistStructure->getItems(),
				'INSERT_PREFETCH_ELEMENTS' => $this->playlistStructure->getPrefetch(),
				'PREFETCH_REFRESH_TIME'    => $this->calculatePrefetchDuration()
			]];
		else
			return [[
				'INSERT_ELEMENTS'          => $this->playlistStructure->getItems(),
			]];

	}

	public function calculatePrefetchDuration(): int
	{
		if ($this->playerEntity->getDuration() < $this->playerEntity->getRefresh())
			$duration = $this->playerEntity->getRefresh() + 1;
		else
			$duration = $this->playerEntity->getDuration()  + 1;

		if ($duration < 900)
			$duration = 900;
		else
			$duration = $duration * 2;

		return $duration;
	}
}