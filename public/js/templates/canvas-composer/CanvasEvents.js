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

export class CanvasEvents
{
	#contextMenu = {};
	#canvasView = {};
	#mediaDialog = {};
	#mediaSelector = {};
	#itemProperties = {};
	#isAutoresize = true;

	constructor(contextMenu, canvasView, mediaDialog, mediaSelector, itemProperties)
	{
		this.#contextMenu    = contextMenu;
		this.#canvasView     = canvasView;
		this.#mediaDialog    = mediaDialog;
		this.#mediaSelector  = mediaSelector;
		this.#itemProperties = itemProperties;
	}

	isAutoResize()
	{
		return this.#isAutoresize;
	}

	initChangeDetectors()
	{
		// check if canvas was changed
		this.#canvasView.getCanvas().on('object:modified', (event) => {
			this.#canvasView.setChanged(true);
		})
		this.#canvasView.getCanvas().on('text:changed', (event) => {
			this.#canvasView.setChanged(true);
		})
		// activate this only when canvas is builded.
		this.#canvasView.getCanvas().on('object:added', (event) => {
			this.#canvasView.setChanged(true);
		})
		this.#canvasView.getCanvas().on('object:removed', (event) => {
			this.#canvasView.setChanged(true);
		})
	}

	initEditEvents() {
		this.initMouseEvents();
		this.initKeyboardEvents();
	}

	initMouseEvents()
	{
		this.#canvasView.getCanvas().on('mouse:up', (options) => {
			this.#contextMenu.remove();
			this.#mediaDialog.remove();
			if (options.button === 1)
			{
				// todo implement changing of canvas properties
				if (options.target == null)
				{
					this.#itemProperties.deactivateAllProperties();
					return;
				}

				this.#itemProperties.deactivatePrevious(this.#canvasView.getCanvas().getActiveObject().type);
				this.#itemProperties.activateCurrent(this.#canvasView.getCanvas().getActiveObject());

			}
			else if (options.button === 3)
			{
				if (options.target == null)
					return;

				this.#canvasView.getCanvas().setActiveObject(options.target);
				this.#contextMenu.show(options);
			}
		});
	}

	initKeyboardEvents()
	{
		this.#canvasView.getCanvasWrap().addEventListener("keydown", (event) => {
			if (event.shiftKey &&
				(event.key === "ArrowLeft" || event.key === "ArrowRight" || event.key === "ArrowUp" || event.key === "ArrowDown"))
			{
				this.#canvasView.moveActiveObject(event.key, 50);
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "Z")
			{
				this.#canvasView.getCanvas().undo()
				this.#itemProperties.deactivateAllProperties();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "Y")
			{
				this.#canvasView.getCanvas().redo()
				this.#itemProperties.deactivateAllProperties();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "D")
			{
				this.#canvasView.dublicateActiveObject()
				this.#itemProperties.deactivateAllProperties();
			}
			else
			{
				switch (event.key) {
					case "Delete":
						this.#canvasView.removeActiveObject();
						this.#canvasView.getCanvas()._historySaveAction()
						break;
					case "ArrowLeft":
					case "ArrowRight":
					case "ArrowUp":
					case "ArrowDown":
						this.#canvasView.moveActiveObject(event.key, 1);
						this.#canvasView.getCanvas()._historySaveAction()
						break;
					case "z":
						this.#canvasView.getCanvas().undo();
						break;
					default:
						break;
				}
			}
		}, false);
	}

	initInsertObjects()
	{
		this.#canvasView.getInsertImage().addEventListener("click", () => {
			if (this.#mediaDialog.isOpen)
				return;

			this.#mediaDialog.displayMediaSelector();
			this.#mediaDialog.initCancelEvent();
			this.#mediaDialog.initInsertEvent();
		});

		this.#canvasView.getInsertText().addEventListener("click", () => {
			let text = new fabric.Textbox('Lorem ipsum', {
				left: 50, top: 10, width: 400, fontFamily: 'Arial',
				fill: '#000000', fontSize: 64
			});
			this.#canvasView.getCanvas().add(text);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertCircle().addEventListener("click", () => {
			var circle = new fabric.Circle({
				left: 50, top: 10, radius: 200,
				fill: '#000000'
			});
			this.#canvasView.getCanvas().add(circle);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertTriangle().addEventListener("click", () => {
			var triangle = new fabric.Triangle({
				left: 50, top: 10, width: 400, height: 400,
				fill: '#000000'
			});
			this.#canvasView.getCanvas().add(triangle);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertRectangle().addEventListener("click", () => {
			var rect = new fabric.Rect({
				left: 50, top: 10, width: 400, height: 300,
				fill: '#000000'
			});
			this.#canvasView.getCanvas().add(rect);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertPolygon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 5;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 5; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var polygon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.#canvasView.getCanvas().add(polygon);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertHexagon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 6;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 6; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var hexagon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.#canvasView.getCanvas().add(hexagon);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});
		this.#canvasView.getInsertOctagon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 8;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 8; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var octagon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.#canvasView.getCanvas().add(octagon);
			this.#canvasView.getCanvas()._historySaveAction()
			this.#canvasView.getCanvas().renderAll();
		});

		this.#canvasView.getUndo().addEventListener("click", () => {
			this.#canvasView.getCanvas().undo()
			this.#itemProperties.deactivateAllProperties();
		});
		this.#canvasView.getRedo().addEventListener("click", () => {
			this.#canvasView.getCanvas().redo()
			this.#itemProperties.deactivateAllProperties();
		});

	}

	initRangeSliderEvents()
	{
		this.#canvasView.getSlider().oninput = () => {
			this.#isAutoresize = false;
			this.#canvasView.scalePercent();
			this.#canvasView.scaleCanvas()
		}
	}

	initSaveEvent(fabricService)
	{
		document.getElementById("save_template").addEventListener("click", () => {

			const format = document.getElementById("formatSelector")?.value;
			const quality = document.getElementById("imageQuality")?.value;

			if (format !== null)
			{
				fabricService.imageFormat = format
				fabricService.imageQuality = quality;
			}
			fabricService.save(this.#canvasView.getCanvas());
			this.#canvasView.setChanged(false);
		});
	}
	initResetEvent(fabricService)
	{
		const reset = document.getElementById("reset_template");
		if (reset === null)
			return;

		reset.addEventListener("click", () => {
			if (confirm(this.#canvasView.getLangByKey('confirm_reset')) === false)
				return;

			const templateId = document.getElementById('template_id').value;
			this.#canvasView.getCanvas().clear();
			fabricService.resetFromTemplateDataBase(templateId);
			this.#canvasView.setChanged(false);
		});
	}

	initCloseEvent(redirectUrl)
	{
		document.getElementById("close_template_editor").addEventListener("click", () => {
			if (this.#canvasView.hasChanged() === true && confirm(this.#canvasView.getLangByKey('confirm_close')) === false)
				return;

			window.location.href = redirectUrl;
		});
	}

}