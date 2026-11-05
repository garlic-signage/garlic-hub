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

/**
 * The properties of the selected zone: a click on a zone shows them, a change of a field changes the zone.
 */
export class ZonePropertiesController
{
	#view;
	#service;
	#autocompletePlaylist;
	#playlistId;
	#playlistName;

	constructor(view, service, autocompletePlaylist, fabricWrapper)
	{
		this.#view                 = view;
		this.#service              = service;
		this.#autocompletePlaylist = autocompletePlaylist;
		this.#playlistId           = autocompletePlaylist.getHiddenIdElement();
		this.#playlistName         = autocompletePlaylist.getEditFieldElement();

		ComposerEventBus.addEventListener("mouseLeftUp", (e) => this.activate(e.detail.target));
		fabricWrapper.on("object:modified", (event) =>
		{
			// the mouse changes only the scale, the zone needs its real width and height
			if (event.target.scaleX !== 1 || event.target.scaleY !== 1)
				event.target.onScaling();

			this.activate(event.target);
		});

		this.#initFields();
	}

	activate(zone)
	{
		if (zone == null)
		{
			this.deactivate();
			return;
		}
		this.#service.select(zone);

		this.#view.show(zone.label.text, Math.round(zone.left), Math.round(zone.top), Math.round(zone.width), Math.round(zone.height));

		zone.rect.fill = this.#service.fixRGBA(zone.rect.fill);
		if (this.#service.isTransparent(zone.rect.fill))
			this.#view.showTransparent();
		else
			this.#view.showColor(zone.rect.fill);

		this.#showPlaylist();
		this.#view.enable();
	}

	deactivate()
	{
		this.#view.clear();
		this.#view.disable();
		this.#autocompletePlaylist.clearAll();
		this.#service.clear();
	}

	async #showPlaylist()
	{
		const playlist = await this.#service.findPlaylist();
		if (playlist.id === 0)
			this.#autocompletePlaylist.setInputFields(0, "");
		else if (playlist.name !== "")
			this.#autocompletePlaylist.setInputFields(playlist.id, playlist.name);
	}

	#initFields()
	{
		this.#view.name.addEventListener("input", () => this.#service.rename(this.#view.name.value));
		this.#view.left.addEventListener("blur", () => this.#service.setLeft(this.#view.left.value));
		this.#view.top.addEventListener("blur", () => this.#service.setTop(this.#view.top.value));
		this.#view.width.addEventListener("blur", () => this.#service.setWidth(this.#view.width.value));
		this.#view.height.addEventListener("blur", () => this.#service.setHeight(this.#view.height.value));
		this.#view.bgcolor.addEventListener("input", () => this.#service.setColor(this.#view.bgcolor.value));
		this.#view.transparent.addEventListener("input", () =>
		{
			if (this.#service.zone === null)
				return;

			const isTransparent = this.#view.transparent.checked;
			this.#view.enableColor(!isTransparent);
			this.#service.setTransparent(isTransparent);
		});
		this.#playlistId.addEventListener('selectedFromDataList', () =>
			this.#service.setPlaylist(this.#playlistId.value, this.#playlistName.value)
		);
		this.#playlistName.addEventListener('blur', () =>
		{
			if (this.#playlistName.value === "")
				this.#service.removePlaylist();
		});
	}
}
