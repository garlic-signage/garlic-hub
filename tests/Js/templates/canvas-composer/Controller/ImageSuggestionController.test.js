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

import {assertEquals}                  from "@std/assert";
import {assertSameElement, createDom}  from "@support/dom.js";
import {FakeFabricWrapper, fire, tick} from "@support/ComposerControllerFakes.js";
import {busOfTest}                     from "@support/MultizoneEvents.js";
import {ImageSuggestionController}     from "@js/templates/canvas-composer/Controller/ImageSuggestionController.js";

// the dialog of templates/templates/canvas.mustache, only there with an AI of the user
const DIALOG = `
	<dialog id="imageSuggestionDialog">
		<header><button type="button" class="close-button bi bi-x" aria-label="Close"></button></header>
		<ul class="image-suggestions"></ul>
	</dialog>`;

const FLASH = '<template id="flashMessageTemplate"><div class="message-bar"><span></span><button class="close-button"></button></div></template>';

const IMAGES = [
	{media_id: 'm1', filename: 'Beach', file_name: 'f00d.jpg', original: '/original/m1', thumbnail: '/thumb/m1'},
	{media_id: 'm2', filename: 'Lobby', file_name: 'beef.png', original: '/original/m2', thumbnail: '/thumb/m2'},
];

class FakeTemplatesService
{
	texts  = [];
	answer = {success: true, images: IMAGES};

	async suggestImages(texts)
	{
		this.texts.push(texts);
		if (this.answer instanceof Error)
			throw this.answer;
		return this.answer;
	}
}

class FakeInsertService
{
	calls = [];
	error = null;

	async replaceImage(...args)
	{
		this.calls.push(args);
		if (this.error !== null)
			throw this.error;
	}
}

function createController(body = DIALOG + FLASH)
{
	const dom       = createDom(body);
	const bus       = busOfTest();
	const wrapper   = new FakeFabricWrapper();
	const templates = new FakeTemplatesService();
	const insert    = new FakeInsertService();
	const overlay   = {calls: [], start() { this.calls.push('start'); }, stop() { this.calls.push('stop'); }};
	const image     = {type: 'image'};
	wrapper.objects      = [image];
	wrapper.activeObject = image;
	new ImageSuggestionController(wrapper, templates, insert, overlay).init();

	return {
		document: dom.document, bus, wrapper, templates, insert, overlay, image,
		dialog:   dom.document.getElementById('imageSuggestionDialog'),
		buttons:  () => [...dom.document.querySelectorAll('.image-suggestions button')],
		errors:   () => [...dom.document.querySelectorAll('.message-bar span')].map(span => span.textContent),
		suggest:  async () => { bus.send('suggestImageForReplace'); await tick(); await tick(); },
		[Symbol.dispose]: () => { bus[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('without an AI there is no dialog and nothing is suggested', async () =>
{
	using page = createController(FLASH);

	await page.suggest();

	assertEquals(page.templates.texts, []);
});

Deno.test('only an image gets suggestions', async () =>
{
	using page = createController();

	for (const active of [null, {type: 'rect'}])
	{
		page.wrapper.activeObject = active;
		await page.suggest();
	}

	assertEquals(page.templates.texts, []);
	assertEquals(page.overlay.calls, []);
});

Deno.test('the AI gets the visible texts of the template from top to bottom', async () =>
{
	using page = createController();
	page.wrapper.objects.push(
		{type: 'textbox', text: ' Opening hours ', top: 300},
		{type: 'i-text', text: 'Welcome', top: 20},
		{type: 'text', text: 'hidden', top: 50, visible: false},
		{type: 'textbox', text: '   ', top: 60},
		{type: 'rect', top: 0},
	);

	await page.suggest();

	assertEquals(page.templates.texts, ['Welcome\nOpening hours']);
});

Deno.test('the suggested pictures are shown in the dialog while the overlay waits for the AI', async () =>
{
	using page = createController();

	await page.suggest();

	assertEquals(page.overlay.calls, ['start', 'stop']);
	assertEquals(page.dialog.hasAttribute('open'), true);
	assertEquals(page.buttons().map(button => [button.title, button.querySelector('img').getAttribute('src'),
		button.querySelector('img').getAttribute('alt')]), [['Beach', '/thumb/m1', 'Beach'], ['Lobby', '/thumb/m2', 'Lobby']]);
});

Deno.test('a refused or failed suggestion shows the error and no dialog', async () =>
{
	for (const answer of [{success: false, error_message: 'No AI'}, new Error('Timeout')])
	{
		using page = createController();
		page.templates.answer = answer;

		await page.suggest();

		assertEquals(page.errors(), [answer.error_message ?? answer.message]);
		assertEquals(page.overlay.calls, ['start', 'stop']);
		assertEquals(page.dialog.hasAttribute('open'), false);
	}
});

Deno.test('a chosen picture replaces the image, the dialog closes', async () =>
{
	using page = createController();
	await page.suggest();
	page.wrapper.activeObject = null;

	fire(page.buttons()[1], 'click');
	await tick();

	assertEquals(page.dialog.hasAttribute('open'), false);
	assertEquals(page.wrapper.calls, [['setActiveObject', page.image]]);
	assertEquals(page.insert.calls, [['m2', '/original/m2', 'beef.png']]);
});

Deno.test('an image removed meanwhile is not replaced', async () =>
{
	using page = createController();
	await page.suggest();
	page.wrapper.objects = [];

	fire(page.buttons()[0], 'click');
	await tick();

	assertEquals(page.insert.calls, []);
	assertEquals(page.dialog.hasAttribute('open'), false);
});

Deno.test('a picture which could not be loaded shows the error', async () =>
{
	using page = createController();
	await page.suggest();
	page.insert.error = new Error('Not found');

	fire(page.buttons()[0], 'click');
	await tick();

	assertEquals(page.errors(), ['Not found']);
});

Deno.test('the close button closes the dialog', async () =>
{
	using page = createController();
	await page.suggest();

	fire(page.dialog.querySelector('.close-button'), 'click');

	assertEquals(page.dialog.hasAttribute('open'), false);
	assertSameElement(page.document.querySelector('dialog[open]'), null);
});
