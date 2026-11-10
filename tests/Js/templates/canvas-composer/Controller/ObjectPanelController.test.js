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

import {assertEquals}                               from "@std/assert";
import {replaceGlobals}                             from "@support/globals.js";
import {element, fire, FakeFabricWrapper, recorder} from "@support/ComposerControllerFakes.js";
import {busOfTest}                                  from "@support/MultizoneEvents.js";
import {ObjectPanelController}                      from "@js/templates/canvas-composer/Controller/ObjectPanelController.js";

const LANG = {object_image: 'Image', object_rect: 'Rectangle', no_objects: 'No objects'};

const OBJECTS = [
	{index: 0, kind: 'image', text: ''},
	{index: 1, kind: 'text', text: 'Welcome'},
	{index: 2, kind: 'rect', text: ''},
];

const GEOMETRY = {left: 10, top: 20, width: 300, height: 200, angle: 0};

function createController()
{
	const frames  = [];
	const globals = replaceGlobals({requestAnimationFrame: (callback) => frames.push(callback)});
	const bus     = busOfTest('workspaceResized', 'objectSelected');
	const view    = recorder(['toggleCollapsed', 'renderList', 'showGeometry', 'getIndexFromEvent'],
		{getIndexFromEvent: (event) => event.index ?? null});
	view.toggle = element();
	view.list   = element();
	view.fields = Object.fromEntries(['left', 'top', 'width', 'height', 'angle'].map(name => [name, element({value: ''})]));
	const service = recorder(['setLeft', 'setTop', 'setWidth', 'setHeight', 'setAngle', 'selectByIndex', 'listObjects',
		'getGeometry', 'getEditable'], {
		listObjects: () => service.objects, getGeometry: GEOMETRY, getEditable: true,
		selectByIndex: (index) => service.objects[index] ?? null
	});
	service.objects = OBJECTS;
	const wrapper = new FakeFabricWrapper();
	new ObjectPanelController(view, service, wrapper, {getLangByKey: (key) => LANG[key]}).init();

	return {
		bus, view, service, wrapper, frames,
		renders: () => view.calls.filter(call => call[0] === 'renderList').length,
		nextFrame: () => frames.splice(0).forEach(callback => callback()),
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); }
	};
}

Deno.test('the panel lists the objects with their text or their kind', () =>
{
	using page = createController();

	const [, entries, empty] = page.view.calls.find(call => call[0] === 'renderList');
	assertEquals(entries.map(entry => entry.label), ['Image', 'Welcome', 'Rectangle']);
	assertEquals(entries[1].index, 1);
	assertEquals(empty, 'No objects');
	assertEquals(page.view.calls.at(-1), ['showGeometry', GEOMETRY, true]);
});

Deno.test('the toggle collapses the panel and gives the canvas the space', () =>
{
	using page = createController();

	fire(page.view.toggle, 'click');

	assertEquals(page.view.calls.at(-1), ['toggleCollapsed']);
	assertEquals(page.bus.events.map(([type]) => type), ['workspaceResized']);
});

Deno.test('a click on an entry selects its object and shows its properties', () =>
{
	using page = createController();

	fire(page.view.list, 'click', {index: 2});

	assertEquals(page.service.calls.at(-1), ['selectByIndex', 2]);
	assertEquals(page.bus.events.length, 1);
	assertEquals(page.bus.events[0][0], 'objectSelected');
	assertEquals(page.bus.events[0][1].target === OBJECTS[2], true);
});

Deno.test('a click beside the entries or on a gone object selects nothing', () =>
{
	using page = createController();

	fire(page.view.list, 'click');
	fire(page.view.list, 'click', {index: 9});

	assertEquals(page.service.calls.filter(call => call[0] === 'selectByIndex'), [['selectByIndex', 9]]);
	assertEquals(page.bus.events, []);
});

Deno.test('a field changes the object and shows the geometry again', () =>
{
	using page = createController();
	const setters = {left: 'setLeft', top: 'setTop', width: 'setWidth', height: 'setHeight', angle: 'setAngle'};

	for (const [name, setter] of Object.entries(setters))
	{
		page.view.fields[name].value = '42';
		fire(page.view.fields[name], 'change');
		assertEquals(page.service.calls.filter(call => call[0] === setter), [[setter, '42']], name);
	}

	assertEquals(page.view.calls.filter(call => call[0] === 'showGeometry').length, 6);
});

Deno.test('the changes of a frame build the list once', () =>
{
	using page = createController();

	for (const name of ['object:added', 'object:removed', 'object:modified', 'text:changed', 'selection:created',
		'selection:updated', 'selection:cleared', 'history:undo', 'history:redo'])
		page.wrapper.emit(name);
	page.bus.send('canvasUpdated');
	page.bus.send('objectsChanged');

	assertEquals([page.frames.length, page.renders()], [1, 1]);
	page.nextFrame();
	assertEquals(page.renders(), 2);

	page.wrapper.emit('object:added');
	page.nextFrame();
	assertEquals(page.renders(), 3);
});

Deno.test('while an object is moved only the fields follow it', () =>
{
	using page = createController();
	const before = page.view.calls.length;

	for (const name of ['object:moving', 'object:scaling', 'object:rotating', 'object:resizing'])
		page.wrapper.emit(name);

	assertEquals(page.view.calls.slice(before).map(call => call[0]), ['showGeometry', 'showGeometry', 'showGeometry', 'showGeometry']);
	assertEquals(page.frames, []);
});
