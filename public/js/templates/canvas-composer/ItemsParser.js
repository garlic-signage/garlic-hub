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

export class ItemsParser
{
	#items = {};
	width     = 0;
	height    = 0;
	MyCanvasView  = {};

	constructor(MyCanvasView)
	{
		this.MyCanvasView = MyCanvasView;
	}

	outputJsonTemplate(width, height)
	{
		this.width  = width;
		this.height = height;
		this.MyCanvasView.setCanvasDimensions(width, height);
		this.zoomToViewPort();
	}

	zoomToViewPort()
	{
		this.MyCanvasView.slider.value = this.calculateZoomByBrowserViewPort();
		this.MyCanvasView.scalePercent();
		this.MyCanvasView.scaleCanvas()
	}

	calculateZoomByBrowserViewPort()
	{
		let w = document.documentElement.clientWidth - 180;
		let h = document.documentElement.clientHeight - 180;
		let p1 = Math.floor((100/this.width)  * w);
		let p2 = Math.floor((100/this.height) * h);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}

	calculateImageScaleByCanvasInPerCent(w, h)
	{
		let p1 = Math.floor((100/w)  * this.width);
		let p2 = Math.floor((100/h) * this.height);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}

	createItem(item, object)
	{
		this.#items[object.id] = {"item": item, "object": object};
	}

}

