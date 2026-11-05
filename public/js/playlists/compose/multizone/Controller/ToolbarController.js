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

import {FlashMessageHandler} from "../../../../core/FlashMessageHandler.js";

/**
 * Add a zone, the keys on the canvas, save and close.
 */
export class ToolbarController
{
	#view;
	#zoneService;
	#saveService;
	#propertiesController;
	#fabricWrapper;

	constructor(view, zoneService, saveService, propertiesController, fabricWrapper)
	{
		this.#view                 = view;
		this.#zoneService          = zoneService;
		this.#saveService          = saveService;
		this.#propertiesController = propertiesController;
		this.#fabricWrapper        = fabricWrapper;

		this.#view.addZone.addEventListener("click", () =>
			this.#zoneService.insert({"zone_name": "Zone " + (this.#zoneService.countObjects() + 1)})
		);
		this.#view.canvasWrap.addEventListener("keydown", (event) => this.#onKey(event), false);
		this.#view.save.addEventListener("click", () => this.#save());
		this.#view.close.addEventListener("click", () =>
		{
			if (!this.#fabricWrapper.hasChanged || confirm(lang["confirm_close"]))
				window.location.href = "/playlists";
		});
	}

	#onKey(event)
	{
		const isArrow = ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"].includes(event.key);
		if (event.shiftKey && isArrow)
			this.#zoneService.moveActive(event.key, 50);
		else if (event.ctrlKey && event.key.toUpperCase() === "D")
			this.#zoneService.duplicateActive();
		else if (event.key === "Delete")
			this.#zoneService.removeActive();
		else if (isArrow)
			this.#zoneService.moveActive(event.key, 1);

		this.#fabricWrapper.renderAll();
		this.#propertiesController.activate(this.#fabricWrapper.getActiveObject());
		this.#fabricWrapper.changed();
	}

	async #save()
	{
		const result = await this.#saveService.save();
		// unsaved changes keep the question when the editor closes
		if (result.success === false)
		{
			new FlashMessageHandler().showError(result.error_message);
			return;
		}
		this.#fabricWrapper.resetChange();
	}
}
