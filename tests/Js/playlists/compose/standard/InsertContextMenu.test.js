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


import {assertEquals, assertThrows}   from "@std/assert";
import {stub}                         from "@std/testing/mock";
import {assertSameElement, createDom} from "@support/dom.js";
import {InsertContextMenu}            from "@js/playlists/compose/standard/InsertContextMenu.js";

// the insert menu of templates/playlists/compose.mustache
const PAGE = `
	<select id="insertMenuSelect">
		<option value="insertMedia">Media</option>
		<option value="insertExternalMedia">External media</option>
		<option value="insertPlaylists">Playlists</option>
		<option value="insertExternalPlaylists">External playlists</option>
		<option value="insertTemplates">Templates</option>
		<option value="insertChannels">Channels</option>
		<option value="fillWithAi">Fill with AI</option>
		<option value="other">Other</option>
	</select>
	<div id="itemSelectContainer"></div>
	<div id="mediaItems"></div>
	<div id="listItems"></div>`;

/**
 * The media selector has its own names for the items and their list, the others share them.
 */
class FakeSelector
{
	shown = [];
	type;
	#document;

	constructor(type, document)
	{
		this.type      = type;
		this.#document = document;
	}

	get items()
	{
		return {type: this.type};
	}

	async showSelector(container)
	{
		this.shown.push(container);
	}

	getMediaItems()
	{
		return {type: 'media'};
	}

	getMediaItemsContainer()
	{
		return this.#document.getElementById('mediaItems');
	}

	getItemsContainer()
	{
		return this.#document.getElementById('listItems');
	}
}

class FakeSelectorFactory
{
	selectors = {};
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(type)
	{
		this.selectors[type] ??= new FakeSelector(type, this.#document);
		return this.selectors[type];
	}
}

class FakeDragDropHandler
{
	source  = '';
	items   = null;
	sources = [];

	addDropSource(container)
	{
		this.sources.push(container);
	}
}

function createPage(aiFiller = null)
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const factory  = new FakeSelectorFactory(document);
	const handler  = new FakeDragDropHandler();
	const menu     = new InsertContextMenu(factory, handler, aiFiller);

	return {
		document, factory, handler, menu,
		container: document.getElementById('itemSelectContainer'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

async function choose(page, value)
{
	const select = page.document.getElementById('insertMenuSelect');
	select.value = value;
	select.dispatchEvent(new Event('change'));
	await wait();
}

Deno.test('the menu starts with the media of the mediapool', async () =>
{
	using page = createPage();

	page.menu.init();
	await wait();

	const selector = page.factory.selectors.mediapool;
	assertSameElement(selector.shown[0], page.container);
	assertEquals(page.handler.source, 'mediapool');
	assertEquals(page.handler.items, {type: 'media'});
	assertSameElement(page.handler.sources[0], page.document.getElementById('mediaItems'));
});

Deno.test('playlists and templates are dragged from their own selector', async () =>
{
	for (const [option, type] of [['insertPlaylists', 'playlists'], ['insertTemplates', 'templates']])
	{
		using page = createPage();
		page.menu.init();
		await wait();

		await choose(page, option);

		assertSameElement(page.factory.selectors[type].shown[0], page.container);
		assertEquals(page.handler.source, type, option);
		assertEquals(page.handler.items, {type: type}, option);
		assertSameElement(page.handler.sources.at(-1), page.document.getElementById('listItems'));
	}
});

Deno.test('the media can be chosen again', async () =>
{
	using page = createPage();
	page.menu.init();
	await wait();
	await choose(page, 'insertPlaylists');

	await choose(page, 'insertMedia');

	assertEquals(page.handler.source, 'mediapool');
	assertEquals(page.factory.selectors.mediapool.shown.length, 2);
});

Deno.test('external media, external playlists and channels are not built yet', async () =>
{
	using page      = createPage();
	using alertStub = stub(globalThis, 'alert');
	page.menu.init();
	await wait();

	for (const option of ['insertExternalMedia', 'insertExternalPlaylists', 'insertChannels'])
		await choose(page, option);

	assertEquals(alertStub.calls.map(call => call.args[0]), ['Insert external media', 'Insert external playlists', 'Insert channels']);
	assertEquals(page.handler.source, 'mediapool');
});

Deno.test('the AI fills in the place of the selectors', async () =>
{
	const shown    = [];
	using page     = createPage({show: (container) => shown.push(container)});
	page.menu.init();
	await wait();

	await choose(page, 'fillWithAi');

	assertEquals(shown.length, 1);
	assertSameElement(shown[0], page.container);
});

Deno.test('without an AI the choice does nothing', async () =>
{
	using page = createPage();
	page.menu.init();
	await wait();

	await choose(page, 'fillWithAi');

	assertEquals(page.handler.source, 'mediapool');
});

Deno.test('an unknown choice is an error', async () =>
{
	using page = createPage();
	page.menu.init();
	await wait();
	const select = page.document.getElementById('insertMenuSelect');
	select.value = 'other';

	assertThrows(() => select.dispatchEvent(new Event('change')), Error, 'Unknown insert menu option');
});
