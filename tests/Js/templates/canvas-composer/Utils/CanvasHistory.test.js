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

import {assertEquals}  from "@std/assert";
import {CanvasHistory} from "@js/templates/canvas-composer/Utils/CanvasHistory.js";

/**
 * The canvas of fabric.js with a list of objects as content: toObject() gives it with the asked properties,
 * loadFromJSON() takes it back. on() and fire() record the events.
 */
class FakeCanvas
{
	objects    = [];
	handlers   = {};
	fired      = [];
	renders    = 0;
	asked      = [];
	/** called while loadFromJSON() runs, e.g. to fire object:modified meanwhile */
	whileLoading = null;

	on(name, handler)
	{
		(this.handlers[name] ??= []).push(handler);
	}

	fire(name)
	{
		this.fired.push(name);
		for (const handler of this.handlers[name] ?? [])
			handler();
	}

	toObject(properties)
	{
		this.asked.push(properties);
		return {objects: [...this.objects]};
	}

	async loadFromJSON(state)
	{
		this.whileLoading?.();
		this.objects = JSON.parse(state).objects;
	}

	renderAll()
	{
		this.renders++;
	}

	/** a change of the user: fabric.js fires object:modified */
	change(object)
	{
		this.objects.push(object);
		this.fire('object:modified');
	}
}

function createHistory()
{
	const canvas  = new FakeCanvas();
	const history = new CanvasHistory(canvas, ['mediaId', 'fileName']);
	return {canvas, history};
}

Deno.test('the state is the content with the properties of the composer', () =>
{
	const {canvas} = createHistory();

	assertEquals(canvas.asked, [['mediaId', 'fileName']]);
});

Deno.test('every change of an object becomes a step, the first one cannot be undone', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');

	// only one step: undo() needs two (see the findings), the state before the first change stays out of reach
	await history.undo();
	assertEquals(canvas.objects, ['a']);

	canvas.change('b');
	await history.undo();
	assertEquals(canvas.objects, ['a']);
	assertEquals(canvas.fired.at(-1), 'history:undo');
	assertEquals(canvas.renders, 1);
});

Deno.test('a step back can be done again', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');
	canvas.change('b');
	await history.undo();

	await history.redo();

	assertEquals(canvas.objects, ['a', 'b']);
	assertEquals(canvas.fired.at(-1), 'history:redo');
});

Deno.test('without a step back there is nothing to do again', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');

	await history.redo();

	assertEquals(canvas.objects, ['a']);
	assertEquals(canvas.fired, ['object:modified']);
});

Deno.test('a new change after a step back keeps the steps forward', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');
	canvas.change('b');
	await history.undo();
	canvas.change('c');

	// the step forward of before is still there (see the findings): redo() brings back a state without "c"
	await history.redo();
	assertEquals(canvas.objects, ['a', 'b']);
});

Deno.test('at most 20 steps are kept', async () =>
{
	const {canvas, history} = createHistory();
	for (let i = 1; i <= 25; i++)
		canvas.change(i);

	for (let i = 0; i < 30; i++)
		await history.undo();

	// the oldest kept state has 5 objects, undo() leaves the last step: 6
	assertEquals(canvas.objects.length, 6);
});

Deno.test('a reset forgets all steps', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');
	canvas.change('b');

	history.reset();
	await history.undo();

	assertEquals(canvas.objects, ['a', 'b']);
});

Deno.test('while paused the changes make no steps', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');

	history.pause();
	canvas.change('b');
	canvas.change('c');
	history.resume();
	canvas.change('d');

	await history.undo();
	// the paused changes are in the state of the step after them
	assertEquals(canvas.objects, ['a']);
});

Deno.test('a change while a step is loaded makes no step', async () =>
{
	const {canvas, history} = createHistory();
	canvas.change('a');
	canvas.change('b');
	canvas.change('c');
	canvas.whileLoading = () => history.save();

	await history.undo();
	canvas.whileLoading = null;
	await history.undo();

	// a step made while loading would be the one before "c" again
	assertEquals(canvas.objects, ['a']);
});
