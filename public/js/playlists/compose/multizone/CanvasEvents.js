/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
import './CanvasView.js';
import './ContextMenu.js';
import './ZoneProperties.js';
import './ZonesModel.js';

export class CanvasEvents
{
	screen_width  = document.getElementById("screen_width");
	screen_height = document.getElementById("screen_height");
	export_unit   = document.getElementById("export_unit");
	zoom_percent  = document.getElementById("percent");
	slider        = document.getElementById("slider");
	add_zone      = document.getElementById("add_zone");
	canvas_wrap   = document.getElementById("canvas_wrap");

	MyContextMenu = {};
	MyLabeledZoneFactory = {}
	MyZonesModel = {};
	MyCanvasView = {};
	MyZoneProperties = {};
	#playlistsService = null;
	is_autoresize = true;
	snap_to_grid = 10;

	constructor(MyZonesModel, MyContextMenu, MyCanvasView, MyZoneProperties, MyLabeledZoneFactory, playlistsService)
	{
		this.MyZonesModel         = MyZonesModel;
		this.MyContextMenu        = MyContextMenu;
		this.MyCanvasView         = MyCanvasView;
		this.MyZoneProperties     = MyZoneProperties;
		this.MyLabeledZoneFactory = MyLabeledZoneFactory;
		this.#playlistsService    = playlistsService;
	}

	isAutoResize() {
		return this.is_autoresize;
	}

	async buildUI()
	{
		this.MyCanvasView.setDimensions(this.MyZonesModel.getScreenWidth(), this.MyZonesModel.getScreenHeight());
		this.MyCanvasView.scaleCanvas(this.MyZonesModel.getZoom());

		const zones = this.MyZonesModel.getZones()
		for (let i = 0; i < zones.length; i++)
		{
			// convert zone_smil_playlist_id to zone playlist_id (SmilControl)
			if (zones[i]?.zone_smil_playlist_id != null)
			{
				zones[i].zone_playlist_id = zone.smil_playlist_id;
				delete zones[i].smil_playlist_id;
			}

			if (zones[i].zone_playlist_id > 0)
				zones[i].zone_playlist_name = await this.MyZonesModel.determinNameById(zones[i].zone_playlist_id)
			else
				zones[i].zone_playlist_name = "";


			const MyLabeledZone = this.MyLabeledZoneFactory.create(zones[i]);
			this.#addZone(MyLabeledZone);
		}

		this.screen_width.value = this.MyCanvasView.getWidth();
		this.screen_height.value = this.MyCanvasView.getHeight();
		this.export_unit.value = this.MyZonesModel.getExportUnit();
		this.slider.value = this.MyZonesModel.getZoom();
		this.zoom_percent.innerHTML = this.slider.value + ' %';

		this.MyCanvasView.renderCanvas();

	}

	initInteractions()
	{
		this.#initChangeDetectors()
		this.#initMouseEvents();
		this.#initKeyboardEvents();
		this.#initInsertObjects();
		this.#initSaveEvent();
		this.#initCloseEvent();
		this.#initUnits();
		this.#initScreenResolutionEvents();
		this.#initRangeSliderEvents();
	}

	#initChangeDetectors()
	{
		this.MyCanvasView.getCanvas().on('object:modified', (event) => {
			this.MyCanvasView.setChanged(true);

			if (event.target.scaleX !== 1 || event.target.scaleY !== 1)
				event.target.onScaling();

			this.MyZoneProperties.activate(event.target);
		});
		this.MyCanvasView.getCanvas().on('object:moving', (event) => {
			this.#snap(event);
			this.MyCanvasView.setChanged(true);
		});
		this.MyCanvasView.getCanvas().on('object:scaling', (event) => {
			this.MyCanvasView.setChanged(true);
		});
		this.MyCanvasView.getCanvas().on('selection:created', (event) => {
			this.MyZoneProperties.highlightListItemById(event.selected[0].id);
		});
		this.MyCanvasView.getCanvas().on('selection:updated', (event) => {
			this.MyZoneProperties.highlightListItemById(event.selected[0].id);
		});
		this.MyCanvasView.getCanvas().on('text:changed', (event) => {
			this.MyCanvasView.setChanged(true);
		})
		// activate this only when canvas is builded.
		this.MyCanvasView.getCanvas().on('object:added', (event) => {
			this.MyCanvasView.setChanged(true);
		})
		this.MyCanvasView.getCanvas().on('object:removed', (event) => {
			this.MyCanvasView.setChanged(true);
		})
	}

	#snap(event)
	{
		event.target.set({
			 left: Math.round(event.target.left / this.snap_to_grid) * this.snap_to_grid,
			 top: Math.round(event.target.top / this.snap_to_grid) * this.snap_to_grid
		});
		this.MyCanvasView.renderCanvas();
	}

