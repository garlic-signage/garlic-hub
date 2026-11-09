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

import {ComposerEventBus} from "./ComposerEventBus.js";

/**
 * The fabric canvas of a composer: the template composer and the multizone editor.
 *
 * It tells the composer by the ComposerEventBus what happened on the canvas: setChanged, resetChanged,
 * snapToGrid and drawGrid while an object moves, mouseLeftUp and mouseRightUp.
 */
export class FabricWrapper
{
	#fabricCanvas
	#hasChanged = false;
	#isObjectMoving = false;

	constructor(fabricCanvas)
	{
		this.#fabricCanvas = fabricCanvas;
		this.#initChangeDetectors();
		this.#initMouseEvents();
		this.#initGridEvents()
	}

	setWidth(width)
	{
		this.#fabricCanvas.setDimensions({width: width});
	}

	setHeight(height)
	{
		this.#fabricCanvas.setDimensions({height: height});
	}

	getWidth()
	{
		return this.#fabricCanvas.getWidth();
	}

	getHeight()
	{
		return this.#fabricCanvas.getHeight();
	}

	get hasChanged()
	{
		return this.#hasChanged;
	}

	changed()
	{
		this.#hasChanged = true;
		ComposerEventBus.dispatchEvent(new CustomEvent('setChanged'));
	}

	/**
	 * Changed without the event setChanged.
	 */
	markChanged()
	{
		this.#hasChanged = true;
	}

	resetChange()
	{
		this.#hasChanged = false;
		ComposerEventBus.dispatchEvent(new CustomEvent('resetChanged'));
	}

	/**
	 * An event of fabric which the wrapper does not pass on itself, e.g. selection:created.
	 */
	on(eventName, handler)
	{
		this.#fabricCanvas.on(eventName, handler);
	}

	getViewportTransform()
	{
		return this.#fabricCanvas.viewportTransform;
	}

	add(object)
	{
		this.#fabricCanvas.add(object);
	}

	remove(object)
	{
		this.#fabricCanvas.remove(object);
	}

	renderAll()
	{
		this.#fabricCanvas.renderAll();
	}

	getObjects()
	{
		return this.#fabricCanvas.getObjects();
	}

	setActiveObject(object)
	{
		this.#fabricCanvas.setActiveObject(object);
	}

	getActiveObject()
	{
		return this.#fabricCanvas.getActiveObject();
	}

	/**
	 * The selected objects: one, the ones of a selection of several or none.
	 */
	getActiveObjects()
	{
		return this.#fabricCanvas.getActiveObjects();
	}

	discardActiveObject()
	{
		return this.#fabricCanvas.discardActiveObject();
	}

	/**
	 * The order of the objects: the canvas owns it since fabric.js 6.
	 */
	sendToBack(object)
	{
		this.#fabricCanvas.sendObjectToBack(object);
	}

	sendBackwards(object)
	{
		this.#fabricCanvas.sendObjectBackwards(object);
	}

	bringForward(object)
	{
		this.#fabricCanvas.bringObjectForward(object);
	}

	bringToFront(object)
	{
		this.#fabricCanvas.bringObjectToFront(object);
	}

	/**
	 * The bounding box of an object on the canvas element, with the zoom and the position of the view. fabric.js
	 * gives it in the units of the template.
	 *
	 * @return {{left: number, top: number, width: number, height: number}}
	 */
	getViewportBoundingRect(object)
	{
		const rect = object.getBoundingRect();
		const vpt  = this.#fabricCanvas.viewportTransform;

		return {
			left: rect.left * vpt[0] + vpt[4],
			top: rect.top * vpt[3] + vpt[5],
			width: rect.width * vpt[0],
			height: rect.height * vpt[3]
		};
	}

	setZoom(factor)
	{
		this.#fabricCanvas.setZoom(factor);
	}

	getZoom()
	{
		return this.#fabricCanvas.getZoom();
	}

	zoomToPoint(x, y, factor)
	{
		this.#fabricCanvas.zoomToPoint(new fabric.Point(x, y), factor);
	}

	clear()
	{
		this.#fabricCanvas.clear();
	}

	fireObjectModified(object)
	{
		this.#fabricCanvas.fire("object:modified", { target: object });
	}

	fireUpdateSelection(object)
	{
		this.#fabricCanvas.fire("selection:updated", { target: object });
	}

	getContext()
	{
		return this.#fabricCanvas.getContext();
	}

	/**
	 * For the subclasses: the canvas for what is special to their composer.
	 */
	get fabricCanvas()
	{
		return this.#fabricCanvas;
	}

	#initChangeDetectors()
	{
		// check if canvas was changed
		this.#fabricCanvas.on('object:modified', (event) =>
		{
			this.changed();
		})
		this.#fabricCanvas.on('text:changed', (event) =>
		{
			this.changed();
		})
		this.#fabricCanvas.on('object:added', (event) =>
		{
			this.changed();
		})
		this.#fabricCanvas.on('object:removed', (event) =>
		{
			this.changed();
		})
	}

	#initGridEvents()
	{
		this.#fabricCanvas.on('object:moving', (options) =>
		{
			ComposerEventBus.dispatchEvent(new CustomEvent('snapToGrid', {detail: options}));
			this.#isObjectMoving = true;
		});
		this.#fabricCanvas.on('after:render', () =>
		{
			if (this.#isObjectMoving === true)
				ComposerEventBus.dispatchEvent(new CustomEvent('drawGrid'));
		});
	}

	#initMouseEvents()
	{
		// the button of the browser: 0 left, 2 right
		this.#fabricCanvas.on('mouse:up', (options) => {
			const button = options.e?.button ?? 0;
			if (button === 0)
			{
				ComposerEventBus.dispatchEvent(new CustomEvent('mouseLeftUp', { detail: options }));
				this.#isObjectMoving = false;
			}
			else if (button === 2)
			{
				ComposerEventBus.dispatchEvent(new CustomEvent('mouseRightUp', { detail: options }));
			}
		});
	}
}
