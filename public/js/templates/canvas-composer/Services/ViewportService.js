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

export class ViewportService
{
	#fabricWrapper;
	#width;
	#height;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	initializeFromCanvas()
	{
		this.#width = this.#fabricWrapper.getWidth();
		this.#height = this.#fabricWrapper.getHeight();
	}

	setCanvasDimensions(width, height)
	{
		this.#width = width;
		this.#height = height;

		this.setWidth(width);
		this.setHeight(height);
	}

	scaleCanvas(zoom)
	{
		this.setZoom(zoom / 100);
		this.setWidth(Math.floor(this.#width / 100 * zoom))
		this.setHeight(Math.floor(this.#height / 100 * zoom));
	}


	calculateZoomByBrowserViewPort(browserWidth, browserHeight)
	{
		let w = document.documentElement.clientWidth - 180;
		let h = document.documentElement.clientHeight - 180;
		let p1 = Math.floor((100/this.#width)  * w);
		let p2 = Math.floor((100/this.#height) * h);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}

	calculateImageScaleByCanvasInPerCent(w, h)
	{
		let p1 = Math.floor((100/w)  * this.#width);
		let p2 = Math.floor((100/h) * this.#height);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}


	setWidth(width)
	{
		this.#fabricWrapper.setWidth(width);
	}

	setHeight(height)
	{
		this.#fabricWrapper.setHeight(height);
	}

	setZoom(zoom)
	{
		this.#fabricWrapper.setZoom(zoom);
	}


}
