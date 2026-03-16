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
		this.#fabricCanvas.setWidth(width);
		this.#hasChanged = true;
	}

	setHeight(height)
	{
		this.#fabricCanvas.setHeight(height);
		this.#hasChanged = true;
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

	resetChange()
	{
		this.#hasChanged = false;
		ComposerEventBus.dispatchEvent(new CustomEvent('resetChanged'));
	}

	getViewportTransform()
	{
		return this.#fabricCanvas.viewportTransform;
	}

	undo()
	{
		this.#fabricCanvas.undo();
	}

	redo()
	{
		this.#fabricCanvas.redo();
	}

	historySaveAction()
	{
		this.#fabricCanvas._historySaveAction();
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

	discardActiveObject()
	{
		return this.#fabricCanvas.discardActiveObject();
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

	toTemplateJSON()
	{
		return this.#fabricCanvas.toJSON([
			"mediaId",
			"fileName",
			"lockMovementX",
			"lockMovementY",
			"lockRotation",
			"lockScalingX",
			"lockScalingY",
		]);
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
		return this.#fabricCanvas.getContext("2d");
	}

	load(jsonContent)
	{
		return new Promise((resolve) => {
			this.#fabricCanvas.loadFromJSON(jsonContent, () => {
				fabric.util.clearFabricFontCache();
				fabric.charWidthsCache = {};
				fabric.Canvas.prototype.historyUndo = [];
				fabric.Canvas.prototype.historyRedo = [];
				resolve();
			});
		});
	}

	toBase64Image(format, quality, backgroundColor)
	{
		return this.#fabricCanvas.toDataURL({
			format: format,
			quality: quality,
			backgroundColor: backgroundColor
		});
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
		this.#fabricCanvas.on('mouse:up', (options) => {
			if (options.button === 1)
			{
				ComposerEventBus.dispatchEvent(new CustomEvent('mouseLeftUp', { detail: options }));
				this.#isObjectMoving = false;
			}
			else if (options.button === 3)
			{
				ComposerEventBus.dispatchEvent(new CustomEvent('mouseRightUp', { detail: options }));
			}
		});
	}
}
