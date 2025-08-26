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


namespace App\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Entities\PlayerEntity;

/**
 * Represents an abstract base class for preparing or handling operations
 * related to the PlayerEntity. This class cannot be instantiated directly
 * but provides a foundational structure for derived classes.
 *
 * The class ensures the encapsulation of the PlayerEntity instance,
 * making it available to subclasses through inheritance.
 */
abstract class AbstractPreparer
{
	protected readonly PlayerEntity $playerEntity;

	public function __construct(PlayerEntity $playerEntity)
	{
		$this->playerEntity = $playerEntity;
	}
}