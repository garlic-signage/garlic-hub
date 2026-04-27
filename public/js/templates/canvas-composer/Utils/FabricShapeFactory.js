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

export class FabricShapeFactory
{
	static #DEFAULT_LEFT = 50;
	static #DEFAULT_TOP = 50;
	static #DEFAULT_FILL = "#000000";


	createImage(mediaId, url, count)
	{
		return new Promise((resolve) =>
		{
			fabric.Image.fromURL(url.replace("thumbs", "originals"), (img) =>
			{
				img.set({
					mediaId: mediaId,
					fileName: url.split('/').pop(),
					left: count * FabricShapeFactory.#DEFAULT_LEFT,
					top: count * FabricShapeFactory.#DEFAULT_TOP
				});
				img.bringToFront();
				resolve(img);
			}, { crossOrigin: 'anonymous' });
		});
	}

	createText()
	{
		const text = "Lorem ipsum";
		return  new fabric.Textbox(text, { ...this.#defaultProperties(), lockUniScaling: true, width: 400, fontFamily: 'Arial', fontSize: 64});
	}

	createCircle()
	{
		return new fabric.Circle({ ...this.#defaultProperties(), radius: 200 });
	}

	createTriangle()
	{
		return new fabric.Triangle({...this.#defaultProperties(), width: 400, height: 400});
	}

	createRectangle()
	{
		return new fabric.Rect({...this.#defaultProperties(), width: 400, height: 300, });
	}

	createPolygon(points)
	{
		return new fabric.Polygon(points, { ...this.#defaultProperties() });
	}

	#defaultProperties()
	{
		return {
			left: FabricShapeFactory.#DEFAULT_LEFT,
			top: FabricShapeFactory.#DEFAULT_TOP,
			fill: FabricShapeFactory.#DEFAULT_FILL
		};
	}
}
