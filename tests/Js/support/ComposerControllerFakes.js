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

import "@support/MultizoneFakeFabric.js";

// the Event of Deno for the fake elements: createDom() sets the one of linkedom, which fits only its nodes
const DenoEvent = globalThis.Event;

/**
 * Helpers for the tests of the controllers of the template composer. The controllers get fake views and services:
 * the elements of a fake view are EventTargets of Deno, so the CustomEvents of the controllers fit the bus.
 */

/**
 * The image class of fabric which the composer asks with instanceof. The fake fabric of the multizone tests has
 * none, the global keeps the first one: create images only with imageObject().
 */
globalThis.fabric.FabricImage ??= class FakeFabricImage
{
	constructor(options = {})
	{
		Object.assign(this, options);
	}
};

export function imageObject(options = {})
{
	return new globalThis.fabric.FabricImage({type: 'image', ...options});
}

export function groupObject(options = {})
{
	return Object.assign(new globalThis.fabric.Group([]), {type: 'group'}, options);
}

/**
 * An element of a fake view: an EventTarget with the given properties, e.g. value or style.
 */
export function element(properties = {})
{
	return Object.assign(new EventTarget(), properties);
}

/**
 * Sends an event like the browser, with properties like key or ctrlKey: to a node of the page (linkedom) or to an
 * element of a fake view.
 */
export function fire(target, type, properties = {})
{
	const EventClass = 'nodeType' in target ? globalThis.Event : DenoEvent;
	const event      = new EventClass(type, {bubbles: true, cancelable: true});
	Object.assign(event, properties);
	target.dispatchEvent(event);
	return event;
}

export function tick()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

/**
 * An object which records the calls of the given methods; a method may answer with a value of answers.
 */
export function recorder(methods, answers = {})
{
	const fake = {calls: []};
	for (const method of methods)
	{
		fake[method] = (...args) =>
		{
			fake.calls.push([method, ...args]);
			const answer = answers[method];
			return typeof answer === 'function' ? answer(...args) : answer;
		};
	}
	return fake;
}

/**
 * The fabricWrapper with on(): the test calls the handlers of the canvas events with emit().
 */
export class FakeFabricWrapper
{
	handlers     = {};
	calls        = [];
	activeObject = null;
	objects      = [];

	on(name, handler)
	{
		(this.handlers[name] ??= []).push(handler);
	}

	emit(name, event = {})
	{
		for (const handler of this.handlers[name] ?? [])
			handler(event);
	}

	getActiveObject()       { return this.activeObject; }
	setActiveObject(object) { this.calls.push(['setActiveObject', object]); this.activeObject = object; }
	getObjects()            { return this.objects; }
	renderAll()             { this.calls.push(['renderAll']); }
	undo()                  { this.calls.push(['undo']); }
	redo()                  { this.calls.push(['redo']); }
}
