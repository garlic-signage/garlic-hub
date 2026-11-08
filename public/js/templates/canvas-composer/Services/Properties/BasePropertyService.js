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

export class BasePropertyService
{
	#fabricWrapper;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	get fabricWrapper()
	{
		return this.#fabricWrapper;
	}

	/**
	 * @return {Object|null} null when nothing is selected
	 */
	_findActiveObject()
	{
		return this.#fabricWrapper.getActiveObject() ?? null;
	}

	_getActiveObject()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object) throw new Error("No active object");
		return object;
	}

	/**
	 * A style of a text: without a selection for the whole text on the object itself, so that its property is the
	 * real value, with a selection only for the selected characters.
	 */
	_setTextStyle(object, property, value)
	{
		if (object.selectionStart === object.selectionEnd)
		{
			object.removeStyle(property);
			object.set(property, value);
		}
		else
			object.setSelectionStyles({[property]: value}, object.selectionStart, object.selectionEnd);
	}

	_updateCanvas(object)
	{
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}

}
