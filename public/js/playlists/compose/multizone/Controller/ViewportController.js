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
 * The resolution of the screen, the unit of the zones and the zoom. A moved zone snaps to a grid of 10 pixel.
 */
export class ViewportController
{
	static GRID = 10;

	#view;
	#viewportService;
	#settings;

	constructor(view, viewportService, settings, fabricWrapper)
	{
		this.#view            = view;
		this.#viewportService = viewportService;
		this.#settings        = settings;

		this.#view.screenWidth.addEventListener("blur", () => this.#resize());
		this.#view.screenHeight.addEventListener("blur", () => this.#resize());
		this.#view.exportUnit.addEventListener("change", () => this.#settings.exportUnit = this.#view.exportUnit.value);
		this.#view.slider.addEventListener("input", () =>
		{
			this.#view.showZoomPercent();
			this.#settings.zoom = this.#view.slider.value;
			this.#viewportService.scaleCanvas(this.#view.slider.value);
		});
		ComposerEventBus.addEventListener("snapToGrid", (e) =>
		{
			const zone = e.detail.target;
			zone.set({
				left: Math.round(zone.left / ViewportController.GRID) * ViewportController.GRID,
				top: Math.round(zone.top / ViewportController.GRID) * ViewportController.GRID
			});
			fabricWrapper.renderAll();
		});
	}

	/**
	 * After the load: the values of the layout.
	 */
	show()
	{
		this.#view.show(this.#viewportService.width, this.#viewportService.height, this.#settings.exportUnit, this.#settings.zoom);
	}

	#resize()
	{
		this.#viewportService.setDimensions(this.#view.screenWidth.value, this.#view.screenHeight.value);
		this.#viewportService.scaleCanvas(this.#view.slider.value);
	}
}
