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

export class SnapService
{
	#fabricWrapper;
	#gridSize = 10;
	#isGridEnabled = false;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	get isGridEnabled()
	{
		return this.#isGridEnabled;
	}

	enable(gridSize = 10)
	{
		this.#gridSize = gridSize;
		this.#isGridEnabled = true;
	}

	disable()
	{
		this.#isGridEnabled = false;
	}

	getCanvasvaluesForGrid()
	{
		return {
			width: this.#fabricWrapper.getWidth(),
			height: this.#fabricWrapper.getHeight(),
			zoom: this.#fabricWrapper.getZoom()
		};
	}


	renderAll()
	{
		this.#fabricWrapper.renderAll();
	}

	snapToGrid(object)
	{
		if (!this.#isGridEnabled)
			return;

		object.set({
			left: Math.round(object.left / this.#gridSize) * this.#gridSize,
			top:  Math.round(object.top  / this.#gridSize) * this.#gridSize
		});
	}
}