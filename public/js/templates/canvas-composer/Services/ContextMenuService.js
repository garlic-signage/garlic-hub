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

import {TemplateFabricWrapper} from "../Utils/TemplateFabricWrapper.js";

export class ContextMenuService
{
	#fabricWrapper;
	#clipboard = {};

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	async duplicate()
	{
		const object = this.#fabricWrapper.getActiveObject();
		// Ctrl+D while nothing is selected
		if (!object)
			return;

		// with the properties of the template, e.g. the file of an image
		this.#clipboard = await object.clone(TemplateFabricWrapper.PROPERTIES);
		await this.#pasteFromClipboardToPos();
	}

	remove()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object)
			return;

		this.#fabricWrapper.remove(object);
		this.#handleFabric();
	}

	isLocked()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (object === null)
			return false;

		return object.lockMovementX && object.lockMovementY;
	}

	isSelectable()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (object === null)
			return false;

		return object.selectable;
	}


	toggleLockedStatus()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (object === null)
			return;

		const isLock = !this.isLocked();

		["lockMovementX", "lockMovementY", "lockSkewingX", "lockSkewingY",
			"lockRotation", "lockScalingX", "lockScalingY"]
			.forEach(prop => object[prop] = isLock);

		this.#handleFabric();
	}

	toggleSelectableStatus()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (object === null)
			return;

		const isSelectable = !object.selectable;

		object.selectable = isSelectable;
		object.evented = isSelectable;

		this.#handleFabric();
	}


	async #pasteFromClipboardToPos()
	{
		const cloned = await this.#clipboard.clone(TemplateFabricWrapper.PROPERTIES);
		this.#fabricWrapper.discardActiveObject();
		cloned.set({
			left: this.#clipboard.left + 20,
			top: this.#clipboard.top + 20,
			evented: true,
		});
		if (cloned instanceof fabric.ActiveSelection)
		{
			// the objects of a selection go on the canvas one by one
			cloned.canvas = this.#fabricWrapper.fabricCanvas;
			cloned.forEachObject((obj) =>
			{
				this.#fabricWrapper.add(obj);
			});
			cloned.setCoords();
		}
		else
			this.#fabricWrapper.add(cloned);

		this.#fabricWrapper.setActiveObject(cloned);
		this.#handleFabric();
	}

	sendToBack()
	{
		this.#fabricWrapper.sendToBack(this.#fabricWrapper.getActiveObject());
		this.#handleFabric();
	}

	sendBackwards()
	{
		this.#fabricWrapper.sendBackwards(this.#fabricWrapper.getActiveObject());
		this.#handleFabric();
	}

	bringForward()
	{
		this.#fabricWrapper.bringForward(this.#fabricWrapper.getActiveObject());
		this.#handleFabric();
	}

	bringToFront()
	{
		this.#fabricWrapper.bringToFront(this.#fabricWrapper.getActiveObject());
		this.#handleFabric();
	}

	#handleFabric()
	{
		this.#fabricWrapper.historySaveAction();
		this.#fabricWrapper.renderAll();
	}
}
