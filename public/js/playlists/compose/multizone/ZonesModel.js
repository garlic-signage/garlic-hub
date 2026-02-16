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
import './CanvasView.js';
import { WaitOverlay } from '../../../core/WaitOverlay.js';

export class ZonesModel
{
	static UNIT_PIXEL   = "pixel";
	static UNIT_PERCENT = "percent";

	MyCanvasView = {};
	#Zones = {};
	#playlist_id;
	#export_unit = ZonesModel.UNIT_PIXEL;
	#zoom = 70
	#screen_width  = 1920;
	#screen_height  = 1080;
	#max_x = 0;
	#max_y = 0;

	constructor(MyCanvasView)
	{
		this.MyCanvasView   = MyCanvasView;
	}

	getExportUnit()
	{
		return this.#export_unit;
	}

	setExportUnit(export_unit)
	{
		this.#export_unit = export_unit;
	}

	getZones()
	{
		return this.#Zones;
	}

	getScreenWidth()
	{
		return this.#screen_width;
	}

	getScreenHeight()
	{
		return this.#screen_height;
	}

	getZoom()
	{
		return this.#zoom;
	}

	setZoom(zoom)
	{
		this.#zoom = zoom;
	}

	loadFromDataBase(playlist_id)
	{
		this.#playlist_id = playlist_id;

		let url = "/async/playlists/multizone/"+playlist_id;

		return fetch(url, {
			method: 'GET',
			headers: {'Content-Type': 'application/json;charset=UTF-8'}
		}).
		then(response => {
			if (!response.ok)
			{
				throw new Error(`HTTP-Fehler! Status: ${response.status}`);
			}
			return response.json(); // JSON-Antwort parsen
		})
		.then(jsonResponse => {
			if (jsonResponse.error !== undefined)
			{
				throw new Error(jsonResponse.error);
			}

			this.#createZonesObject(jsonResponse);

		})
		.catch(error => {

			alert(error.message);
	//		jThymian.printError(error.message);
	//		ThymianLog.log(error.message, 0, window.location.pathname);
		});
	}

	async determinNameById(playlist_id)
	{
		try
		{
			const url      = "/async/playlists/find/" + playlist_id;
			const response = await fetch(url);
			const playlist = await response.json();

			if (playlist.lenght === 0)
				return '';

			return playlist.name;

		}
		catch (error)
		{
			console.error('Error fetching suggestions:', error);
		}
	}


	#createZonesObject(response)
	{
		if (response.zones === null || response.zones.length === 0) // new playlist
			return;

		// we need to be read compatible to the old save format
		let zones = response.zones;

		if (zones.hasOwnProperty("resolution"))
		{
			this.#export_unit = zones.export_unit;
			this.#screen_width = zones.resolution.split("x")[0];
			this.#screen_height = zones.resolution.split("x")[1];
			this.#Zones       = this.#prepareZones(zones.zones)
			this.#zoom        = zones.zoom;
		}
		else // the old format
		{
			// sometimes old zones will be parsed to an object not an array
			if (!Array.isArray(zones))
				zones = Object.values(zones);

			this.#setExportUnitCompatible(response.export_unit);
			this.#Zones = this.#prepareZonesAndScreenForCompatible(zones)
		}
	}

