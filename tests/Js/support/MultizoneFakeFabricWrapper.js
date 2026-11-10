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

/**
 * The FabricWrapper of the canvas for the multizone editor: the objects in the order of the layers,
 * the active object, the size and the zoom. It records the calls of the editor and whether something changed.
 */
export class MultizoneFakeFabricWrapper
{
	objects    = [];
	active     = null;
	calls      = [];
	handlers   = {};
	hasChanged = false;
	renders    = 0;
	changes    = 0;

	setWidth(width)   { this.calls.push(['setWidth', width]); }
	setHeight(height) { this.calls.push(['setHeight', height]); }
	setZoom(factor)   { this.calls.push(['setZoom', factor]); }
	renderAll()       { this.renders++; }

	changed()
	{
		this.changes++;
		this.hasChanged = true;
	}

	resetChange()
	{
		this.hasChanged = false;
	}

	on(eventName, handler)
	{
		this.handlers[eventName] = handler;
	}

	add(object)
	{
		this.objects.push(object);
		object.canvas = {getObjects: () => this.objects};
	}

	remove(object)
	{
		this.objects = this.objects.filter(other => other !== object);
		if (this.active === object)
			this.active = null;
	}

	getObjects()                { return this.objects; }
	getActiveObject()           { return this.active; }
	setActiveObject(object)     { this.active = object; }
	discardActiveObject()       { this.active = null; }

	sendToBack(object)    { this.calls.push(['sendToBack', object]); }
	sendBackwards(object) { this.calls.push(['sendBackwards', object]); }
	bringForward(object)  { this.calls.push(['bringForward', object]); }
	bringToFront(object)  { this.calls.push(['bringToFront', object]); }
}
