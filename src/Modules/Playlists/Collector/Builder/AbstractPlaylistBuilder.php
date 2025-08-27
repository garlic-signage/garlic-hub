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

namespace App\Modules\Playlists\Collector\Builder;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Playlists\Collector\Contracts\PlaylistBuilderInterface;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;

/**
 * An abstract class that serves as a blueprint for building playlists.
 * Defines a structure and common properties required for creating playlist builders.
 */
abstract class AbstractPlaylistBuilder implements PlaylistBuilderInterface
{
	protected PlayerEntity $playerEntity;
	protected BuildHelper $buildHelper;
	protected SimplePlaylistStructureFactory $simplePlaylistStructureFactory;

	public function __construct(PlayerEntity $playerEntity, BuildHelper $buildHelper, SimplePlaylistStructureFactory $simplePlaylistStructureFactory)
	{
		$this->playerEntity = $playerEntity;
		$this->buildHelper = $buildHelper;
		$this->simplePlaylistStructureFactory = $simplePlaylistStructureFactory;
	}

}
