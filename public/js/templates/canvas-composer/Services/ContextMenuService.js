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

export class ContextMenuService
{
	#fabricWrapper;
	#clipboard = {};

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	duplicate()
	{
		const object = this.#fabricWrapper.getActiveObject();
		object.clone(cloned =>
		{
			this.#clipboard = cloned;
			this.#pasteFromClipboardToPos();
		});
	}

	remove()
	{
		const object = this.#fabricWrapper.getActiveObject();
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


	#pasteFromClipboardToPos()
	{
		this.#clipboard.clone(cloned =>
		{
			this.#fabricWrapper.discardActiveObject();
			cloned.set({
				left: this.#clipboard.left + 20,
				top: this.#clipboard.top + 20,
				evented: true,
			});
			if (cloned.type === 'activeSelection')
			{
				cloned.canvas = this.#fabricWrapper;
				cloned.forEachObject((obj) =>
				{
					this.#fabricWrapper.add(obj);
				});
				// this should solve the unselectability
				cloned.setCoords();
			}
			else
				this.#fabricWrapper.add(cloned);

			this.#fabricWrapper.setActiveObject(cloned);
			this.#handleFabric();
		});
	}

	sendToBack()
	{
		this.#fabricWrapper.getActiveObject().sendToBack();
		this.#handleFabric();
	}

	sendBackwards()
	{
		this.#fabricWrapper.getActiveObject().sendBackwards();
		this.#handleFabric();
	}

	bringForward()
	{
		this.#fabricWrapper.getActiveObject().bringForward();
		this.#handleFabric();
	}

	bringToFront()
	{
		this.#fabricWrapper.getActiveObject().bringToFront();
		this.#handleFabric();
	}

	#handleFabric()
	{
		this.#fabricWrapper.historySaveAction();
		this.#fabricWrapper.renderAll();
	}
}
