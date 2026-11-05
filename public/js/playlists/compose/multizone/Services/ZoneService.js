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

import {ComposerEventBus} from "../../../../core/canvas/ComposerEventBus.js";

/**
 * The zones on the canvas: create, duplicate, remove, move and the order of the layers.
 * A new zone and a removed one are told by the events zoneAdded and zoneRemoved.
 */
export class ZoneService
{
	#fabricWrapper;
	#labeledZoneFactory;

	constructor(fabricWrapper, labeledZoneFactory)
	{
		this.#fabricWrapper      = fabricWrapper;
		this.#labeledZoneFactory = labeledZoneFactory;
	}

	create(properties)
	{
		const zone = this.#labeledZoneFactory.create(properties);
		this.#fabricWrapper.add(zone);
		ComposerEventBus.dispatchEvent(new CustomEvent("zoneAdded", {detail: {zone: zone}}));

		return zone;
	}

	insert(properties)
	{
		const zone = this.create(properties);
		this.#fabricWrapper.renderAll();

		return zone;
	}

	countObjects()
	{
		return this.#fabricWrapper.getObjects().length;
	}

	/**
	 * The zones in the order of the layers, the lowest first.
	 */
	zones()
	{
		return this.#fabricWrapper.getObjects().filter(object => object.getType() === "LabeledZone");
	}

	duplicateActive()
	{
		const active = this.#fabricWrapper.getActiveObject();
		if (active == null)
			return;

		const properties = active.getPropertiesForDuplicate();
		properties.zone_left += 20;
		properties.zone_top  += 20;

		this.#fabricWrapper.setActiveObject(this.insert(properties));
	}

	removeActive()
	{
		const active = this.#fabricWrapper.getActiveObject();
		if (active == null)
			return;

		ComposerEventBus.dispatchEvent(new CustomEvent("zoneRemoved", {detail: {id: active.getId()}}));
		this.#fabricWrapper.remove(active);
	}

	selectById(id)
	{
		const zone = this.#fabricWrapper.getObjects().find(object => object.id === id);
		if (zone === undefined)
			return null;

		this.#fabricWrapper.setActiveObject(zone);

		return zone;
	}

	moveActive(direction, step)
	{
		const active = this.#fabricWrapper.getActiveObject();
		if (active == null)
			return;

		switch (direction)
		{
			case "ArrowLeft":
				active.left -= step;
				break;
			case "ArrowRight":
				active.left += step;
				break;
			case "ArrowUp":
				active.top -= step;
				break;
			case "ArrowDown":
				active.top += step;
				break;
			default:
				break;
		}
	}

	sendToBack()
	{
		this.#fabricWrapper.getActiveObject().sendToBack();
	}

	sendBackwards()
	{
		this.#fabricWrapper.getActiveObject().sendBackwards();
	}

	bringForward()
	{
		this.#fabricWrapper.getActiveObject().bringForward();
	}

	bringToFront()
	{
		this.#fabricWrapper.getActiveObject().bringToFront();
	}
}
