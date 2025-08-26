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

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\IndexSections;
use Exception;

/**
 * Factory class responsible for creating instances of preparers based on the provided index section.
 */
class PreparerFactory
{
	/**
	 * @throws Exception
	 */
	public function create(IndexSections $indexSections, PlayerEntity $playerEntity): PreparerInterface
	{
		return match ($indexSections)
		{
			IndexSections::META => new MetaPreparer($playerEntity),
			IndexSections::SUBSCRIPTIONS => new SubscriptionPreparer($playerEntity),
			IndexSections::LAYOUT => new LayoutPreparer($playerEntity),
			IndexSections::STANDBY_TIMES => new ScreenTimesPreparer($playerEntity),
			IndexSections::PLAYLIST => new PlaylistPreparer($playerEntity),
			default => throw new Exception('Unknown index section'),
		};
	}
}