	#initMouseEvents()
	{
		this.MyCanvasView.getCanvas().on('mouse:up', (options) => {
			this.MyContextMenu.remove();
			if (options.button === 1) // left mouse button
			{
				if (options.target == null)
				{
					this.MyZoneProperties.deactivate();
					return;
				}
				this.MyZoneProperties.activate(options.target);

			}
			else if (options.button === 3) // right mouse button
			{
				if (options.target == null)
					return;

				this.MyCanvasView.getCanvas().setActiveObject(options.target);
				this.MyContextMenu.show(options);
				this.#initContextMenuEvents()

			}
		});
	}

	#initContextMenuEvents()
	{
		let move_background = document.getElementById("move_background");
		move_background.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().sendToBack();
			this.MyContextMenu.remove();
		}
		let move_back = document.getElementById("move_back");
		move_back.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().sendBackwards();
			this.MyContextMenu.remove();
		}
		let move_front = document.getElementById("move_front");
		move_front.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().bringForward();
			this.MyContextMenu.remove();
		}
		let move_foreground = document.getElementById("move_foreground");
		move_foreground.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().bringToFront();
			this.MyContextMenu.remove();
		}

		let duplicate_zone = document.getElementById("duplicate_zone");
		duplicate_zone.onclick = () => {
			this.#duplicateZone();
			this.MyContextMenu.remove();
		}

		let delete_zone = document.getElementById("delete_zone");
		delete_zone.onclick = () => {
			this.#removeObject();
			this.MyContextMenu.remove();
		}
	}

	#initKeyboardEvents()
	{
		this.canvas_wrap.addEventListener("keydown", (event) => {
			if (event.shiftKey &&
				(event.key === "ArrowLeft" || event.key === "ArrowRight" || event.key === "ArrowUp" || event.key === "ArrowDown")) {
				this.MyCanvasView.moveActiveObject(event.key, 50);
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "D")
			{
				this.#duplicateZone();
			}
			else
			{
				switch (event.key) {
					case "Delete":
						this.#removeObject();
						break;
					case "ArrowLeft":
					case "ArrowRight":
					case "ArrowUp":
					case "ArrowDown":
						this.MyCanvasView.moveActiveObject(event.key, 1);
						break;
					default:
						break;
				}
			}
			this.MyCanvasView.renderCanvas();
			this.MyZoneProperties.activate(this.MyCanvasView.getActiveObject());
			this.MyCanvasView.setChanged(true);
		}, false);
	}

	#removeObject()
	{
		let id = this.MyCanvasView.getActiveObject().getId()
		this.MyZoneProperties.removeListItem(id);
		this.MyCanvasView.removeActiveObject();
	}

	#initInsertObjects()
	{
		this.add_zone.addEventListener("click", () => {

			const count = this.MyCanvasView.getCanvas().getObjects().length + 1
			const zone_properties  = {"zone_name": "Zone " + count};
			this.#createNewZone(zone_properties);
		});
	}

	#initUnits()
	{
		this.export_unit.addEventListener("change", () => {
			this.MyZonesModel.setExportUnit(this.export_unit.value);
		});
	}

	#initScreenResolutionEvents()
	{
		this.screen_width.addEventListener("blur", () => {
			this.MyCanvasView.setDimensions(this.screen_width.value, this.screen_height.value);
			this.MyCanvasView.scaleCanvas(this.slider.value)
		});
		this.screen_height.addEventListener("blur", () => {
			this.MyCanvasView.setDimensions(this.screen_width.value, this.screen_height.value);
			this.MyCanvasView.scaleCanvas(this.slider.value)
		});
	}

	#initRangeSliderEvents()
	{
		this.slider.addEventListener("input", () => {
			this.is_autoresize = false;
			this.zoom_percent.innerHTML = this.slider.value + ' %';
			this.MyZonesModel.setZoom(this.slider.value)
			this.MyCanvasView.scaleCanvas(this.slider.value)
		})
	}

	#initSaveEvent() {
		document.getElementById("save_zones").addEventListener("click", () => {
			this.MyZonesModel.saveToDataBase();
			this.MyCanvasView.setChanged(false);
		});
	}

	#initCloseEvent()
	{
		document.getElementById("close_zones_editor").addEventListener("click", () => {
			const changed = this.MyCanvasView.hasChanged();
			if (!changed || (changed && confirm(lang["confirm_close"])))
				window.location.href = "/playlists";1
		});

	}

	#duplicateZone()
	{
		let props = this.MyCanvasView.getActiveObject().getPropertiesForDuplicate();
		props.zone_left += 20;
		props.zone_top += 20;

		const MyLabeledZone = this.#createNewZone(props);

		this.MyCanvasView.setActiveObject(MyLabeledZone);
	}

	#createNewZone(zone_properties)
	{
		const MyLabeledZone = this.MyLabeledZoneFactory.create(zone_properties);
		this.#addZone(MyLabeledZone);

		this.MyCanvasView.getCanvas().renderAll();

		return MyLabeledZone
	}

	#addZone(MyLabeledZone)
	{
		this.MyCanvasView.addZone(MyLabeledZone);
		this.MyZoneProperties.addList(MyLabeledZone)
	}

}