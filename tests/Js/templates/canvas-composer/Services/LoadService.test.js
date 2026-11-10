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
import {stub}                                               from "@std/testing/mock";
import {callNames, composerWaitOverlay, composerWrapper, DenoCustomEvent, FLASH_TEMPLATE, flashMessages} from "@support/ComposerServicesFakes.js";
import {createDom}                                          from "@support/dom.js";
import {busOfTest}                                          from "@support/MultizoneEvents.js";
import {replaceGlobals}                                     from "@support/globals.js";
import {LoadService}                                        from "@js/templates/canvas-composer/Services/LoadService.js";

class FakeTemplatesService
{
	calls  = [];
	answer = {success: true, content: ''};

	async loadTemplateContent(id)
	{
		this.calls.push(['loadTemplateContent', id]);
		return this.answer;
	}

	async loadPlaylistItemContent(id)
	{
		this.calls.push(['loadPlaylistItemContent', id]);
		return this.answer;
	}
}

class FakeFontCollector
{
	collected = [];
	preloads  = 0;

	collectUsedFontsFromSelection(object)
	{
		this.collected.push(object.fontFamily);
	}

	async preloadUsedFonts()
	{
		this.preloads++;
	}
}

function createService()
{
	const dom       = createDom(FLASH_TEMPLATE);
	// the bus is an EventTarget of Deno
	const globals   = replaceGlobals({CustomEvent: DenoCustomEvent});
	const bus       = busOfTest('canvasUpdated');
	const log       = stub(console, 'log');
	const wrapper   = composerWrapper();
	const templates = new FakeTemplatesService();
	const fonts     = new FakeFontCollector();
	const overlay   = composerWaitOverlay();

	return {
		document: dom.document, wrapper, templates, fonts, overlay, bus,
		service: new LoadService(wrapper, templates, fonts, overlay),
		[Symbol.dispose]: () => { log.restore(); bus[Symbol.dispose](); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function content(objects, viewport = {width: 1080, height: 1920, scale: 100})
{
	return JSON.stringify({objects, viewport});
}

/**
 * The JSON which fabric.js gets.
 */
function loaded(page)
{
	return JSON.parse(page.wrapper.calls.find(call => call[0] === 'load')[1]);
}

Deno.test('a template is loaded with its size, as start of the history and without changes', async () =>
{
	using page = createService();
	page.templates.answer = {success: true, content: content([{type: 'rect'}])};

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(page.templates.calls, [['loadTemplateContent', 7]]);
	assertEquals(loaded(page).objects, [{type: 'rect'}]);
	assertEquals(page.wrapper.calls.filter(call => ['setWidth', 'setHeight'].includes(call[0])), [['setWidth', 1080], ['setHeight', 1920]]);
	assertEquals(callNames(page.wrapper), ['load', 'setWidth', 'setHeight', 'historySaveAction', 'resetChange']);
	assertEquals(page.bus.events.map(event => event[0]), ['canvasUpdated']);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('the item of a playlist loads its own content', async () =>
{
	using page = createService();

	await page.service.loadFromPlaylistItemDataBase(12);

	assertEquals(page.templates.calls, [['loadPlaylistItemContent', 12]]);
});

Deno.test('an empty content is an empty template of 1920 x 1080', async () =>
{
	using page = createService();

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(loaded(page), {objects: [], viewport: {width: 1920, height: 1080, scale: 100}});
});

Deno.test('the fonts of all texts, also in groups, are loaded before the content', async () =>
{
	using page = createService();
	page.templates.answer = {success: true, content: content([
		{type: 'Textbox', fontFamily: 'Roboto'},
		{type: 'group', objects: [{type: 'textbox', fontFamily: 'Lato'}, {type: 'group', objects: [{type: 'Textbox', fontFamily: 'Oswald'}]}]},
		{type: 'image', fontFamily: 'none'},
		{type: 'group'}
	])};

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(page.fonts.collected, ['Roboto', 'Lato', 'Oswald']);
	assertEquals(page.fonts.preloads, 1);
});

Deno.test('a text scaled by an old version gets the size in pixels', async () =>
{
	using page = createService();
	page.templates.answer = {success: true, content: content([
		{type: 'textbox', fontSize: 20, width: 100, scaleX: 2, scaleY: 2},
		{type: 'textbox', fontSize: 20, width: 100, scaleX: 2, scaleY: 3},
		{type: 'textbox', fontSize: 20, width: 100, scaleX: 1, scaleY: 1},
		{type: 'textbox', fontSize: 20, width: 100}
	])};

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(loaded(page).objects.map(text => [text.fontSize, text.width, text.scaleX, text.scaleY]), [
		[40, 200, 1, 1], [20, 100, 2, 3], [20, 100, 1, 1], [20, 100, undefined, undefined]
	]);
});

Deno.test('the reset empties the canvas and loads the template again', async () =>
{
	using page = createService();

	await page.service.resetFromTemplateDataBase(7);

	assertEquals(callNames(page.wrapper)[0], 'clear');
	assertEquals(page.templates.calls, [['loadTemplateContent', 7]]);
});

Deno.test('a refused content shows the error, loads nothing and stops the overlay', async () =>
{
	using page = createService();
	page.templates.answer = {success: false, error_message: 'No access'};

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(flashMessages(page.document), [['error', 'No access']]);
	assertEquals(page.wrapper.calls, []);
	assertEquals(page.bus.events, []);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('a broken content shows the error and stops the overlay', async () =>
{
	using page = createService();
	page.templates.answer = {success: true, content: '{broken'};

	await page.service.loadFromTemplateDataBase(7);

	assertEquals(flashMessages(page.document).length, 1);
	assertEquals(page.wrapper.calls, []);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});
