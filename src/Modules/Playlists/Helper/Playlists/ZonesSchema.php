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

namespace App\Modules\Playlists\Helper\Playlists;

/**
 * The layout of a multizone playlist as the zone editor sends it: the resolution, the unit of the zones and the zones.
 * The layout is read by the player index, the export and the editor, which expect all of these values,
 * so everything else than the known fields with valid values is rejected.
 */
class ZonesSchema
{
	private const int MAX_ZONES      = 100;
	private const int MAX_PIXEL      = 16384;
	private const int MAX_ZOOM       = 1000;
	private const int MAX_Z_INDEX    = 999;
	private const int MAX_NAME       = 100;
	private const string COLOR_HEX   = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/Di';
	private const string COLOR_RGB   = '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:[01]|0?\.\d+|1\.0+)\s*)?\)$/Di';

	/**
	 * @param array<mixed> $input the body of the request, other fields than the layout (csrf_token, playlist_id) are dropped
	 * @return array{resolution:string, zoom:int, export_unit:string, zones:list<array<string,int|float|string>>}|null null when a value is not valid
	 */
	public function parse(array $input): ?array
	{
		$resolution = $input['resolution'] ?? null;
		if (!is_string($resolution) || preg_match('/^(\d{1,5})x(\d{1,5})$/D', $resolution, $size) !== 1)
			return null;

		if (!$this->inRange((int) $size[1], 1, self::MAX_PIXEL) || !$this->inRange((int) $size[2], 1, self::MAX_PIXEL))
			return null;

		$zoom = $this->toInt($input['zoom'] ?? 70, 1, self::MAX_ZOOM);
		$unit = $input['export_unit'] ?? 'pixel';
		if ($zoom === null || !in_array($unit, ['pixel', 'percent'], true))
			return null;

		$zones = $input['zones'] ?? [];
		if (!is_array($zones) || !array_is_list($zones) || count($zones) > self::MAX_ZONES)
			return null;

		$parsed = [];
		foreach ($zones as $zone)
		{
			$parsedZone = is_array($zone) ? $this->parseZone($zone, $unit) : null;
			if ($parsedZone === null)
				return null;

			$parsed[] = $parsedZone;
		}

		return ['resolution' => $size[1] . 'x' . $size[2], 'zoom' => $zoom, 'export_unit' => $unit, 'zones' => $parsed];
	}

	/**
	 * @param array<mixed> $zone
	 * @return array<string,int|float|string>|null
	 */
	private function parseZone(array $zone, string $unit): ?array
	{
		$name = $zone['zone_name'] ?? null;
		$color = $zone['zone_bgcolor'] ?? null;
		if (!is_string($name) || mb_strlen($name) > self::MAX_NAME || !is_string($color) || !$this->isColor($color))
			return null;

		$parsed = ['zone_name' => $name];
		foreach (['zone_left', 'zone_top', 'zone_width', 'zone_height'] as $field)
		{
			// a zone can be moved partly out of the screen in the editor: left and top may be negative
			$outside = $field === 'zone_left' || $field === 'zone_top';
			$value   = $unit === 'percent'
				? $this->toPercent($zone[$field] ?? null, $outside)
				: $this->toInt($zone[$field] ?? null, $outside ? -self::MAX_PIXEL : 0, self::MAX_PIXEL);
			if ($value === null)
				return null;

			$parsed[$field] = $value;
		}

		$zIndex = $this->toInt($zone['zone_z-index'] ?? null, 0, self::MAX_Z_INDEX);
		// 0 is a zone without a playlist
		$playlistId = $this->toInt($zone['zone_playlist_id'] ?? 0, 0, PHP_INT_MAX);
		if ($zIndex === null || $playlistId === null)
			return null;

		return $parsed + ['zone_z-index' => $zIndex, 'zone_bgcolor' => $color, 'zone_playlist_id' => $playlistId];
	}

	private function isColor(string $color): bool
	{
		return $color === 'transparent' || preg_match(self::COLOR_HEX, $color) === 1 || preg_match(self::COLOR_RGB, $color) === 1;
	}

	private function inRange(int $value, int $min, int $max): bool
	{
		return $value >= $min && $value <= $max;
	}

	/**
	 * A whole number as number or as text.
	 */
	private function toInt(mixed $value, int $min, int $max): ?int
	{
		if (is_string($value) && preg_match('/^-?\d{1,18}$/D', $value) === 1)
			$value = (int) $value;

		return is_int($value) && $this->inRange($value, $min, $max) ? $value : null;
	}

	/**
	 * A number from 0 to 100 (-100 to 100 outside of the screen) as number or as text, the editor sends fractions for the percent.
	 */
	private function toPercent(mixed $value, bool $negativeAllowed): int|float|null
	{
		if (is_string($value) && preg_match('/^-?\d{1,3}(?:\.\d{1,12})?$/D', $value) === 1)
			$value = str_contains($value, '.') ? (float) $value : (int) $value;

		return (is_int($value) || is_float($value)) && $value >= ($negativeAllowed ? -100 : 0) && $value <= 100 ? $value : null;
	}
}