	#prepareZones(zones)
	{
		zones.sort((a, b) => a["zone_z-index"] - b["zone_z-index"]);

		if (this.#export_unit === ZonesModel.UNIT_PIXEL)
			return zones;

		let converted_zones = [];
		if (this.#export_unit === ZonesModel.UNIT_PERCENT)
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
		if (this.#export_unit === ZonesModel.UNIT_PERCENT)
		{
			this.#calculateZoomLevel(this.#screen_width, this.#screen_height);
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
			this.#screen_width = this.#max_x;
			this.#screen_height = this.#max_y;
			this.#calculateZoomLevel(this.#max_x, this.#max_y);
		}

		return converted_zones;
	}

	saveToDataBase()
	{
		let MyProgress = new WaitOverlay();
		MyProgress.start();

		try
		{
			const Properties = this.#createJsonForSave();
			Properties.csrf_token = this.#detectCsrfTokenInMetaTag();

			let url = "/async/playlists/multizone/"+ this.#playlist_id;
			fetch(url, {
				method: 'POST',
				headers: {'Content-Type': 'application/json;charset=UTF-8'},
				body: JSON.stringify(Properties)
			}).then(response => {
				MyProgress.stop();

				if (!response.ok)
				{
					throw new Error(`HTTP-Fehler: ${response.statusText}`);
				}

				return response.json();
			})
				.then(jsonResponse => {
					if (jsonResponse.success === false)
					{
				//		jThymian.printError(jsonResponse.message);
					}
				})
				.catch(error => {
					MyProgress.stop();
				//	jThymian.printError(error.message);  // Fehler ausgeben
				//	ThymianLog.log(error.message, 0, window.location.pathname);
				});

		}
		catch (err) {
			MyProgress.stop();
	//		ThymianLog.logException(err);
	//		jThymian.printError(err);
		}
	}

	#createJsonForSave()
	{
		return {
			"resolution"  : this.MyCanvasView.getWidth() + "x" + this.MyCanvasView.getHeight(),
			"zoom"        : this.#zoom,
			"export_unit" : this.#export_unit,
			"zones"       : this.#collectZones(),
		}
	}

	#collectZones()
	{
		const Zones = [];
		const obj = this.MyCanvasView.getCanvas().getObjects();

		obj.forEach((MyZone) => {
			if (MyZone.getType() !== "LabeledZone")
				return;

			const zone_data = this.#convertForSave(MyZone.getPropertiesForExport());
			Zones.push(zone_data);
		});

		return Zones;
	}

	#calculateZoomLevel(width, height)
	{
		const zoom_width  = (1280 / width) * 100;
		const zoom_height = (720 / height) * 100;
		const zoom_level  = Math.min(zoom_width, zoom_height);

		this.#zoom        = Math.round(zoom_level / 5) * 5;
	}

	#determineMaximumBorders(zone)
	{
		const y = Number(zone.zone_height) + Number(zone.zone_top);
		const x = Number(zone.zone_width) + Number(zone.zone_left);

		if (y > this.#max_y)
			this.#max_y = y;

		if (x > this.#max_x)
			this.#max_x = x;

	}

	#convertForSave(zone)
	{
		if (this.#export_unit === ZonesModel.UNIT_PIXEL)
			return zone;

		let w = 100 / parseFloat(this.MyCanvasView.getWidth());
		let h = 100 / parseFloat(this.MyCanvasView.getHeight());

		return this.#convertZoneDimensionsByFactor(w, h, zone);
	}

	#convertForView(zone)
	{
		if (this.#export_unit === ZonesModel.UNIT_PIXEL)
			return zone;

		let w = parseFloat(this.#screen_width) / 100;
		let h = parseFloat(this.#screen_height) / 100;

		return this.#convertZoneDimensionsByFactor(w, h, zone);
	}

	#convertZoneDimensionsByFactor(w, h, zone)
	{
		zone.zone_left   = Math.round(w * zone.zone_left);
		zone.zone_top    = Math.round(h * zone.zone_top);
		zone.zone_width  = Math.round(w * zone.zone_width);
		zone.zone_height = Math.round(h * zone.zone_height);

		return zone;
	}

	#setExportUnitCompatible(units)
	{
		if (units === "1")
			this.#export_unit = ZonesModel.UNIT_PERCENT
		else
			this.#export_unit = ZonesModel.UNIT_PIXEL;
	}

	#detectCsrfTokenInMetaTag()
	{
		const metaTag = document.querySelector('meta[name="csrf-token"]');
		if (metaTag && metaTag.hasAttribute('content'))
			return metaTag.getAttribute('content');

		throw new Error("No CSRF token found in meta tag");
	}
}
