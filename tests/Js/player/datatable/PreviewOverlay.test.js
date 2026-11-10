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
import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {replaceGlobals}               from "@support/globals.js";
import {PreviewOverlay}               from "@js/player/datatable/PreviewOverlay.js";

// the dialog of templates/player/datatable.mustache
const PAGE = `
	<dialog id="playerPreviewDialog" class="player-preview">
		<header>
			<span class="dialog-name"></span>
			<button type="button" class="close-button bi bi-x" aria-label="Close"></button>
		</header>
		<div class="preview-frame"></div>
	</dialog>`;

const ORIGIN = 'https://hub.example.com';

/**
 * The window of the page: its listeners are called by the test.
 */
class FakeWindow
{
	listeners = [];

	addEventListener(type, listener, options = {})
	{
		this.listeners.push({type, listener, once: options.once === true});
	}

	fire(type, event = {})
	{
		for (const entry of [...this.listeners].filter(entry => entry.type === type))
		{
			if (entry.once)
				this.listeners.splice(this.listeners.indexOf(entry), 1);
			entry.listener(event);
		}
	}
}

function createPage()
{
	const dom     = createDom(PAGE);
	const window  = new FakeWindow();
	const globals = replaceGlobals({window, location: {origin: ORIGIN}});
	const overlay = new PreviewOverlay();

	return {
		document: dom.document, window, overlay,
		dialog: dom.document.getElementById('playerPreviewDialog'),
		frame:  dom.document.querySelector('.preview-frame'),
		iframe: () => dom.document.querySelector('.preview-frame iframe'),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

/**
 * The window of the player in the iframe, which sends the layout.
 */
function playerWindowOf(iframe)
{
	const playerWindow = {};
	Object.defineProperty(iframe, 'contentWindow', {value: playerWindow});
	return playerWindow;
}

Deno.test('the preview plays as the player from the SMIL index of the hub, without a cache', () =>
{
	using page = createPage();

	page.overlay.open('11111111-1111-4111-8111-111111111111', 'Eingang & Lobby');

	const url = new URL(page.iframe().getAttribute('src'), ORIGIN);
	assertEquals(url.pathname, '/js/player/preview/index.html');
	assertEquals(Object.fromEntries(url.searchParams), {
		index: ORIGIN + '/smil-index', uuid: '11111111-1111-4111-8111-111111111111', name: 'Eingang & Lobby', storage: 'memory'
	});
	assertEquals(page.iframe().getAttribute('allow'), 'autoplay');
	assertEquals(page.document.querySelector('.dialog-name').textContent, 'Eingang & Lobby');
	assertEquals(page.dialog.hasAttribute('open'), true);
	assertEquals(page.frame.style.aspectRatio, '16 / 9');
});

Deno.test('closing ends the player: the iframe goes', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');

	page.document.querySelector('.close-button').dispatchEvent(new Event('click'));

	assertEquals(page.dialog.hasAttribute('open'), false);
	assertEquals(page.iframe(), null);
});

Deno.test('every preview gets a new player', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');
	page.dialog.close();

	page.overlay.open('uuid-2', 'Kasse');

	assertEquals(page.frame.querySelectorAll('iframe').length, 1);
	assertEquals(new URL(page.iframe().getAttribute('src'), ORIGIN).searchParams.get('uuid'), 'uuid-2');
});

Deno.test('the frame gets the proportions of the layout the player sends', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');
	const playerWindow = playerWindowOf(page.iframe());

	page.window.fire('message', {origin: ORIGIN, source: playerWindow, data: {type: 'garlic-preview-layout', width: 1080, height: '1920'}});

	assertEquals(page.frame.style.aspectRatio, '1080 / 1920');
});

Deno.test('only the layout of the own player counts', async (t) =>
{
	const cases = {
		'another origin':        (playerWindow) => ({origin: 'https://evil.example', source: playerWindow, data: {type: 'garlic-preview-layout', width: 800, height: 600}}),
		'another window':        () => ({origin: ORIGIN, source: {}, data: {type: 'garlic-preview-layout', width: 800, height: 600}}),
		'another message':       (playerWindow) => ({origin: ORIGIN, source: playerWindow, data: {type: 'something', width: 800, height: 600}}),
		'no data':               (playerWindow) => ({origin: ORIGIN, source: playerWindow, data: null}),
		'a size of zero':        (playerWindow) => ({origin: ORIGIN, source: playerWindow, data: {type: 'garlic-preview-layout', width: 0, height: 600}}),
		'a size which is no number': (playerWindow) => ({origin: ORIGIN, source: playerWindow, data: {type: 'garlic-preview-layout', width: 800, height: 'x'}}),
	};

	for (const [name, message] of Object.entries(cases))
	{
		await t.step(name, () =>
		{
			using page = createPage();
			page.overlay.open('uuid-1', 'Eingang');

			page.window.fire('message', message(playerWindowOf(page.iframe())));

			assertEquals(page.frame.style.aspectRatio, '16 / 9');
		});
	}
});

Deno.test('a message after the close is ignored', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');
	const playerWindow = playerWindowOf(page.iframe());
	page.dialog.close();

	page.window.fire('message', {origin: ORIGIN, source: playerWindow, data: {type: 'garlic-preview-layout', width: 800, height: 600}});

	assertEquals(page.frame.style.aspectRatio, '16 / 9');
});

Deno.test('while the corner of the frame is dragged a cover lies over the player', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');

	page.frame.dispatchEvent(new Event('pointerdown'));
	const cover = page.frame.querySelector('.drag-cover');
	assertSameElement(page.frame.lastElementChild, cover);

	page.window.fire('pointerup');
	assertEquals(page.frame.querySelector('.drag-cover'), null);
});

Deno.test('a press inside the player is no drag of the corner', () =>
{
	using page = createPage();
	page.overlay.open('uuid-1', 'Eingang');

	page.iframe().dispatchEvent(new Event('pointerdown', {bubbles: true}));

	assertEquals(page.frame.querySelector('.drag-cover'), null);
});
