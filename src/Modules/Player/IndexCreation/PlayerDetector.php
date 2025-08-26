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

use App\Framework\Core\Config\Config;
use App\Modules\Player\Enums\PlayerModel;

/**
 * A class responsible for detecting the player model based on a provided model name.
 */
class PlayerDetector
{
	protected PlayerModel $modelId;

	protected Config $config;

	public function __construct(Config $Config)
	{
		$this->config = $Config;
	}

	public function getModelId(): PlayerModel
	{
		return $this->modelId;
	}

	public function detectModelId(string $modelName): static
	{
		$this->modelId = match ($modelName)
		{
			'XMP-120', 'XMP-130', 'XDS-101', 'XDS-104', 'XDS-151' => PlayerModel::IADEA_XMP1X0,
			'XMP-320', 'XMP-330', 'XMP-340', 'XDS-195', 'XDS-245', 'GDATA-1100' => PlayerModel::IADEA_XMP3X0,
			'XMP-3250', 'XMP-3350', 'XMP-3450', 'XDS-1950', 'XDS-2450' => PlayerModel::IADEA_XMP3X50,
			'fs5-player', 'fs5-playerSTLinux', 'NTnextPlayer', 'Kathrein', 'NT111', 'NTwin' => PlayerModel::COMPATIBLE,
			'XMP-2200', 'MBR-1100', 'XMP-6200', 'XMP-6250', 'XMP-6400', 'XMP-7300', 'XMP-8552', 'XDS-1060', 'XDS-1062', 'XDS-1068', 'XDS-1078', 'XDS-1071', 'XDS-1078-A9', 'XDS-1078-A12', 'XDS-1088-H', 'XDS-1588', 'XDS-1588-A' => PlayerModel::IADEA_XMP2X00,
			'Garlic' => PlayerModel::GARLIC,
			'IDS-App' => PlayerModel::IDS,
			'BXP-202', 'BXP-301', 'TD-1050' => PlayerModel::QBIC,
			'ScreenliteWeb' => PlayerModel::SCREENLITE,
			default => PlayerModel::UNKNOWN,
		};
		return $this;
	}
}