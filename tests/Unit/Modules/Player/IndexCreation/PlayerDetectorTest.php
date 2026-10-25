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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Config\Config;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\PlayerDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

class PlayerDetectorTest extends TestCase
{
	/**
	 * @return array<string,array{PlayerModel,list<string>}>
	 */
	public static function models(): array
	{
		return [
			'iadea 1x0'   => [PlayerModel::IADEA_XMP1X0, ['XMP-120', 'XMP-130', 'XDS-101', 'XDS-104', 'XDS-151']],
			'iadea 3x0'   => [PlayerModel::IADEA_XMP3X0, ['XMP-320', 'XMP-330', 'XMP-340', 'XDS-195', 'XDS-245', 'GDATA-1100']],
			'iadea 3x50'  => [PlayerModel::IADEA_XMP3X50, ['XMP-3250', 'XMP-3350', 'XMP-3450', 'XDS-1950', 'XDS-2450']],
			'compatible'  => [PlayerModel::COMPATIBLE, ['fs5-player', 'fs5-playerSTLinux', 'NTnextPlayer', 'Kathrein', 'NT111', 'NTwin']],
			'iadea 2x00'  => [PlayerModel::IADEA_XMP2X00, ['XMP-2200', 'MBR-1100', 'XMP-6200', 'XMP-6250', 'XMP-6400', 'XMP-7300', 'XMP-8552', 'XDS-1060', 'XDS-1062', 'XDS-1068', 'XDS-1078', 'XDS-1071', 'XDS-1078-A9', 'XDS-1078-A12', 'XDS-1088-H', 'XDS-1588', 'XDS-1588-A']],
			'garlic'      => [PlayerModel::GARLIC, ['Garlic']],
			'ids'         => [PlayerModel::IDS, ['IDS-App']],
			'qbic'        => [PlayerModel::QBIC, ['BXP-202', 'BXP-301', 'TD-1050']],
			'screenlite'  => [PlayerModel::SCREENLITE, ['ScreenliteWeb']],
			'unknown'     => [PlayerModel::UNKNOWN, ['', 'XMP', 'xmp-120', 'XMP-120 ', 'Garlic2', 'garlic', '<script>', "XMP-120\n"]],
		];
	}

	/**
	 * @param list<string> $names
	 */
	#[Group('units')]
	#[DataProvider('models')]
	public function testEveryModelNameLeadsToItsModel(PlayerModel $model, array $names): void
	{
		$detector = new PlayerDetector(new Config(new RecordingConfigLoader([])));

		foreach ($names as $name)
			static::assertSame($model, $detector->detectModelId($name)->getModelId(), "'" . $name . "'");
	}

	#[Group('units')]
	public function testTheDetectorCanBeUsedAgain(): void
	{
		$detector = new PlayerDetector(new Config(new RecordingConfigLoader([])));

		static::assertSame(PlayerModel::GARLIC, $detector->detectModelId('Garlic')->getModelId());
		static::assertSame(PlayerModel::UNKNOWN, $detector->detectModelId('nothing')->getModelId());
	}
}
