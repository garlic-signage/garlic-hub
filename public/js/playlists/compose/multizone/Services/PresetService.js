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
'use strict';

/**
 * Replaces the zones of the canvas by the zones of a preset. The preset is in percent, the editor shows pixel:
 * the borders are rounded, so neighbouring zones keep touching without a gap. The playlists of the old zones are not taken over.
 */
export class PresetService
{
	#viewportService;
	#zoneService;

	constructor(viewportService, zoneService)
	{
		this.#viewportService = viewportService;
		this.#zoneService     = zoneService;
	}

	hasZones()
	{
		return this.#zoneService.zones().length > 0;
	}

	apply(preset)
	{
		const width  = Number(this.#viewportService.width);
		const height = Number(this.#viewportService.height);

		this.#zoneService.removeAll();
		preset.zones.forEach((zone, i) =>
		{
			const left = Math.round(width * zone.left / 100);
			const top  = Math.round(height * zone.top / 100);
			this.#zoneService.create({
				"zone_name"   : "Zone " + (i + 1),
				"zone_left"   : left,
				"zone_top"    : top,
				"zone_width"  : Math.round(width * (zone.left + zone.width) / 100) - left,
				"zone_height" : Math.round(height * (zone.top + zone.height) / 100) - top,
				"zone_bgcolor": zone.bgcolor
			});
		});
	}
}
