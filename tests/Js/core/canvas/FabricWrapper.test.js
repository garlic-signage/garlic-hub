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
import {assertEquals, assertStrictEquals} from "@std/assert";
import {replaceGlobals}                   from "@support/globals.js";
import {ComposerEventBus}                 from "@js/core/canvas/ComposerEventBus.js";
import {FabricWrapper}                    from "@js/core/canvas/FabricWrapper.js";

/**
 * The canvas of fabric.js: its events are fired by the test, the other methods record their calls.
 */
class FakeCanvas
{
	handlers          = {};
	calls             = [];
	viewportTransform = [2, 0, 0, 0.5, 10, 20];

	on(name, handler)
	{
		(this.handlers[name] ??= []).push(handler);
	}

	fire(name, options = {})
	{
		this.calls.push(['fire', name, options]);
		for (const handler of this.handlers[name] ?? [])
			handler(options);
	}

	trigger(name, options = {})
	{
		for (const handler of this.handlers[name] ?? [])
			handler(options);
	}
}

for (const method of ['setDimensions', 'add', 'remove', 'renderAll', 'setActiveObject', 'discardActiveObject', 'sendObjectToBack',
	'sendObjectBackwards', 'bringObjectForward', 'bringObjectToFront', 'setZoom', 'zoomToPoint', 'clear'])
	FakeCanvas.prototype[method] = function (...args) { this.calls.push([method, ...args]); return 'result of ' + method; };
for (const [method, value] of [['getWidth', 800], ['getHeight', 600], ['getObjects', ['a']], ['getActiveObject', 'a'], ['getActiveObjects', ['a', 'b']], ['getZoom', 1.5], ['getContext', 'context']])
	FakeCanvas.prototype[method] = () => value;

/**
 * The events of the bus while the wrapper works, with their detail.
 */
function listen()
{
	const events = [];
	const names  = ['setChanged', 'resetChanged', 'snapToGrid', 'drawGrid', 'mouseLeftUp', 'mouseRightUp'];
	const record = (event) => events.push([event.type, event.detail ?? null]);
	names.forEach(name => ComposerEventBus.addEventListener(name, record));

	return {events, [Symbol.dispose]: () => names.forEach(name => ComposerEventBus.removeEventListener(name, record))};
}

Deno.test('every change of an object is a change of the composer', async (t) =>
{
	for (const name of ['object:modified', 'text:changed', 'object:added', 'object:removed'])
	{
		await t.step(name, () =>
		{
			using bus   = listen();
			const canvas  = new FakeCanvas();
			const wrapper = new FabricWrapper(canvas);

			canvas.trigger(name);

			assertEquals(wrapper.hasChanged, true);
			assertEquals(bus.events, [['setChanged', null]]);
		});
	}
});

Deno.test('a change marked by hand tells nobody, a reset tells the composer', () =>
{
	using bus     = listen();
	const wrapper = new FabricWrapper(new FakeCanvas());

	wrapper.markChanged();
	assertEquals([wrapper.hasChanged, bus.events], [true, []]);

	wrapper.resetChange();
	assertEquals([wrapper.hasChanged, bus.events], [false, [['resetChanged', null]]]);
});

Deno.test('while an object moves the grid snaps and is drawn after each render, until the left button is up', () =>
{
	using bus    = listen();
	const canvas = new FakeCanvas();
	new FabricWrapper(canvas);
	const moving = {target: 'object'};

	canvas.trigger('after:render');
	canvas.trigger('object:moving', moving);
	canvas.trigger('after:render');
	canvas.trigger('mouse:up', {e: {button: 0}});
	canvas.trigger('after:render');

	assertEquals(bus.events, [['snapToGrid', moving], ['drawGrid', null], ['mouseLeftUp', {e: {button: 0}}]]);
});

Deno.test('the right button is reported as such and does not end the moving, an event without button is the left one', () =>
{
	using bus    = listen();
	const canvas = new FakeCanvas();
	new FabricWrapper(canvas);

	canvas.trigger('object:moving', {});
	canvas.trigger('mouse:up', {e: {button: 2}});
	canvas.trigger('after:render');
	canvas.trigger('mouse:up', {e: {button: 1}});
	canvas.trigger('mouse:up', {});

	assertEquals(bus.events.map(([name]) => name), ['snapToGrid', 'mouseRightUp', 'drawGrid', 'mouseLeftUp']);
});

Deno.test('the box of an object on the element has the zoom and the position of the view', () =>
{
	const wrapper = new FabricWrapper(new FakeCanvas());

	const rect = wrapper.getViewportBoundingRect({getBoundingRect: () => ({left: 100, top: 40, width: 50, height: 80})});

	assertEquals(rect, {left: 210, top: 40, width: 100, height: 40});
});

Deno.test('the other calls go to the canvas of fabric.js', () =>
{
	using _fabric = replaceGlobals({fabric: {Point: class { constructor(x, y) { this.x = x; this.y = y; } }}});
	const canvas  = new FakeCanvas();
	const wrapper = new FabricWrapper(canvas);

	wrapper.setWidth(640);
	wrapper.setHeight(480);
	wrapper.add('a');
	wrapper.remove('a');
	wrapper.renderAll();
	wrapper.setActiveObject('a');
	assertEquals(wrapper.discardActiveObject(), 'result of discardActiveObject');
	wrapper.sendToBack('a');
	wrapper.sendBackwards('a');
	wrapper.bringForward('a');
	wrapper.bringToFront('a');
	wrapper.setZoom(2);
	wrapper.zoomToPoint(10, 20, 3);
	wrapper.clear();
	wrapper.fireObjectModified('a');
	wrapper.fireUpdateSelection('b');

	assertEquals(canvas.calls.map(([method, ...args]) => [method, ...args.map(arg => arg?.constructor?.name === 'Point' ? [arg.x, arg.y] : arg)]), [
		['setDimensions', {width: 640}], ['setDimensions', {height: 480}], ['add', 'a'], ['remove', 'a'], ['renderAll'],
		['setActiveObject', 'a'], ['discardActiveObject'], ['sendObjectToBack', 'a'], ['sendObjectBackwards', 'a'],
		['bringObjectForward', 'a'], ['bringObjectToFront', 'a'], ['setZoom', 2], ['zoomToPoint', [10, 20], 3], ['clear'],
		['fire', 'object:modified', {target: 'a'}], ['fire', 'selection:updated', {target: 'b'}]
	]);
	assertEquals([wrapper.getWidth(), wrapper.getHeight(), wrapper.getObjects(), wrapper.getActiveObject(), wrapper.getActiveObjects(), wrapper.getZoom(), wrapper.getContext()],
		[800, 600, ['a'], 'a', ['a', 'b'], 1.5, 'context']);
	assertStrictEquals(wrapper.getViewportTransform(), canvas.viewportTransform);
	assertStrictEquals(wrapper.fabricCanvas, canvas);
});

Deno.test('other events of fabric.js are passed to the canvas', () =>
{
	const canvas  = new FakeCanvas();
	const wrapper = new FabricWrapper(canvas);
	const handler = () => {};

	wrapper.on('selection:created', handler);

	assertStrictEquals(canvas.handlers['selection:created'][0], handler);
});
