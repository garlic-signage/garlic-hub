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
 * The list of the zones follows the canvas, a click on an entry selects its zone.
 */
export class ZoneListController
{
	#view;
	#zoneService;
	#propertiesController;
	#fabricWrapper;

	constructor(view, zoneService, propertiesController, fabricWrapper)
	{
		this.#view                 = view;
		this.#zoneService          = zoneService;
		this.#propertiesController = propertiesController;
		this.#fabricWrapper        = fabricWrapper;

		ComposerEventBus.addEventListener("zoneAdded", (e) =>
			this.#view.add(e.detail.zone.id, e.detail.zone.label.text, (id) => this.#select(id))
		);
		ComposerEventBus.addEventListener("zoneRemoved", (e) => this.#view.remove(e.detail.id));
		ComposerEventBus.addEventListener("zoneRenamed", (e) => this.#view.rename(e.detail.id, e.detail.name));
		fabricWrapper.on("selection:created", (event) => this.#view.highlight(event.selected[0].id));
		fabricWrapper.on("selection:updated", (event) => this.#view.highlight(event.selected[0].id));
	}

	#select(id)
	{
		this.#view.highlight(id);

		const zone = this.#zoneService.selectById(id);
		if (zone === null)
			return;

		this.#propertiesController.activate(zone);
		this.#fabricWrapper.renderAll();
	}
}
