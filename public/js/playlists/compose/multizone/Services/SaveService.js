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

import {WaitOverlay} from "../../../../core/WaitOverlay.js";
import {Utils} from "../../../../core/Utils.js";
import {LayoutSettings} from "../Utils/LayoutSettings.js";

/**
 * Saves the layout: the resolution, the zoom, the unit and the zones in the order of their layers.
 * In percent the zones are converted by the resolution.
 */
export class SaveService
{
	#settings;
	#viewportService;
	#zoneService;
	#playlistId;

	constructor(settings, viewportService, zoneService, playlistId)
	{
		this.#settings        = settings;
		this.#viewportService = viewportService;
		this.#zoneService     = zoneService;
		this.#playlistId      = playlistId;
	}

	/**
	 * @return {Promise<{success: boolean, error_message?: string}>}
	 */
	async save()
	{
		const progress = new WaitOverlay();
		progress.start();

		try
		{
			const properties = this.#createJsonForSave();
			properties.csrf_token = Utils.csrfToken();

			const response = await fetch("/async/playlists/multizone/" + this.#playlistId, {
				method: 'POST',
				headers: {'Content-Type': 'application/json;charset=UTF-8'},
				body: JSON.stringify(properties)
			});
			const result = await response.json().catch(() => null);
			if (result !== null && typeof result === "object" && "success" in result)
				return result;

			return response.ok ? {success: true} : {success: false, error_message: "HTTP-Error: " + response.status};
		}
		catch (error)
		{
			return {success: false, error_message: error.message};
		}
		finally
		{
			progress.stop();
		}
	}

	#createJsonForSave()
	{
		return {
			"resolution"  : this.#viewportService.width + "x" + this.#viewportService.height,
			"zoom"        : this.#settings.zoom,
			"export_unit" : this.#settings.exportUnit,
			"zones"       : this.#zoneService.zones().map(zone => this.#convertForSave(zone.getPropertiesForExport())),
		}
	}

	#convertForSave(zone)
	{
		if (this.#settings.exportUnit === LayoutSettings.UNIT_PIXEL)
			return zone;

		let w = 100 / parseFloat(this.#viewportService.width);
		let h = 100 / parseFloat(this.#viewportService.height);

		zone.zone_left   = w * zone.zone_left;
		zone.zone_top    = h * zone.zone_top;
		zone.zone_width  = w * zone.zone_width;
		zone.zone_height = h * zone.zone_height;

		return zone;
	}
}
