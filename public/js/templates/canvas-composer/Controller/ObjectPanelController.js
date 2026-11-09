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

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";

/**
 * The panel left of the canvas: the list follows the objects, the selection and their order, the fields follow
 * the selected object also while it is dragged. A change in a field counts like one with the mouse (history,
 * unsaved).
 */
export class ObjectPanelController
{
	static #FIELD_SETTERS = {
		left:   "setLeft",
		top:    "setTop",
		width:  "setWidth",
		height: "setHeight",
		angle:  "setAngle"
	};

	#objectPanelView;
	#objectPanelService;
	#fabricWrapper;
	#composerContext;
	#isRefreshPending = false;

	constructor(objectPanelView, objectPanelService, fabricWrapper, composerContext)
	{
		this.#objectPanelView    = objectPanelView;
		this.#objectPanelService = objectPanelService;
		this.#fabricWrapper      = fabricWrapper;
		this.#composerContext    = composerContext;
	}

	init()
	{
		this.#objectPanelView.toggle.addEventListener("click", () =>
		{
			this.#objectPanelView.toggleCollapsed();
			// the canvas gets the space
			ComposerEventBus.dispatchEvent(new CustomEvent("workspaceResized"));
		});

		this.#objectPanelView.list.addEventListener("click", (event) => this.#select(event));

		for (const [name, setter] of Object.entries(ObjectPanelController.#FIELD_SETTERS))
		{
			this.#objectPanelView.fields[name].addEventListener("change", (event) =>
			{
				this.#objectPanelService[setter](event.target.value);
				this.#showGeometry();
			});
		}

		// a whole new list: the objects, their order, their texts or locks changed
		for (const eventName of ["object:added", "object:removed", "object:modified", "text:changed",
			"selection:created", "selection:updated", "selection:cleared", "history:undo", "history:redo"])
		{
			this.#fabricWrapper.on(eventName, () => this.#scheduleRefresh());
		}
		ComposerEventBus.addEventListener("canvasUpdated", () => this.#scheduleRefresh());
		ComposerEventBus.addEventListener("objectsChanged", () => this.#scheduleRefresh());

		// only the fields while the mouse moves an object
		for (const eventName of ["object:moving", "object:scaling", "object:rotating", "object:resizing"])
		{
			this.#fabricWrapper.on(eventName, () => this.#showGeometry());
		}

		this.#refresh();
	}

	/**
	 * The properties in the toolbar like after a click on the canvas.
	 */
	#select(event)
	{
		const index = this.#objectPanelView.getIndexFromEvent(event);
		if (index === null)
			return;

		const object = this.#objectPanelService.selectByIndex(index);
		if (object !== null)
			ComposerEventBus.dispatchEvent(new CustomEvent("objectSelected", {detail: {target: object}}));
	}

	/**
	 * Once per frame: loading or undo adds every object on its own.
	 */
	#scheduleRefresh()
	{
		if (this.#isRefreshPending)
			return;

		this.#isRefreshPending = true;
		requestAnimationFrame(() =>
		{
			this.#isRefreshPending = false;
			this.#refresh();
		});
	}

	#refresh()
	{
		const entries = this.#objectPanelService.listObjects().map(entry => ({
			...entry,
			label: entry.text !== "" ? entry.text : this.#composerContext.getLangByKey("object_" + entry.kind)
		}));
		this.#objectPanelView.renderList(entries, this.#composerContext.getLangByKey("no_objects"));
		this.#showGeometry();
	}

	#showGeometry()
	{
		this.#objectPanelView.showGeometry(
			this.#objectPanelService.getGeometry(),
			this.#objectPanelService.getEditable()
		);
	}
}
