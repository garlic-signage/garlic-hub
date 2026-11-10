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


import {assertEquals, assertRejects}  from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {replaceGlobals}               from "@support/globals.js";
import {DragDropHandler}              from "@js/playlists/compose/standard/DragDropHandler.js";

// the playlist of templates/playlists/compose.mustache, the flash message of the main layout
const PAGE = `
	<template id="flashMessageTemplate"><div class="message-bar"><span></span><button class="close-button"></button></div></template>
	<button id="playerExport" data-release="0"><span class="label">Export</span></button>
	<ul id="playlistItems"><li id="itemId-5"></li><li id="itemId-3"></li></ul>
	<div id="mediaList"><article data-select-id="abc"></article></div>`;

/**
 * dragula of the page: records the containers and the options, the test fires the events with fire().
 */
class FakeDrake
{
	static created = [];

	containers;
	options;
	handlers  = {};
	destroyed = false;

	constructor(containers, options)
	{
		this.containers = containers;
		this.options    = options;
		FakeDrake.created.push(this);
	}

	on(name, handler)
	{
		this.handlers[name] = handler;
		return this;
	}

	destroy()
	{
		this.destroyed = true;
	}

	fire(name, ...args)
	{
		return this.handlers[name](...args);
	}
}

/**
 * Answers with the result set by the test and records the calls.
 */
class FakeItemsService
{
	calls  = [];
	result = {success: true, data: {item: {item_id: 9}, playlist_metrics: {duration: 60}}};

	async updateItemsOrders(playlistId, positions)
	{
		this.calls.push(['updateItemsOrders', playlistId, positions]);
		return this.result;
	}

	async insertMedia(id, playlistId, position)
	{
		this.calls.push(['insertMedia', id, playlistId, position]);
		return this.result;
	}

	async insertPlaylist(id, playlistId, position)
	{
		this.calls.push(['insertPlaylist', id, playlistId, position]);
		return this.result;
	}

	async insertTemplate(id, playlistId, position)
	{
		this.calls.push(['insertTemplate', id, playlistId, position]);
		return this.result;
	}
}

class FakeItemList
{
	calls = [];

	createPlaylistItem(item, position)
	{
		this.calls.push(['createPlaylistItem', item, position]);
	}

