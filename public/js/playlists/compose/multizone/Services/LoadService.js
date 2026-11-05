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

import {LayoutSettings} from "../Utils/LayoutSettings.js";

/**
 * Loads the layout of a multizone playlist and puts its zones on the canvas. The editor shows the zones in pixel,
 * a layout in percent is converted. It reads the old format, too: only the zones, the screen comes from their borders.
 */
export class LoadService
{
	#settings;
	#viewportService;
	#zoneService;
	#playlistsService;
	#screenWidth  = 1920;
	#screenHeight = 1080;
	#maxX = 0;
	#maxY = 0;

	constructor(settings, viewportService, zoneService, playlistsService)
	{
		this.#settings         = settings;
		this.#viewportService  = viewportService;
		this.#zoneService      = zoneService;
		this.#playlistsService = playlistsService;
	}

	async load(playlistId)
	{
		const zones = await this.#loadFromDataBase(playlistId);

		this.#viewportService.setDimensions(this.#screenWidth, this.#screenHeight);
		this.#viewportService.scaleCanvas(this.#settings.zoom);

		for (let i = 0; i < zones.length; i++)
		{
			// convert zone_smil_playlist_id to zone playlist_id (SmilControl)
			if (zones[i]?.zone_smil_playlist_id != null)
			{
				zones[i].zone_playlist_id = zones[i].zone_smil_playlist_id;
				delete zones[i].zone_smil_playlist_id;
			}

			if (zones[i].zone_playlist_id > 0)
				zones[i].zone_playlist_name = await this.#playlistsService.findNameById(zones[i].zone_playlist_id)
			else
				zones[i].zone_playlist_name = "";

			this.#zoneService.create(zones[i]);
		}
	}

	#loadFromDataBase(playlistId)
	{
		return fetch("/async/playlists/multizone/" + playlistId, {
			method: 'GET',
			headers: {'Content-Type': 'application/json;charset=UTF-8'}
		}).
		then(async response => {
			// structured errors come with status 4xx and an error_message
			const jsonResponse = await response.json().catch(() => null);
			if (jsonResponse !== null && jsonResponse.success === false)
			{
				throw new Error(jsonResponse.error_message);
			}
			if (!response.ok || jsonResponse === null)
			{
				throw new Error(`HTTP-Fehler! Status: ${response.status}`);
			}
			return jsonResponse;
		})
		.then(jsonResponse => {
			if (jsonResponse.error !== undefined)
			{
				throw new Error(jsonResponse.error);
			}

			return this.#createZones(jsonResponse);
		})
		.catch(error => {
			alert(error.message);
			return [];
		});
	}

	#createZones(response)
	{
		if (response.zones === null || response.zones.length === 0) // new playlist
			return [];

		// we need to be read compatible to the old save format
		let zones = response.zones;

		if (zones.hasOwnProperty("resolution"))
		{
			// new playlists may only have a resolution
			this.#settings.exportUnit = zones.export_unit ?? this.#settings.exportUnit;
			this.#screenWidth         = zones.resolution.split("x")[0];
			this.#screenHeight        = zones.resolution.split("x")[1];
			const prepared            = this.#prepareZones(zones.zones ?? []);
			this.#settings.zoom       = zones.zoom ?? this.#settings.zoom;

			return prepared;
		}

		// the old format: sometimes old zones will be parsed to an object not an array
		if (!Array.isArray(zones))
			zones = Object.values(zones);

		this.#setExportUnitCompatible(response.export_unit);

		return this.#prepareZonesAndScreenForCompatible(zones);
	}

	#prepareZones(zones)
	{
		zones.sort((a, b) => a["zone_z-index"] - b["zone_z-index"]);

		if (this.#settings.exportUnit === LayoutSettings.UNIT_PIXEL)
			return zones;

		let converted_zones = [];
		if (this.#settings.exportUnit === LayoutSettings.UNIT_PERCENT)
		{
			for (let i = 0; i < zones.length; i++)
			{
				converted_zones.push(this.#convertForView(zones[i]));
			}
		}
		return converted_zones;
	}

	#prepareZonesAndScreenForCompatible(zones)
	{
		zones.sort((a, b) => a["zone_z-index"] - b["zone_z-index"]);
		let converted_zones = [];
		if (this.#settings.exportUnit === LayoutSettings.UNIT_PERCENT)
		{
			this.#calculateZoomLevel(this.#screenWidth, this.#screenHeight);
			for (let i = 0; i < zones.length; i++)
			{
				converted_zones.push(this.#convertForView(zones[i]));
			}
		}
		else
		{
			for (let i = 0; i < zones.length; i++)
			{
				this.#determineMaximumBorders(zones[i])
			}
			converted_zones = zones;
			this.#screenWidth  = this.#maxX;
			this.#screenHeight = this.#maxY;
			this.#calculateZoomLevel(this.#maxX, this.#maxY);
		}

		return converted_zones;
	}

	#calculateZoomLevel(width, height)
	{
		const zoom_width  = (1280 / width) * 100;
		const zoom_height = (720 / height) * 100;
		const zoom_level  = Math.min(zoom_width, zoom_height);

		this.#settings.zoom = Math.round(zoom_level / 5) * 5;
	}

	#determineMaximumBorders(zone)
	{
		const y = Number(zone.zone_height) + Number(zone.zone_top);
		const x = Number(zone.zone_width) + Number(zone.zone_left);

		if (y > this.#maxY)
			this.#maxY = y;

		if (x > this.#maxX)
			this.#maxX = x;
	}

	#convertForView(zone)
	{
		let w = parseFloat(this.#screenWidth) / 100;
		let h = parseFloat(this.#screenHeight) / 100;

		zone.zone_left   = w * zone.zone_left;
		zone.zone_top    = h * zone.zone_top;
		zone.zone_width  = w * zone.zone_width;
		zone.zone_height = h * zone.zone_height;

		return zone;
	}

	#setExportUnitCompatible(units)
	{
		if (units === "1")
			this.#settings.exportUnit = LayoutSettings.UNIT_PERCENT
		else
			this.#settings.exportUnit = LayoutSettings.UNIT_PIXEL;
	}
}
