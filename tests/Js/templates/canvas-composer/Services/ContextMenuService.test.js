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

import {assertEquals}                                       from "@std/assert";
import {composerFabric, composerWrapper, callNames, FakeFabricObject} from "@support/ComposerServicesFakes.js";
import {busOfTest}                                          from "@support/MultizoneEvents.js";
import {replaceGlobals}                                     from "@support/globals.js";
import {ContextMenuService}                                 from "@js/templates/canvas-composer/Services/ContextMenuService.js";

const PROPERTIES = ["mediaId", "fileName", "placeholder", "lockMovementX", "lockMovementY", "lockRotation", "lockScalingX", "lockScalingY"];
const LOCKS      = ["lockMovementX", "lockMovementY", "lockSkewingX", "lockSkewingY", "lockRotation", "lockScalingX", "lockScalingY"];

/**
 * A service with the given selected object; fabric is the fake of this test.
 */
function createService(active = null, properties = {fabricCanvas: {id: 'canvas'}}, fabric = composerFabric())
{
	const globals = replaceGlobals({fabric});
	const bus     = busOfTest('objectsChanged');
	const wrapper = composerWrapper({getActiveObject: () => active}, properties);

	return {
		fabric, wrapper, bus, service: new ContextMenuService(wrapper),
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); }
	};
}

function added(page)
{
	return page.wrapper.calls.filter(call => call[0] === 'add').map(call => call[1]);
}

Deno.test('nothing selected: copy, paste, duplicate and remove do nothing', async () =>
{
	using page = createService(null);

	await page.service.copy();
	await page.service.paste();
	await page.service.duplicate();
	page.service.remove();

	assertEquals(callNames(page.wrapper), ['getActiveObject', 'getActiveObject', 'getActiveObject']);
	assertEquals(page.bus.events, []);
});

Deno.test('a copy is cloned with the properties of the template', async () =>
{
	const object = new FakeFabricObject({left: 100, top: 50, mediaId: 'abc'});
	using page   = createService(object);

	await page.service.copy();
	await page.service.paste();

	const [pasted] = added(page);
	assertEquals(pasted.clonedWith, PROPERTIES);
	assertEquals(pasted.mediaId, 'abc');
});

Deno.test('every paste lies a little further than the one before', async () =>
{
	const object = new FakeFabricObject({left: 100, top: 50});
	using page   = createService(object);
	await page.service.copy();

	await page.service.paste();
	await page.service.paste();
	await page.service.paste();

	assertEquals(added(page).map(copy => [copy.left, copy.top, copy.evented]), [[120, 70, true], [140, 90, true], [160, 110, true]]);
	assertEquals(page.bus.events.length, 3);
});

Deno.test('a new copy starts again with the first distance', async () =>
{
	const object = new FakeFabricObject({left: 100, top: 50});
	using page   = createService(object);
	await page.service.copy();
	await page.service.paste();
	await page.service.paste();

	await page.service.copy();
	await page.service.paste();

	assertEquals(added(page).map(copy => copy.left), [120, 140, 120]);
});

Deno.test('a paste becomes the selection and is one step of the history', async () =>
{
	const object = new FakeFabricObject({left: 0, top: 0});
	using page   = createService(object);
	await page.service.copy();
	page.wrapper.calls.length = 0;

	await page.service.paste();

	assertEquals(callNames(page.wrapper), ['discardActiveObject', 'add', 'setActiveObject', 'historySaveAction', 'renderAll']);
	const [pasted] = added(page);
	assertEquals(page.wrapper.calls[2][1] === pasted, true);
	assertEquals(page.bus.events.map(event => event[0]), ['objectsChanged']);
});

Deno.test('a pasted selection puts its objects on the canvas one by one', async () =>
{
	const fabric    = composerFabric();
	const selection = new fabric.ActiveSelection({left: 10, top: 10});
	selection._objects = [new FakeFabricObject({name: 'a'}), new FakeFabricObject({name: 'b'})];
	using page = createService(selection, {fabricCanvas: {id: 'canvas'}}, fabric);

	await page.service.copy();
	await page.service.paste();

	assertEquals(added(page).map(object => object.name), ['a', 'b']);
	const active = page.wrapper.calls.find(call => call[0] === 'setActiveObject')[1];
	assertEquals(active instanceof fabric.ActiveSelection, true);
	assertEquals(active.canvas, {id: 'canvas'});
	assertEquals(active.calls, [['setCoords']]);
});

Deno.test('duplicate copies and pastes the selected object', async () =>
{
	const object = new FakeFabricObject({left: 0, top: 0});
	using page   = createService(object);

	await page.service.duplicate();

	assertEquals(added(page).map(copy => [copy.left, copy.top]), [[20, 20]]);
});

Deno.test('the selected object is removed', () =>
{
	const object = new FakeFabricObject();
	using page   = createService(object);

	page.service.remove();

	assertEquals(page.wrapper.calls.find(call => call[0] === 'remove')[1] === object, true);
	assertEquals(callNames(page.wrapper).slice(-2), ['historySaveAction', 'renderAll']);
	assertEquals(page.bus.events.length, 1);
});

Deno.test('the lock switches all locks of the object together', () =>
{
	const object = new FakeFabricObject({lockMovementX: false, lockMovementY: false});
	using page   = createService(object);

	assertEquals(page.service.isLocked(), false);
	page.service.toggleLockedStatus();
	assertEquals(LOCKS.map(lock => object[lock]), LOCKS.map(() => true));
	assertEquals(page.service.isLocked(), true);

	page.service.toggleLockedStatus();
	assertEquals(LOCKS.map(lock => object[lock]), LOCKS.map(() => false));
	assertEquals(page.bus.events.length, 2);
});

Deno.test('an object locked only in one direction counts as not locked', () =>
{
	using page = createService(new FakeFabricObject({lockMovementX: true, lockMovementY: false}));

	assertEquals(page.service.isLocked(), false);
});

Deno.test('the selectable switch also switches the events of the object', () =>
{
	const object = new FakeFabricObject({selectable: true, evented: true});
	using page   = createService(object);

	page.service.toggleSelectableStatus();
	assertEquals([object.selectable, object.evented, page.service.isSelectable()], [false, false, false]);

	page.service.toggleSelectableStatus();
	assertEquals([object.selectable, object.evented, page.service.isSelectable()], [true, true, true]);
});

Deno.test('without a selection nothing is locked or selectable and nothing changes', () =>
{
	using page = createService(null);

	assertEquals([page.service.isLocked(), page.service.isSelectable()], [false, false]);
	page.service.toggleLockedStatus();
	page.service.toggleSelectableStatus();

	assertEquals(page.bus.events, []);
});

Deno.test('the order of the selected object is changed on the canvas', () =>
{
	const object = new FakeFabricObject();
	using page   = createService(object);

	page.service.sendToBack();
	page.service.sendBackwards();
	page.service.bringForward();
	page.service.bringToFront();

	const moves = page.wrapper.calls.filter(call => ['sendToBack', 'sendBackwards', 'bringForward', 'bringToFront'].includes(call[0]));
	assertEquals(moves.map(call => [call[0], call[1] === object]),
		[['sendToBack', true], ['sendBackwards', true], ['bringForward', true], ['bringToFront', true]]);
	assertEquals(page.bus.events.length, 4);
});
