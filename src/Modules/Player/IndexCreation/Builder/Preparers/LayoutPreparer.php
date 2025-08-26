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

use App\Modules\Playlists\Helper\PlaylistMode;

/**
 * Class LayoutPreparer is responsible for preparing layouts for a player entity.
 * It determines the layout structure and regions based on the playlist mode and other properties.
 * Supports single layout and multizone configurations.
 */
class LayoutPreparer extends AbstractPreparer implements PreparerInterface
{
	/**
	 * @return list<array<string,mixed>>
	 */
	public function prepare(): array
	{
		$properties = $this->playerEntity->getProperties();

		$layout = $this->replaceRootLayout($properties['width'], $properties['height']);
		if ($this->playerEntity->getPlaylistMode() == PlaylistMode::MULTIZONE->value)
		{
			$layout['regions'] = $this->replaceMultizoneRegions();
		}
		else
		{
			$layout['regions'][] = $this->replaceRegion('', '0', '0', $properties['width'], $properties['height'], '0');
		}
		return [$layout];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function replaceRootLayout(string|int $width, string|int $height): array
	{
		return [
			'ROOT_LAYOUT_WIDTH' => $width,
			'ROOT_LAYOUT_HEIGHT' => $height
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function replaceMultizoneRegions(): array
	{
		$zones = $this->playerEntity->getZones();

		$regions = [];

		foreach ($zones['zones'] as $key => $value)
		{
			if ($zones['export_unit'] == 'percent')
			{
				$regions[] = $this->replaceRegion($key
					, $value['zone_top'] . '%', $value['zone_left'] . '%', $value['zone_width'] . '%', $value['zone_height'] . '%', $value['zone_z-index'], $value['zone_bgcolor']);
			}
			else
			{
				$regions[] = $this->replaceRegion(
					$key,
					$value['zone_top'], $value['zone_left'], $value['zone_width'], $value['zone_height'],
					$value['zone_z-index'], $value['zone_bgcolor']);
			}
		}
		return $regions;
	}

	/**
	 * @return array<string,string|int>
	 */
	private function replaceRegion(string|int $screenId, string|int $top, string|int $left, string|int $width, string|int $height, string|int $zIndex, string $bgColor = 'transparent'): array
	{
		return [
			'SCREEN_ID' => $screenId,
			'REGION_TOP' => $top,
			'REGION_LEFT' => $left,
			'REGION_WIDTH' => $width,
			'REGION_HEIGHT' => $height,
			'REGION_Z_INDEX' => $zIndex,
			'REGION_BGCOLOR' => $bgColor
		];
	}

}

