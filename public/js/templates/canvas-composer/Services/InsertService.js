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

export class InsertService
{
	#fabricWrapper;
	#fabricShapeFactory;
	#viewportService;

	constructor(fabricWrapper, fabricShapeFactory, viewportService)
	{
		this.#fabricWrapper      = fabricWrapper;
		this.#fabricShapeFactory = fabricShapeFactory;
		this.#viewportService    = viewportService;
	}

	async insertImage(mediaId, url, count)
	{
		const img = await this.#fabricShapeFactory.createImage(mediaId, url, count);

		let scale = this.#viewportService.calculateImageScaleByCanvasInPerCent(img.width, img.height);
		img.scale(scale/150);

		this.#addObject(img);
	}

	async replaceImage(mediaId, url)
	{
		return new Promise((resolve, reject) =>
		{
			const object = this.#fabricWrapper.getActiveObject();
			const w = object.width  * object.scaleX;
			const h = object.height * object.scaleY;
			url = url.replace("thumbs", "originals");

			this.#fabricWrapper.historySaveAction();
			this.#fabricWrapper.fireObjectModified(object);
			this.#fabricWrapper.renderAll();
			object.setSrc(url, () =>
				{
					object.scaleX = 1;
					object.scaleY = 1;
					object.mediaId = mediaId;
					object.fileName = url.split('/').pop();
					object.scaleToWidth(w, true);
					object.scaleToHeight(h, true);
					this.#fabricWrapper.renderAll();
					resolve();
				},
				(err) => reject(err),
				{ crossOrigin: 'anonymous' });
		});
	}

	insertText()
	{
		const text = this.#fabricShapeFactory.createText();
		this.#addObject(text);
	}

	insertCircle()
	{
		const circle = this.#fabricShapeFactory.createCircle();
		this.#addObject(circle);
	}

	insertTriangle()
	{
		const triangle = this.#fabricShapeFactory.createTriangle();
		this.#addObject(triangle);
	}

	insertRectangle()
	{
		const rect = this.#fabricShapeFactory.createRectangle();
		this.#addObject(rect);
	}

	insertRegularPolygon(sides)
	{
		const points = this.#calculatePolygonPoints(sides);
		const polygon = this.#fabricShapeFactory.createPolygon(points);
		this.#addObject(polygon);
	}

	#calculatePolygonPoints(sides, radius = 200, cx = 200, cy = 200)
	{
		const sweep = Math.PI * 2 / sides;
		const points = [];
		for (let i = 0; i < sides; i++)
		{
			points.push({
				x: cx + radius * Math.cos(i * sweep),
				y: cy + radius * Math.sin(i * sweep)
			});
		}
		return points;
	}

	#addObject(object)
	{
		this.#fabricWrapper.add(object);
		this.#fabricWrapper.historySaveAction();
		this.#fabricWrapper.renderAll();
	}
}
