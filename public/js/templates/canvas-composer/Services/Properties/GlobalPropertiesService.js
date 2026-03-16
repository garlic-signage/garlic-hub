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

	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	getOpacity()
	{
		const object = this._getActiveObject();
		return object.opacity * 100;
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

	setPosition(position)
	{
		const object = this._getActiveObject();

		let canvasBound = {
			width: this.fabricWrapper.getWidth(),
			height: this.fabricWrapper.getHeight(),
			center: {
				x: this.fabricWrapper.getWidth() / 2,
				y: this.fabricWrapper.getHeight() / 2
			}
		}

		let s = object.getBoundingRect().height / this.fabricWrapper.getViewportTransform()[3],
			a = object.getBoundingRect().width / this.fabricWrapper.getViewportTransform()[0];

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
