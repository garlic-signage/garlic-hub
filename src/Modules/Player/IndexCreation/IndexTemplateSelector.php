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

namespace App\Modules\Player\IndexCreation;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\TemplateIndexFiles;

/**
 * Provides functionality to select the appropriate index template file
 * based on the player's model and firmware version.
 */
class IndexTemplateSelector
{
	public function select(PlayerEntity $playerEntity): TemplateIndexFiles
	{
		switch ($playerEntity->getModel())
		{
			case PlayerModel::IADEA_XMP2X00:
			case PlayerModel::QBIC:
				$indexFileName = TemplateIndexFiles::XMP2XXX;
				break;
			case PlayerModel::GARLIC:
				$garlic_build = $this->determineGarlicBuild($playerEntity->getFirmwareVersion());
				if ($garlic_build >= 566)
					$indexFileName = TemplateIndexFiles::GARLIC;
				else
					$indexFileName = TemplateIndexFiles::SIMPLE;
				break;
			case PlayerModel::IADEA_XMP1X0:
			case PlayerModel::IADEA_XMP3X0:
			case PlayerModel::IADEA_XMP3X50:
			case PlayerModel::IDS:
			case PlayerModel::SCREENLITE:
			case PlayerModel::COMPATIBLE:
			default:
				$indexFileName = TemplateIndexFiles::SIMPLE;
				break;
		}
		return $indexFileName;
	}

	private function determineGarlicBuild(string $firmwareVersion): int
	{
		$ar = explode('L', $firmwareVersion);
		$ar = explode('.', $ar[0]);

		return (int) end($ar);
	}

}