	displayPlaylistMetrics(metrics)
	{
		this.calls.push(['displayPlaylistMetrics', metrics]);
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	FakeDrake.created = [];
	const globals  = replaceGlobals({dragula: (containers, options) => new FakeDrake(containers, options)});
	const document = dom.document;
	const service  = new FakeItemsService();
	const itemList = new FakeItemList();
	const target   = document.getElementById('playlistItems');
	const handler  = new DragDropHandler(target, service, itemList);
	handler.playlistId = 7;

	return {
		document, service, itemList, handler, target,
		source: document.getElementById('mediaList'),
		drake:  () => FakeDrake.created.at(-1),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

/**
 * A selector was opened: its list is the second container.
 */
function withSource(page, source = 'mediapool')
{
	page.handler.source = source;
	page.handler.items  = {abc: {media_id: 'abc'}};
	page.handler.addDropSource(page.source);
	return page.drake();
}

/**
 * Like dragula after a drop of the selector: the copy is in the playlist before the sibling.
 */
function dropCopy(page, sibling = null)
{
	const copy = page.document.createElement('article');
	copy.setAttribute('data-select-id', 'abc');
	page.target.insertBefore(copy, sibling);
	return copy;
}

function errors(page)
{
	return [...page.document.querySelectorAll('.message-bar--error span')].map(span => span.textContent);
}

function isSaveNotified(page)
{
	return page.document.getElementById('playerExport').classList.contains('notify-save');
}

Deno.test('only the items of the playlist can be moved before a selector is opened', () =>
{
	using page  = createPage();
	const drake = page.drake();

	assertEquals(drake.containers.length, 1);
	assertSameElement(drake.containers[0], page.target);
	assertEquals(drake.options.accepts(null, page.target, page.target), true);
	assertEquals(drake.options.accepts(null, page.source, page.target), false);
});

Deno.test('an opened selector becomes a second container, the old dragula ends', () =>
{
	using page = createPage();
	const old  = page.drake();

	const drake = withSource(page);

	assertEquals(old.destroyed, true);
	assertEquals(drake.containers.length, 2);
	assertSameElement(drake.containers[1], page.source);
});

Deno.test('the selector gives copies only into the playlist, the playlist keeps its items', () =>
{
	using page  = createPage();
	const drake = withSource(page);

	assertEquals(drake.options.accepts(null, page.target, page.source), true);
	assertEquals(drake.options.accepts(null, page.source, page.source), false);
	assertEquals(drake.options.accepts(null, page.source, page.target), false);
	assertEquals(drake.options.copy(page.source.firstElementChild), true);
	assertEquals(drake.options.copy(page.target.firstElementChild), false);
});

Deno.test('the shadow of a dragged element is marked', () =>
{
	using page = createPage();
	const item = page.target.firstElementChild;

	page.drake().fire('shadow', item);

	assertEquals(item.classList.contains('dragula-shadow'), true);
});

Deno.test('a canceled drop changes nothing', async () =>
{
	using page = createPage();

	await page.drake().fire('drop', page.target.firstElementChild, null, page.target, null);

	assertEquals(page.service.calls, []);
});

Deno.test('a moved item saves the new order of the playlist', async () =>
{
	using page = createPage();
	page.target.appendChild(page.document.getElementById('itemId-5'));

	await page.drake().fire('drop', page.document.getElementById('itemId-5'), page.target, page.target, null);

	assertEquals(page.service.calls, [['updateItemsOrders', 7, {1: '3', 2: '5'}]]);
	assertEquals(isSaveNotified(page), true);
});

Deno.test('a refused order shows the error and asks for no export', async () =>
{
	using page = createPage();
	page.service.result = {success: false, error_message: 'Not allowed'};

	await page.drake().fire('drop', page.target.firstElementChild, page.target, page.target, null);

	assertEquals(errors(page), ['Not allowed']);
	assertEquals(isSaveNotified(page), false);
});

Deno.test('every selector inserts with its own request', async () =>
{
	const requests = {mediapool: 'insertMedia', playlists: 'insertPlaylist', templates: 'insertTemplate'};

	for (const [source, request] of Object.entries(requests))
	{
		using page  = createPage();
		const drake = withSource(page, source);
		const copy  = dropCopy(page);

		await drake.fire('drop', copy, page.target, page.source, null);

		assertEquals(page.service.calls, [[request, 'abc', 7, 3]], source);
	}
});

Deno.test('a copy dropped at the end becomes the last item', async () =>
{
	using page  = createPage();
	const drake = withSource(page);
	const copy  = dropCopy(page);

	await drake.fire('drop', copy, page.target, page.source, null);

	assertEquals(page.itemList.calls, [
		['createPlaylistItem', {item_id: 9}, 3], ['displayPlaylistMetrics', {duration: 60}]
	]);
	assertEquals(copy.parentElement, null);
	assertEquals(isSaveNotified(page), true);
});

Deno.test('a copy dropped before an item takes its place', async () =>
{
	using page    = createPage();
	const drake   = withSource(page);
	const sibling = page.document.getElementById('itemId-3');
	const copy    = dropCopy(page, sibling);

	await drake.fire('drop', copy, page.target, page.source, sibling);

	assertEquals(page.service.calls, [['insertMedia', 'abc', 7, 2]]);
	assertEquals(page.itemList.calls[0], ['createPlaylistItem', {item_id: 9}, 2]);
});

Deno.test('a refused insert removes the copy and shows the error', async () =>
{
	using page  = createPage();
	const drake = withSource(page);
	page.service.result = {success: false, error_message: 'The playlist is full'};
	const copy  = dropCopy(page);

	await drake.fire('drop', copy, page.target, page.source, null);

	assertEquals(copy.parentElement, null);
	assertEquals(errors(page), ['The playlist is full']);
	assertEquals(page.itemList.calls, []);
	assertEquals(isSaveNotified(page), false);
});

Deno.test('a drop without a known selector is an error', async () =>
{
	using page  = createPage();
	const drake = withSource(page, 'channels');

	await assertRejects(() => drake.fire('drop', dropCopy(page), page.target, page.source, null), Error, 'Unknown source');
	assertEquals(page.service.calls, []);
});
