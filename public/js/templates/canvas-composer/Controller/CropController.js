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

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";
import {CropService} from "../Services/CropService.js";

/**
 * Starts and ends the crop mode of an image:
 * - start: "crop image" in the context menu or a double click on the image,
 * - keep the cut: Enter, a double click or the selection of something else,
 * - cancel: Escape.
 * Other keys, undo and redo keep the cut first, then do what they do.
 */
export class CropController
{
	#cropService;
	#fabricWrapper;

	constructor(cropService, fabricWrapper)
	{
		this.#cropService   = cropService;
		this.#fabricWrapper = fabricWrapper;
	}

	init()
	{
		ComposerEventBus.addEventListener("cropImage", () => this.#cropService.enter(this.#fabricWrapper.getActiveObject()));
		ComposerEventBus.addEventListener("beforeHistory", () => this.#cropService.apply());

		this.#fabricWrapper.on("mouse:dblclick", (event) =>
		{
			if (this.#cropService.isCropping() && event.target === this.#cropService.image)
				this.#cropService.apply();
			else if (CropService.isCroppable(event.target))
				this.#cropService.enter(event.target);
		});
		this.#fabricWrapper.on("selection:cleared", () => this.#cropService.apply());
		this.#fabricWrapper.on("selection:updated", (event) => this.#keepWhenOtherSelected(event));
		this.#fabricWrapper.on("selection:created", (event) => this.#keepWhenOtherSelected(event));

		// before the keys of the composer, e.g. Delete for the selected object
		document.addEventListener("keydown", (event) => this.#onKey(event), true);
	}

	#keepWhenOtherSelected(event)
	{
		const selected = event.selected ?? [];
		if (this.#cropService.isCropping() && !(selected.length === 1 && selected[0] === this.#cropService.image))
			this.#cropService.apply();
	}

	#onKey(event)
	{
		if (!this.#cropService.isCropping())
			return;

		if (event.key === "Enter")
		{
			event.preventDefault();
			event.stopPropagation();
			this.#cropService.apply();
		}
		else if (event.key === "Escape")
		{
			event.preventDefault();
			event.stopPropagation();
			this.#cropService.cancel();
		}
		else if (!["Shift", "Control", "Alt", "Meta"].includes(event.key))
			this.#cropService.apply();
	}
}
