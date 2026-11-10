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

import {assertEquals}            from "@std/assert";
import {FakeTime}                from "@std/testing/time";
import {createDom}               from "@support/dom.js";
import {replaceGlobals}          from "@support/globals.js";
import {FakeFabricWrapper, fire} from "@support/ComposerControllerFakes.js";
import {busOfTest}               from "@support/MultizoneEvents.js";
import {DesignCheckController}   from "@js/templates/canvas-composer/Controller/DesignCheckController.js";

// the CustomEvent of Deno for the bus: createDom() sets the one of linkedom
const DenoCustomEvent = globalThis.CustomEvent;

// the design check of templates/templates/canvas.mustache
const PAGE = `
	<button id="designCheckButton"><span id="designCheckCount" class="design-check-count" hidden></span></button>
	<p id="designCheckOk">All fine</p>
	<ul id="designCheckHints"></ul>`;

const LANG = {
	too_small: 'The text {name} is smaller than {size} px',
	too_long:  'The template shows for {seconds} s',
};

/**
 * The canvas with after:render, the wrapper gives the place of an object on the screen.
 */
class FakeCheckWrapper extends FakeFabricWrapper
{
	canvasHandlers = {};
	fabricCanvas   = {
		contextContainer: {name: 'composer'},
		on: (name, handler) => (this.canvasHandlers[name] ??= []).push(handler)
	};

	getViewportBoundingRect(object)
	{
		return object.rect;
	}

	render(ctx)
	{
		for (const handler of this.canvasHandlers['after:render'] ?? [])
			handler({ctx});
	}
}

/**
 * Records what is drawn.
 */
class FakeContext
{
	drawn = [];

	save()    { this.drawn.push('save'); }
	restore() { this.drawn.push('restore'); }

	strokeText(text, x, y)
	{
		this.drawn.push(['strokeText', text, x, y]);
	}

	fillText(text, x, y)
	{
		this.drawn.push(['fillText', text, x, y, this.fillStyle]);
	}
}

function createController()
{
	const time     = new FakeTime();
	const dom      = createDom(PAGE);
	const globals  = replaceGlobals({CustomEvent: DenoCustomEvent});
	const bus      = busOfTest('objectSelected');
	const wrapper  = new FakeCheckWrapper();
	const service  = {hints: [], runs: 0, check() { this.runs++; return this.hints; }};
	const context  = {getLangByKey: (key) => LANG[key]};
	new DesignCheckController(service, wrapper, context).init();

	return {
		document: dom.document, time, bus, wrapper, service,
		count:   dom.document.getElementById('designCheckCount'),
		ok:      dom.document.getElementById('designCheckOk'),
		entries: () => [...dom.document.querySelectorAll('#designCheckHints li')],
		[Symbol.dispose]: () =>
		{
			bus[Symbol.dispose]();
			globals[Symbol.dispose]();
			dom[Symbol.dispose]();
			time[Symbol.dispose]();
		}
	};
}

function check(page)
{
	page.wrapper.emit('object:modified');
	page.time.tick(DesignCheckController.DELAY_MS);
}

Deno.test('the check runs half a second after the last change', () =>
{
	using page = createController();

	for (const name of ['object:added', 'object:removed', 'object:modified', 'text:changed'])
	{
		page.wrapper.emit(name);
		page.time.tick(DesignCheckController.DELAY_MS - 1);
	}
	assertEquals(page.service.runs, 0);

	page.time.tick(1);
	assertEquals(page.service.runs, 1);
});

Deno.test('the hints are listed with their values and counted', () =>
{
	using page = createController();
	const text = {name: 'Title', rect: {left: 0, top: 0, width: 10, height: 10}};
	page.service.hints = [
		{key: 'too_small', values: {name: 'Title', size: 24}, object: text},
		{key: 'too_long', values: {seconds: 12}, object: null},
	];

	check(page);

	assertEquals(page.entries().map(entry => entry.textContent), ['The text Title is smaller than 24 px', 'The template shows for 12 s']);
	assertEquals(page.entries().map(entry => entry.classList.contains('design-check-element')), [true, false]);
	assertEquals([page.count.textContent, page.count.hidden, page.ok.hidden], ['2', false, true]);
	assertEquals(page.wrapper.calls, [['renderAll']]);
});

Deno.test('without hints the check says all is fine', () =>
{
	using page = createController();
	page.service.hints = [{key: 'too_long', values: {seconds: 12}, object: null}];
	check(page);

	page.service.hints = [];
	check(page);

	assertEquals(page.entries(), []);
	assertEquals([page.count.textContent, page.count.hidden, page.ok.hidden], ['0', true, false]);
});

Deno.test('a hint of an element selects it and shows its properties', () =>
{
	using page = createController();
	const text = {name: 'Title', rect: {left: 0, top: 0, width: 10, height: 10}};
	page.service.hints = [{key: 'too_small', values: {name: 'Title', size: 24}, object: text}];
	check(page);

	fire(page.entries()[0], 'click');

	assertEquals(page.wrapper.calls.slice(1), [['setActiveObject', text], ['renderAll']]);
	assertEquals(page.bus.events.length, 1);
	assertEquals(page.bus.events[0][1].target === text, true);
});

Deno.test('the elements of the hints get a mark beside their upper right corner', () =>
{
	using page = createController();
	const text = {rect: {left: 100, top: 50, width: 200, height: 40}};
	const gone = {rect: {left: 0, top: 0, width: 10, height: 10}};
	page.wrapper.objects = [text];
	page.service.hints = [
		{key: 'too_small', values: {name: 'a', size: 1}, object: text},
		{key: 'too_small', values: {name: 'b', size: 2}, object: text},
		{key: 'too_small', values: {name: 'c', size: 3}, object: gone},
	];
	check(page);

	// not on another canvas, e.g. a copy which the check renders
	const copy = new FakeContext();
	page.wrapper.render(copy);
	assertEquals(copy.drawn, []);

	const ctx = new FakeContext();
	page.wrapper.fabricCanvas.contextContainer = ctx;
	page.wrapper.render(ctx);

	// once per element, the removed one not
	assertEquals(ctx.drawn, [
		'save', ['strokeText', DesignCheckController.MARK, 304, 50], ['fillText', DesignCheckController.MARK, 304, 50, '#d97706'], 'restore'
	]);
});
