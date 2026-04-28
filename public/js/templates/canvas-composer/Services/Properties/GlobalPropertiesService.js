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

import {BasePropertyService} from "./BasePropertyService.js";

export class GlobalPropertiesService extends BasePropertyService
{
	#canvasWidth;
	#canvasHeight;

	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	setCanvasDimension(width, height)
	{
		this.#canvasWidth = width;
		this.#canvasHeight = height;
	}

	getOpacity()
	{
		const object = this._getActiveObject();
		return parseInt(object.opacity * 100);
	}

	setOpacity(value)
	{
		const object = this._getActiveObject();
		object.set("opacity", Number(value) / 100)
		this._updateCanvas(object);
	}

	getStrokeColor()
	{
		const object = this._getActiveObject();
		return object.stroke;
	}

	setStrokeColor(value)
	{
		const object = this._getActiveObject();
		object.set("stroke", value);
		this._updateCanvas(object);
	}

	getStrokeWidth()
	{
		const object = this._getActiveObject();
		return object.strokeWidth;
	}

	setStrokeWidth(value)
	{
		const object = this._getActiveObject();
		object.set("strokeWidth", Number(value));
		this._updateCanvas(object);
	}

	getScaleX()
	{
		const object = this._getActiveObject();
		return object.scaleX;
	}

	setScaleX(value)
	{
		const object = this._getActiveObject();
		object.set("scaleX", Number(value));
		this._updateCanvas(object);
	}

	getScaleY()
	{
		const object = this._getActiveObject();
		return object.scaleY;
	}

	setScaleY(value)
	{
		const object = this._getActiveObject();
		object.set("scaleY", Number(value));
		this._updateCanvas(object);
	}

	setPosition(position)
	{
		const object       = this._getActiveObject();

		let canvasBound = {
			width: this.#canvasWidth,
			height: this.#canvasHeight,
			center: {
				x: this.#canvasWidth / 2,
				y: this.#canvasHeight / 2
			}
		}

		const bound = object.getBoundingRect(true);
		const s = bound.height;
		const a = bound.width;

		switch (position)
		{
			case "top":
				object.setPositionByOrigin(new fabric.Point(object.getCenterPoint().x, s / 2 + canvasBound.center.y - canvasBound.height / 2), "center", "center")
				break
			case "middle":
				object.setPositionByOrigin({
					x: object.getCenterPoint().x,
					y: canvasBound.center.y
				}, "center", "center")
				break
			case "bottom":
				object.setPositionByOrigin(new fabric.Point(object.getCenterPoint().x, canvasBound.center.y + canvasBound.height / 2 - s / 2), "center", "center")
				break
			case "left":
				object.setPositionByOrigin(new fabric.Point(canvasBound.center.x - canvasBound.width / 2 + a / 2, object.getCenterPoint().y), "center", "center")
				break
			case "center":
				object.setPositionByOrigin({
					x: canvasBound.center.x,
					y: object.getCenterPoint().y
				}, "center", "center")
				break
			case "right":
				object.setPositionByOrigin(new fabric.Point(canvasBound.center.x + canvasBound.width / 2 - a / 2, object.getCenterPoint().y), "center", "center")
		}
		this._updateCanvas(object);
	}

}
