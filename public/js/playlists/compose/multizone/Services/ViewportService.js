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

/**
 * The size of the screen of the playlist and the zoom of the canvas which shows it.
 */
export class ViewportService
{
	#fabricWrapper;
	#width  = 0;
	#height = 0;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	get width()
	{
		return this.#width;
	}

	get height()
	{
		return this.#height;
	}

	setDimensions(width, height)
	{
		this.#width  = width;
		this.#height = height;
		this.#fabricWrapper.setWidth(width);
		this.#fabricWrapper.setHeight(height);
		this.#fabricWrapper.renderAll();
	}

	scaleCanvas(zoom)
	{
		this.#fabricWrapper.setZoom(zoom / 100);
		this.#fabricWrapper.setWidth(Math.floor(this.#width / 100 * zoom));
		this.#fabricWrapper.setHeight(Math.floor(this.#height / 100 * zoom));
	}
}
