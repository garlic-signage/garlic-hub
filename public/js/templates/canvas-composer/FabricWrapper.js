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

export class FabricWrapper extends EventTarget
{
	#fabricCanvas
	#hasChanged = false;

	constructor(fabricCanvas)
	{
		super();
		this.#fabricCanvas = fabricCanvas;
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

	get hasChanged()
	{
		return this.#hasChanged;
	}

	changed()
	{
		this.#hasChanged = true;
	}

	resetChange()
	{
		this.#hasChanged = false;
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

	zoom(factor)
	{
		this.#fabricCanvas.setZoom(factor);
	}

	zoomToPoint(x, y, factor)
	{
		this.#fabricCanvas.zoomToPoint(new fabric.Point(x, y), factor);
	}

	toJSON()
	{
		return this.#fabricCanvas.toJSON();
	}

	clear()
	{
		this.#fabricCanvas.clear();
	}

	load(jsonContent)
	{
		this.#fabricCanvas.loadFromJSON(jsonContent, () => {
			fabric.clearFabricFontCache();
			fabric.charWidthsCache = {};
			fabric.Canvas.prototype.historyUndo = []
			fabric.Canvas.prototype.historyRedo = []
			this.dispatchEvent(new CustomEvent("loadCompleted"));
		});
	}

	toBase64Image(mimeType, quality, backgroundColor)
	{
		return this.#fabricCanvas.toDataURL({
			format: mimeType.split('/')[1],
			quality: quality,
			backgroundColor: backgroundColor
		});
	}

	#initChangeDetectors()
	{
		// check if canvas was changed
		this.#fabricCanvas.on('object:modified', (event) => {
			this.#hasChanged = true;
		})
		this.#fabricCanvas.on('text:changed', (event) => {
			this.#hasChanged = true;
		})
		this.#fabricCanvas.on('object:added', (event) => {
			this.#hasChanged = true;
		})
		this.#fabricCanvas.on('object:removed', (event) => {
			this.#hasChanged = true;
		})
	}

	#initMouseEvents()
	{
		this.#fabricCanvas.on('mouse:up', (options) => {
			if (options.button === 1)
			{
				this.dispatchEvent(new CustomEvent('mouseLeftUp', { detail: options }));
			}
			else if (options.button === 3)
			{
				this.dispatchEvent(new CustomEvent('mouseRightUp', { detail: options }));
			}
		});
	}
}
