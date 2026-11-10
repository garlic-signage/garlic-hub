<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Playlists\Helper\Compose;

use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Helper\Playlists\ZonesSchema;

/**
 * The fixed layouts of the zone editor from config/multizone_presets.json. The zones are in percent,
 * so a preset fits every resolution. Every preset is checked like a saved layout and must lie inside of the screen,
 * a broken file stops the editor with the name of the preset instead of giving the editor wrong zones.
 */
readonly class MultizonePresets
{
	public function __construct(private string $file, private ZonesSchema $zonesSchema)
	{}

	/**
	 * @return list<array{id:string, zones:list<array<string,int|float|string>>}>
	 * @throws CoreException
	 */
	public function load(): array
	{
		$content = is_readable($this->file) ? file_get_contents($this->file) : false;
		if ($content === false)
			throw new CoreException('Multizone presets not readable: ' . $this->file);

		$presets = json_decode($content, true);
		if (!is_array($presets) || !array_is_list($presets))
			throw new CoreException('Multizone presets are not a list: ' . $this->file);

		$parsed = [];
		foreach ($presets as $index => $preset)
		{
			$parsedPreset = is_array($preset) ? $this->parsePreset($preset) : null;
			if ($parsedPreset === null)
				throw new CoreException('Multizone preset not valid: ' . (is_array($preset) && is_string($preset['id'] ?? null) ? $preset['id'] : '#' . $index));

			$parsed[] = $parsedPreset;
		}

		return $parsed;
	}

	/**
	 * @param array<mixed> $preset
	 * @return array{id:string, zones:list<array<string,int|float|string>>}|null
	 */
	private function parsePreset(array $preset): ?array
	{
		// the id is the key of the translated name
		$id    = $preset['id'] ?? null;
		$zones = $preset['zones'] ?? null;
		if (!is_string($id) || preg_match('/^[a-z0-9_]{1,50}$/D', $id) !== 1 || !is_array($zones) || $zones === [] || !array_is_list($zones))
			return null;

		$layout = ['resolution' => '100x100', 'export_unit' => 'percent', 'zones' => []];
		foreach ($zones as $i => $zone)
		{
			if (!is_array($zone))
				return null;

			// the schema needs a name, the presets have none
			$layout['zones'][] = [
				'zone_name'    => 'Zone',
				'zone_left'    => $zone['left'] ?? null,
				'zone_top'     => $zone['top'] ?? null,
				'zone_width'   => $zone['width'] ?? null,
				'zone_height'  => $zone['height'] ?? null,
				'zone_z-index' => $i,
				'zone_bgcolor' => $zone['bgcolor'] ?? null
			];
		}

		$parsedLayout = $this->zonesSchema->parse($layout);
		if ($parsedLayout === null)
			return null;

		$parsedZones = [];
		foreach ($parsedLayout['zones'] as $zone)
		{
			if (!$this->insideOfScreen($zone))
				return null;

			$parsedZones[] = [
				'left'    => $zone['zone_left'],
				'top'     => $zone['zone_top'],
				'width'   => $zone['zone_width'],
				'height'  => $zone['zone_height'],
				'bgcolor' => $zone['zone_bgcolor']
			];
		}

		return ['id' => $id, 'zones' => $parsedZones];
	}

	/**
	 * @param array<string,int|float|string> $zone
	 */
	private function insideOfScreen(array $zone): bool
	{
		// @infection-ignore-all the schema gives numbers in percent, the casts are for the types only
		[$left, $top, $width, $height] = [(float) $zone['zone_left'], (float) $zone['zone_top'], (float) $zone['zone_width'], (float) $zone['zone_height']];

		return $left >= 0 && $top >= 0 && $width > 0 && $height > 0 && $left + $width <= 100 && $top + $height <= 100;
	}
}
