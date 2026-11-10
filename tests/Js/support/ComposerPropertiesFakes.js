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

import {replaceGlobals} from "./globals.js";

/**
 * The parts of templates/templates/canvas.mustache which the property panels read, without the icons.
 */
export const PROPERTIES_PAGE = `
	<ul id="globalProperties" style="display:none;">
		<li><input id="opacity" type="range" min="0" max="100" value="100"><span id="opacityDisplay">100</span></li>
		<li>
			<input id="shadowColor" type="color" value="#cccccc">
			<input id="shadowOffsetX" type="number" value="0">
			<input id="shadowOffsetY" type="number" value="0">
			<input id="shadowBlur" type="number" value="0">
			<button id="createShadow"></button>
			<button id="deleteShadow"></button>
		</li>
		<li>
			<button id="alignLeft"></button><button id="alignCenter"></button><button id="alignRight"></button>
			<button id="alignTop"></button><button id="alignMiddle"></button><button id="alignBottom"></button>
		</li>
		<li>
			<input type="color" id="strokeColor">
			<input id="strokeWidth" type="range" min="0" max="50" value="0"><span id="strokeWidthDisplay">0</span>
		</li>
		<li><input type="color" id="fillColor" style="display: none;"></li>
		<li>
			<ul id="textProperties" style="display:none;">
				<li><button id="fontFamily">Font</button><div id="fontsDropdown"><!-- upload of fonts --></div></li>
				<li><button id="textAlign"><i id="left"></i><i id="center"></i><i id="right"></i></button></li>
				<li><button id="textBold" name="bold"></button></li>
				<li><button id="textItalic" name="italic"></button></li>
				<li><button id="textUnderline" name="underline"></button></li>
				<li><input type="number" id="fontSize" min="1"></li>
			</ul>
		</li>
	</ul>
	<ul id="group_properties">
		<li><button id="object_group" style="display: none;"><svg id="group"></svg><svg id="ungroup"></svg></button></li>
	</ul>`;

/**
 * An object of fabric.js: set() takes a name and a value or an object of values.
 */
export class FakeObject
{
	constructor(options = {})
	{
		Object.assign(this, options);
	}

	set(nameOrOptions, value)
	{
		if (typeof nameOrOptions === 'string')
			this[nameOrOptions] = value;
		else
			Object.assign(this, nameOrOptions);
		return this;
	}
}

/**
 * A text of fabric.js with the styles of its characters. The selection is given with selectionStart/End.
 */
export class FakeText extends FakeObject
{
	type           = 'i-text';
	text           = 'Lobby';
	isEditing      = false;
	selectionStart = 0;
	selectionEnd   = 0;
	/** the styles of the characters, one object each */
	charStyles     = [];
	calls          = [];

	constructor(options = {})
	{
		super();
		Object.assign(this, options);
		if (this.charStyles.length === 0)
			this.charStyles = [...this.text].map(() => ({}));
	}

	getSelectionStyles(start, end, complete)
	{
		this.calls.push(['getSelectionStyles', start, end, complete]);
		const own = {fill: this.fill, fontWeight: this.fontWeight, fontStyle: this.fontStyle, fontFamily: this.fontFamily, fontSize: this.fontSize};
		return this.charStyles.slice(start, end).map(style => complete ? {...own, ...style} : {...style});
	}

	setSelectionStyles(styles, start, end)
	{
		this.calls.push(['setSelectionStyles', styles, start, end]);
		for (let i = start; i < end; i++)
			Object.assign(this.charStyles[i], styles);
	}

	removeStyle(property)
	{
		this.calls.push(['removeStyle', property]);
		for (const style of this.charStyles)
			delete style[property];
	}
}

class FakePoint
{
	constructor(x, y)
	{
		this.x = x;
		this.y = y;
	}
}

class FakeShadow
{
	constructor(options)
	{
		Object.assign(this, options);
	}
}

export class FakeGroup extends FakeObject
{
	type = 'group';
	#objects;

	constructor(objects = [], options = {})
	{
		super(options);
		this.#objects = [...objects];
	}

	getObjects()
	{
		return [...this.#objects];
	}

	removeAll()
	{
		const objects = this.#objects;
		this.#objects = [];
		return objects;
	}
}

/**
 * Like in fabric.js the selection of several objects is a group.
 */
export class FakeActiveSelection extends FakeGroup
{
	type = 'activeselection';
}

/**
 * The global fabric of the script tag, only the classes the property panels use. Use it with "using".
 */
export function useFakeFabric()
{
	return replaceGlobals({fabric: {Point: FakePoint, Shadow: FakeShadow, Group: FakeGroup, ActiveSelection: FakeActiveSelection}});
}

/**
 * The canvas of fabric.js with the order of its objects.
 */
export class FakeCanvas
{
	objects = [];
	active  = null;
	calls   = [];

	constructor(objects = [])
	{
		this.objects = [...objects];
	}

	getObjects()
	{
		return [...this.objects];
	}

	discardActiveObject()
	{
		this.calls.push('discardActiveObject');
		this.active = null;
	}

	remove(...objects)
	{
		this.objects = this.objects.filter(object => !objects.includes(object));
	}

	insertAt(index, ...objects)
	{
		this.objects.splice(index, 0, ...objects);
	}

	setActiveObject(object)
	{
		this.active = object;
	}
}

/**
 * The TemplateFabricWrapper: the active object is set by the test, the updates of the canvas are recorded.
 */
export class FakeFabricWrapper
{
	active       = null;
	modified     = [];
	renders      = 0;
	fabricCanvas = new FakeCanvas();

	getActiveObject()
	{
		return this.active;
	}

	fireObjectModified(object)
	{
		this.modified.push(object);
	}

	renderAll()
	{
		this.renders++;
	}
}

/**
 * The ToggleButtonFactory: the buttons record their updates and whether they are shown.
 */
export class FakeToggleButtonFactory
{
	buttons = new Map();

	create(element)
	{
		const button = {
			element, updates: [], shown: null,
			update(type, value) { this.updates.push([type, value]); },
			getElement()        { return this.element; },
			show()              { this.shown = true; },
			hide()              { this.shown = false; }
		};
		this.buttons.set(element?.id, button);
		return button;
	}
}
