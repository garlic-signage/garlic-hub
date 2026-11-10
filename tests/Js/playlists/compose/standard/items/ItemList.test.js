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
import {ItemList}                     from "@js/playlists/compose/standard/items/ItemList.js";

// the list of templates/playlists/compose.mustache with the export button
const PAGE = `
	<button id="playerExport" data-release="0"><span class="label">Export</span></button>
	<ul id="playlistItems"></ul>`;

/**
 * An item with its element: the actions carry the id of the item like in the template.
 */
class FakeItem
{
	data;
	belongsToMasterPlaylist = null;
	previewAction    = null;
	deleteItemAction = null;
	#document;

	constructor(data, document)
	{
		this.data      = data;
		this.#document = document;
	}

	buildItemElement()
	{
		const item = this.#document.createElement('li');
		item.id = 'itemId-' + this.data.item_id;
		item.innerHTML = `<div class="actions" data-item-id="${this.data.item_id}"><button class="item-preview"></button><button class="delete-item"></button></div>`;
		this.previewAction    = item.querySelector('.item-preview');
		this.deleteItemAction = item.querySelector('.delete-item');
		return item;
	}
}

class FakeItemFactory
{
	created = [];
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(itemData, itemsService)
	{
		const item = new FakeItem(itemData, this.#document);
		this.created.push([item, itemsService]);
		return item;
	}
}

const METRICS = {duration: 30, count_items: 2, filesize: 100};

class FakeItemsService
{
	calls  = [];
	loaded = {success: true, data: {playlist: {playlist_mode: 'master', shuffle: 0}, items: [{item_id: 1}, {item_id: 2}], playlist_metrics: METRICS}};
	deleted = {success: true, data: {playlist_metrics: {...METRICS, count_items: 1}}};

	async loadByPlaylistId(playlistId)
	{
		this.calls.push(['loadByPlaylistId', playlistId]);
		return this.loaded;
	}

	async delete(playlistId, itemId)
	{
		this.calls.push(['delete', playlistId, itemId]);
		return this.deleted;
	}
}

class FakeProperties
{
	calls = [];

	setOptions(playlist)  { this.calls.push(['setOptions', playlist]); }
	display(metrics)      { this.calls.push(['display', metrics]); }
}

function createPage()
{
	const dom        = createDom(PAGE);
	const document   = dom.document;
	const factory    = new FakeItemFactory(document);
	const service    = new FakeItemsService();
	const properties = new FakeProperties();
	const previews   = [];
	const list       = new ItemList(factory, service, document.getElementById('playlistItems'), properties,
		{open: (playlistId, itemId) => previews.push([playlistId, itemId])});

	return {
		document, factory, service, properties, previews, list,
		target: document.getElementById('playlistItems'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function shownIds(page)
{
	return [...page.target.children].map(element => element.id);
}

async function click(element)
{
	element.dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the playlist is loaded with its items, options and metrics', async () =>
{
	using page = createPage();

	await page.list.buildPlaylist(7);

	assertEquals(page.service.calls, [['loadByPlaylistId', 7]]);
	assertEquals(shownIds(page), ['itemId-1', 'itemId-2']);
	assertEquals(page.properties.calls, [['setOptions', page.service.loaded.data.playlist], ['display', METRICS]]);
	assertEquals(page.factory.created.every(([, service]) => service === page.service), true);
});

Deno.test('the items know whether they belong to a master playlist', async () =>
{
	for (const [mode, master] of [['master', true], ['internal', false]])
	{
		using page = createPage();
		page.service.loaded.data.playlist.playlist_mode = mode;

		await page.list.buildPlaylist(7);

		assertEquals(page.factory.created.map(([item]) => item.belongsToMasterPlaylist), [master, master], mode);
	}
});

Deno.test('a playlist which could not be loaded shows nothing', async () =>
{
	using page = createPage();
	page.service.loaded = {success: false, error_message: 'Not allowed'};

	await page.list.buildPlaylist(7);

	assertEquals(shownIds(page), []);
	assertEquals(page.properties.calls, []);
});

Deno.test('a new item is added at the end', () =>
{
	using page = createPage();
	page.list.createPlaylistItem({item_id: 1});

	page.list.createPlaylistItem({item_id: 2});

	assertEquals(shownIds(page), ['itemId-1', 'itemId-2']);
});

Deno.test('a dropped item takes the place of the copy of the selector', () =>
{
	using page = createPage();
	page.list.createPlaylistItem({item_id: 1});
	page.list.createPlaylistItem({item_id: 2});

	// like the DragDropHandler: the copy is in the list, the position is the index of the item after it
	for (const [position, expected] of [[1, ['itemId-9', 'drop', 'itemId-1', 'itemId-2']], [2, ['itemId-1', 'itemId-9', 'drop', 'itemId-2']],
		[3, ['itemId-1', 'itemId-2', 'itemId-9', 'drop']]])
	{
		const copy = page.document.createElement('li');
		copy.id    = 'drop';
		page.target.insertBefore(copy, page.target.children[position - 1] ?? null);

		page.list.createPlaylistItem({item_id: 9}, position);

		assertEquals(shownIds(page), expected, String(position));
		copy.remove();
		page.document.getElementById('itemId-9').remove();
	}
});

Deno.test('a position after the end adds the item at the end', () =>
{
	using page = createPage();
	page.list.createPlaylistItem({item_id: 1});

	page.list.createPlaylistItem({item_id: 2}, 5);

	assertEquals(shownIds(page), ['itemId-1', 'itemId-2']);
});

Deno.test('the eye opens the preview at the item', async () =>
{
	using page = createPage();
	await page.list.buildPlaylist(7);

	await click(page.document.querySelector('#itemId-2 .item-preview'));

	assertEquals(page.previews, [[7, '2']]);
});

Deno.test('a deleted item leaves the list and the metrics are updated', async () =>
{
	using page = createPage();
	await page.list.buildPlaylist(7);
	page.properties.calls = [];

	await click(page.document.querySelector('#itemId-1 .delete-item'));

	assertEquals(page.service.calls.at(-1), ['delete', 7, '1']);
	assertEquals(shownIds(page), ['itemId-2']);
	assertEquals(page.properties.calls, [['display', {...METRICS, count_items: 1}]]);
	assertEquals(page.document.getElementById('playerExport').classList.contains('notify-save'), true);
});

Deno.test('an item which could not be deleted stays', async () =>
{
	using page = createPage();
	page.service.deleted = {success: false, error_message: 'Not allowed'};
	await page.list.buildPlaylist(7);

	await click(page.document.querySelector('#itemId-1 .delete-item'));

	assertEquals(shownIds(page), ['itemId-1', 'itemId-2']);
	assertEquals(page.document.getElementById('playerExport').classList.contains('notify-save'), false);
});

Deno.test('items without preview or delete get no events', () =>
{
	using page = createPage();
	page.factory.create = (itemData) =>
	{
		const item = new FakeItem(itemData, page.document);
		item.buildItemElement = () => { const element = page.document.createElement('li'); element.id = 'itemId-' + itemData.item_id; return element; };
		return item;
	};

	page.list.createPlaylistItem({item_id: 1});

	assertSameElement(page.target.firstElementChild, page.document.getElementById('itemId-1'));
});
