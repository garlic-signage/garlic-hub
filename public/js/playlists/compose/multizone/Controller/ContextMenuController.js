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
 * The context menu of a zone: the right mouse button selects the zone and opens it, every click closes it.
 */
export class ContextMenuController
{
	#view;
	#zoneService;
	#fabricWrapper;

	constructor(view, zoneService, fabricWrapper)
	{
		this.#view          = view;
		this.#zoneService   = zoneService;
		this.#fabricWrapper = fabricWrapper;

		ComposerEventBus.addEventListener("mouseLeftUp", () => this.#view.remove());
		ComposerEventBus.addEventListener("mouseRightUp", (e) => this.#open(e.detail));
	}

	#open(options)
	{
		this.#view.remove();
		if (options.target == null)
			return;

		this.#fabricWrapper.setActiveObject(options.target);
		if (options.target.getType() !== "LabeledZone")
			return;

		this.#view.show(options.e.pageX, options.e.pageY);
		this.#onEntry("move_background", () => this.#zoneService.sendToBack());
		this.#onEntry("move_back", () => this.#zoneService.sendBackwards());
		this.#onEntry("move_front", () => this.#zoneService.bringForward());
		this.#onEntry("move_foreground", () => this.#zoneService.bringToFront());
		this.#onEntry("duplicate_zone", () => this.#zoneService.duplicateActive());
		this.#onEntry("delete_zone", () => this.#zoneService.removeActive());
	}

	#onEntry(entryId, action)
	{
		this.#view.onClick(entryId, () =>
		{
			action();
			this.#view.remove();
		});
	}
}
