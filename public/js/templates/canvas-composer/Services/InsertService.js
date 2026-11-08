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

	async insertImage(mediaId, url, fileName, count)
	{
		const img = await this.#fabricShapeFactory.createImage(mediaId, url, fileName, count);

		let scale = this.#viewportService.calculateImageScaleByCanvasInPerCent(img.width, img.height);
		img.scale(scale/150);

		this.#addObject(img);
	}

	/**
	 * The new picture fills the area of the old one: scaled until it covers it and cut centered (cropX/cropY), so
	 * the layout of the template stays. A round clip path keeps its size. A placeholder is no longer one.
	 */
	async replaceImage(mediaId, url, fileName)
	{
		const object = this.#fabricWrapper.getActiveObject();
		const w = object.width  * object.scaleX;
		const h = object.height * object.scaleY;
		const oldScale = object.scaleX;

		// loaded first: a picture which can not be loaded leaves the image as it is
		const element = await new Promise((resolve, reject) =>
		{
			fabric.util.loadImage(url, (img, isError) =>
			{
				if (isError || !img || !(img.naturalWidth || img.width))
					reject(new Error("The image could not be loaded: " + fileName));
				else
					resolve(img);
			}, null, "anonymous");
		});

		object.setElement(element);
		const crop = InsertService.coverCrop(element, w, h);
		object.set(crop);
		if (object.clipPath)
			object.clipPath.set({
				scaleX: object.clipPath.scaleX * oldScale / crop.scaleX,
				scaleY: object.clipPath.scaleY * oldScale / crop.scaleY
			});
		object.mediaId = mediaId;
		// only the plain file name is stored, the signed url is rebuilt on every load
		object.fileName = fileName;
		object.placeholder = false;
		object.setCoords();
		// object:modified after the change saves it as one step of the history
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}

	/**
	 * The part of a picture which covers an area of the width and height, cut centered.
	 *
	 * @return {{width: number, height: number, cropX: number, cropY: number, scaleX: number, scaleY: number}}
	 */
	static coverCrop(element, width, height)
	{
		const naturalWidth  = element.naturalWidth  || element.width;
		const naturalHeight = element.naturalHeight || element.height;
		const scale         = Math.max(width / naturalWidth, height / naturalHeight);
		const cropWidth     = Math.min(naturalWidth,  width / scale);
		const cropHeight    = Math.min(naturalHeight, height / scale);

		return {
			width: cropWidth, height: cropHeight,
			cropX: (naturalWidth - cropWidth) / 2, cropY: (naturalHeight - cropHeight) / 2,
			scaleX: scale, scaleY: scale
		};
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
