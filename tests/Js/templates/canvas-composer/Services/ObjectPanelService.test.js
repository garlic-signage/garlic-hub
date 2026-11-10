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

import {assertEquals}                                     from "@std/assert";
import {callNames, composerWrapper, FakeFabricObject}     from "@support/ComposerServicesFakes.js";
import {ObjectPanelService}                               from "@js/templates/canvas-composer/Services/ObjectPanelService.js";

/**
 * The objects of the canvas from the back to the front; active is the selection.
 */
function createService(objects = [], active = null)
{
	const wrapper = composerWrapper({
		getObjects: () => objects,
		getActiveObjects: () => active === null ? [] : [active],
		getActiveObject: () => active ?? undefined
	});

	return {wrapper, service: new ObjectPanelService(wrapper)};
}

function shape(options = {})
{
	return new FakeFabricObject({type: 'rect', left: 10.4, top: 20.6, width: 100, height: 50, scaleX: 1.5, scaleY: 2, angle: 44.6, ...options});
}

function updates(page)
{
	return callNames(page.wrapper).filter(name => ['fireObjectModified', 'renderAll'].includes(name));
}

Deno.test('the list names the objects from the front to the back with the index of the canvas', () =>
{
	const image = shape({type: 'image', fileName: 'lobby.jpg'});
	const text  = shape({type: 'Textbox', text: '  Welcome  \nto the lobby'});
	const group = shape({type: 'group'});
	const rect  = shape({lockMovementX: true, lockMovementY: true, selectable: false});
	const page  = createService([image, text, group, rect], text);

	assertEquals(page.service.listObjects(), [
		{index: 3, kind: 'shape', shape: 'rect', text: '', isLocked: true, isSelectable: false, isActive: false},
		{index: 2, kind: 'group', shape: 'group', text: '', isLocked: false, isSelectable: true, isActive: false},
		{index: 1, kind: 'text', shape: 'textbox', text: 'Welcome', isLocked: false, isSelectable: true, isActive: true},
		{index: 0, kind: 'image', shape: 'image', text: 'lobby.jpg', isLocked: false, isSelectable: true, isActive: false}
	]);
});

Deno.test('a long label is cut after 30 characters', () =>
{
	const page = createService([shape({type: 'i-text', text: 'a'.repeat(31)}), shape({type: 'text', text: 'b'.repeat(30)})]);

	assertEquals(page.service.listObjects().map(entry => entry.text), ['b'.repeat(30), 'a'.repeat(30) + '…']);
});

Deno.test('an object locked in one direction only is not locked in the list', () =>
{
	const page = createService([shape({lockMovementX: true})]);

	assertEquals(page.service.listObjects()[0].isLocked, false);
});

Deno.test('an object of the list is selected by its index', () =>
{
	const objects = [shape(), shape()];
	const page    = createService(objects);

	const selected = page.service.selectByIndex(1);

	assertEquals(selected === objects[1], true);
	assertEquals(callNames(page.wrapper), ['getObjects', 'setActiveObject', 'renderAll']);
	assertEquals(page.wrapper.calls[1][1] === objects[1], true);
});

Deno.test('a missing or not selectable object is not selected', () =>
{
	const page = createService([shape({selectable: false})]);

	assertEquals([page.service.selectByIndex(0), page.service.selectByIndex(5)], [null, null]);
	assertEquals(callNames(page.wrapper), ['getObjects', 'getObjects']);
});

Deno.test('the geometry is in whole pixels of the template, scaled', () =>
{
	const page = createService([], shape());

	assertEquals(page.service.getGeometry(), {left: 10, top: 21, width: 150, height: 100, angle: 45});
});

Deno.test('without a single selected object there is no geometry and nothing is editable', () =>
{
	for (const active of [null, shape({type: 'activeSelection'})])
	{
		const page = createService([], active);

		assertEquals(page.service.getGeometry(), null);
		assertEquals(page.service.getEditable(), {left: false, top: false, width: false, height: false, angle: false});
	}
});

Deno.test('the locks of an object close its fields', () =>
{
	const free   = createService([], shape());
	const locked = createService([], shape({lockMovementX: true, lockMovementY: true, lockScalingX: true, lockScalingY: true, lockRotation: true}));

	assertEquals(free.service.getEditable(), {left: true, top: true, width: true, height: true, angle: true});
	assertEquals(locked.service.getEditable(), {left: false, top: false, width: false, height: false, angle: false});
});

Deno.test('the height of a text follows its lines', () =>
{
	const page = createService([], shape({type: 'textbox'}));

	assertEquals(page.service.getEditable().height, false);
});

Deno.test('position and rotation are set and saved', () =>
{
	const object = shape();
	const page   = createService([], object);

	page.service.setLeft('30');
	page.service.setTop(40);
	page.service.setAngle('370');

	assertEquals([object.left, object.top, object.angle], [30, 40, 10]);
	assertEquals(object.calls.length, 3);
	assertEquals(updates(page), ['fireObjectModified', 'renderAll', 'fireObjectModified', 'renderAll', 'fireObjectModified', 'renderAll']);
});

Deno.test('the width scales a shape and widens the box of a text', () =>
{
	const rect = shape();
	createService([], rect).service.setWidth('300');
	assertEquals([rect.scaleX, rect.width], [3, 100]);

	const text = shape({type: 'textbox', scaleX: 2});
	createService([], text).service.setWidth('300');
	assertEquals([text.scaleX, text.width], [2, 150]);
});

Deno.test('the height scales the object, the size is at least one pixel', () =>
{
	const rect = shape();
	const page = createService([], rect);

	page.service.setHeight('200');
	assertEquals(rect.scaleY, 4);

	page.service.setHeight('0');
	page.service.setWidth('-5');
	assertEquals([rect.scaleY, rect.scaleX], [0.02, 0.01]);
});

Deno.test('empty, not numeric or locked values change nothing', () =>
{
	const object = shape({lockMovementY: true});
	const page   = createService([], object);

	page.service.setLeft('');
	page.service.setLeft('abc');
	page.service.setLeft('Infinity');
	page.service.setTop('50');

	assertEquals([object.left, object.top], [10.4, 20.6]);
	assertEquals(updates(page), []);
});

Deno.test('without a selection nothing is set', () =>
{
	const page = createService([], null);

	page.service.setLeft('30');
	page.service.setAngle('10');

	assertEquals(updates(page), []);
});
