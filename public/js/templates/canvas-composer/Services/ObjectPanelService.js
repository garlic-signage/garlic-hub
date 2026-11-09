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

import {BasePropertyService} from "./Properties/BasePropertyService.js";

/**
 * The objects of the template for the panel beside the canvas: the list to select them, also a hidden one, and the
 * position, size and rotation of the selected object in pixels of the template. The locks of an object apply here
 * like with the mouse.
 */
export class ObjectPanelService extends BasePropertyService
{
	static #MAX_LABEL_LENGTH = 30;

	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	/**
	 * The topmost object first, like the order on the canvas from the front. The index is the one of the canvas.
	 *
	 * @return {{index: number, kind: string, shape: string, text: string, isLocked: boolean, isSelectable: boolean, isActive: boolean}[]}
	 */
	listObjects()
	{
		const objects = this.fabricWrapper.getObjects();
		const actives = this.fabricWrapper.getActiveObjects();
		const entries = objects.map((object, index) => ({
			index:        index,
			kind:         ObjectPanelService.#kindOf(object),
			shape:        String(object.type).toLowerCase(),
			text:         ObjectPanelService.#textOf(object),
			isLocked:     object.lockMovementX === true && object.lockMovementY === true,
			isSelectable: object.selectable !== false,
			isActive:     actives.includes(object)
		}));

		return entries.reverse();
	}

	/**
	 * @return {Object|null} the object, null when it does not exist (anymore) or can not be selected
	 */
	selectByIndex(index)
	{
		const object = this.fabricWrapper.getObjects()[index] ?? null;
		if (object === null || object.selectable === false)
			return null;

		this.fabricWrapper.setActiveObject(object);
		this.fabricWrapper.renderAll();

		return object;
	}

	/**
	 * @return {{left: number, top: number, width: number, height: number, angle: number}|null} null without a single
	 * selected object: a selection of several has no position of its own for the panel
	 */
	getGeometry()
	{
		const object = this.#findSingleObject();
		if (object === null)
			return null;

		return {
			left:   Math.round(object.left),
			top:    Math.round(object.top),
			width:  Math.round(object.width * object.scaleX),
			height: Math.round(object.height * object.scaleY),
			angle:  Math.round(object.angle)
		};
	}

	/**
	 * Which fields may change the selected object: the locks of the template, the height of a text follows its
	 * lines.
	 */
	getEditable()
	{
		const object = this.#findSingleObject();
		if (object === null)
			return {left: false, top: false, width: false, height: false, angle: false};

		return {
			left:   object.lockMovementX !== true,
			top:    object.lockMovementY !== true,
			width:  object.lockScalingX !== true,
			height: object.lockScalingY !== true && !ObjectPanelService.#isText(object),
			angle:  object.lockRotation !== true
		};
	}

	setLeft(value)
	{
		this.#change("left", value, (object, number) => object.set("left", number));
	}

	setTop(value)
	{
		this.#change("top", value, (object, number) => object.set("top", number));
	}

	/**
	 * A text gets a wider box and wraps its lines new, everything else is scaled: an image keeps its cut.
	 */
	setWidth(value)
	{
		this.#change("width", value, (object, number) =>
		{
			const width = Math.max(1, number);
			if (ObjectPanelService.#isText(object))
				object.set("width", width / object.scaleX);
			else
				object.set("scaleX", width / object.width);
		});
	}

	setHeight(value)
	{
		this.#change("height", value, (object, number) => object.set("scaleY", Math.max(1, number) / object.height));
	}

	setAngle(value)
	{
		this.#change("angle", value, (object, number) => object.rotate(number % 360));
	}

	#change(field, value, apply)
	{
		const object = this.#findSingleObject();
		const number = Number(value);
		if (object === null || value === "" || !Number.isFinite(number) || !this.getEditable()[field])
			return;

		apply(object, number);
		object.setCoords();
		this._updateCanvas(object);
	}

	#findSingleObject()
	{
		const object = this._findActiveObject();
		if (object === null || String(object.type).toLowerCase() === "activeselection")
			return null;

		return object;
	}

	static #kindOf(object)
	{
		if (ObjectPanelService.#isText(object))
			return "text";

		switch (String(object.type).toLowerCase())
		{
			case "image":
				return "image";
			case "group":
				return "group";
			default:
				return "shape";
		}
	}

	/**
	 * The first line of a text, the file of an image, nothing else: the panel names it by its kind then.
	 */
	static #textOf(object)
	{
		let text = "";
		if (ObjectPanelService.#isText(object))
			text = String(object.text ?? "").split("\n")[0].trim();
		else if (typeof object.fileName === "string")
			text = object.fileName;

		if (text.length > ObjectPanelService.#MAX_LABEL_LENGTH)
			text = text.substring(0, ObjectPanelService.#MAX_LABEL_LENGTH) + "…";

		return text;
	}

	static #isText(object)
	{
		return ["text", "i-text", "textbox"].includes(String(object.type).toLowerCase());
	}
}
