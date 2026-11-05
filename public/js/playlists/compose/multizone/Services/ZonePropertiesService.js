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

import {ComposerEventBus} from "../../../../core/canvas/ComposerEventBus.js";
import {LabeledZone} from "../Utils/LabeledZone.js";

/**
 * Changes the zone whose properties are shown. A new name is told by the event zoneRenamed.
 */
export class ZonePropertiesService
{
	#fabricWrapper;
	#playlistsService;
	#zone = null;

	constructor(fabricWrapper, playlistsService)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#playlistsService = playlistsService;
	}

	get zone()
	{
		return this.#zone;
	}

	select(zone)
	{
		this.#zone = zone;
	}

	clear()
	{
		this.#zone = null;
	}

	rename(name)
	{
		if (this.#zone === null || this.#zone.item(1).text === name)
			return;

		this.#fabricWrapper.changed();
		this.#zone.item(1).text = name;
		this.#zone.dirty        = true;

		ComposerEventBus.dispatchEvent(new CustomEvent("zoneRenamed", {detail: {id: this.#fabricWrapper.getActiveObject().getId(), name: name}}));
		this.#fabricWrapper.renderAll();
	}

	setLeft(value)
	{
		if (this.#zone === null || this.#zone.left === Number(value))
			return;

		this.#fabricWrapper.changed();
		this.#zone.left = Number(value);
		this.#fabricWrapper.renderAll();
	}

	setTop(value)
	{
		if (this.#zone === null || this.#zone.top === Number(value))
			return;

		this.#fabricWrapper.changed();
		this.#zone.top = Number(value);
		this.#fabricWrapper.renderAll();
	}

	setWidth(value)
	{
		if (this.#zone === null)
			return;

		this.#zone.changeWidth(Number(value));
		this.#fabricWrapper.changed();
		this.#fabricWrapper.renderAll();
	}

	setHeight(value)
	{
		if (this.#zone === null)
			return;

		this.#zone.changeHeight(Number(value));
		this.#fabricWrapper.changed();
		this.#fabricWrapper.renderAll();
	}

	setColor(color)
	{
		if (this.#zone === null)
			return;

		this.#zone.changeBgColor(color);
		this.#fabricWrapper.changed();
		this.#fabricWrapper.renderAll();
	}

	setTransparent(isTransparent)
	{
		if (this.#zone === null)
			return;

		this.#zone.changeBgColor(isTransparent ? LabeledZone._transparent : "#000000");
		this.#fabricWrapper.changed();
		this.#fabricWrapper.renderAll();
	}

	setPlaylist(playlistId, playlistName)
	{
		if (this.#zone === null || this.#zone.zone_playlist_id === playlistId)
			return;

		this.#fabricWrapper.changed();
		this.#zone.item(2).text        = playlistName;
		this.#zone.dirty               = true;
		this.#zone.zone_playlist_id    = playlistId;
		this.#fabricWrapper.renderAll();
	}

	/**
	 * An empty name of the playlist: the zone has no playlist.
	 */
	removePlaylist()
	{
		if (this.#zone === null)
			return;

		this.#fabricWrapper.changed();
		this.#zone.zone_playlist_id = 0;
	}

	/**
	 * @return {Promise<{id: number|string, name: string}>}
	 */
	async findPlaylist()
	{
		const playlistId = this.#zone.zone_playlist_id;
		if (playlistId === 0)
			return {id: 0, name: ""};

		return {id: playlistId, name: await this.#playlistsService.findNameById(playlistId)};
	}

	isTransparent(fill)
	{
		return fill === LabeledZone._transparent;
	}

	fixRGBA(color)
	{
		if (color.startsWith("rgba"))
		{
			if (!color.endsWith(")"))
				return color + ")";
		}

		return color;
	}
}
