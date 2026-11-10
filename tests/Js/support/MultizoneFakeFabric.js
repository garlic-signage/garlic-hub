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
 * The few classes of fabric.js which the multizone editor uses. fabric is a global of a script tag and
 * LabeledZone extends fabric.Group when its module is loaded: import this file before the modules of the editor.
 * Simplified: a group takes the place and size of its first object with the stroke, as fabric does for a zone.
 */
class FakeObject
{
	constructor(options = {})
	{
		Object.assign(this, options);
	}

	set(options)
	{
		Object.assign(this, options);
		return this;
	}
}

class FakeRect extends FakeObject {}

class FakeText extends FakeObject
{
	constructor(text, options = {})
	{
		super(options);
		this.text = text;
	}
}

class FakeGroup extends FakeObject
{
	_objects = [];
	controlsVisibility = {};
	scaleX = 1;
	scaleY = 1;

	constructor(objects = [], options = {})
	{
		super(options);
		this._objects = objects;
		const [first] = objects;
		if (first !== undefined)
		{
			const stroke = first.strokeWidth ?? 0;
			this.left    = first.left;
			this.top     = first.top;
			this.width   = first.width + stroke;
			this.height  = first.height + stroke;
		}
	}

	item(index)
	{
		return this._objects[index];
	}

	setControlsVisibility(options)
	{
		Object.assign(this.controlsVisibility, options);
	}
}

export const fabric = {Group: FakeGroup, Rect: FakeRect, Text: FakeText};

globalThis.fabric ??= fabric;
