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

export class SnapController
{
	#snapView;
	#snapService;


	constructor(snapView, snapService)
	{
		this.#snapView = snapView;
		this.#snapService = snapService;
		ComposerEventBus.addEventListener("snapToGrid", (e) =>
		{
			if (!this.#snapService.isGridEnabled)
				return;

			this.#snapService.snapToGrid(e.detail.target);
		});
		ComposerEventBus.addEventListener("drawGrid", (e) =>
		{
			if (!this.#snapService.isGridEnabled)
				return;

			this.#snapView.drawGrid(
				this.#snapService.getCanvasvaluesForGrid()
			);
		});
		this.#snapView.snapToGrid.addEventListener("change", () =>
		{
			const value = this.#snapView.getSnapToGridValue();

			if (value > 0)
				this.#snapService.enable(value);
			else
				this.#snapService.disable();
		});

	}
}
