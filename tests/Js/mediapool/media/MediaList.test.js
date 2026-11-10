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

import {assertEquals, assertRejects}   from "@std/assert";
import {assertSameElement, createDom}  from "@support/dom.js";
import {MemoryStorage, replaceGlobals} from "@support/globals.js";
import {MediaList}                     from "@js/mediapool/media/MediaList.js";

const PAGE = `
	<button id="setListView"></button>
	<button id="setGridView"></button>
	<div id="mediaList"></div>
	<ul id="contextMenu">
		<li id="infoMedia"></li>
		<li id="editMedia"></li>
		<li id="removeMedia"></li>
		<li id="cloneMedia"></li>
		<li id="suggestTags"></li>
	</ul>`;

/**
 * Builds the article of a medium as Media.renderForDisplayInMediaPool() does, with its drag icon.
 */
class FakeMediaFactory
{
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(media)
	{
		const document = this.#document;
		return {
			renderForDisplayInMediaPool()
			{
				const article = document.createElement('article');
				article.className = 'media-item';
				article.setAttribute('data-media-id', media.media_id);
				article.setAttribute('data-mimetype', media.mimetype ?? 'image/jpeg');
				if (media.editable !== undefined)
					article.setAttribute('data-editable', media.editable ? 'true' : 'false');

				const icon = document.createElement('i');
				icon.className = 'media-drag-icon';
				article.appendChild(icon);

				return article;
			}
		};
	}
}

class FakeContextMenu
{
	calls = [];

	show(event)                       { this.calls.push(['show', event.type]); }
	addInfoEvent(element)             { this.calls.push(['info', element.id]); }
	addRemoveEvent(element)           { this.calls.push(['remove', element.id]); }
	addEditEvent(element)             { this.calls.push(['edit', element.id]); }
	addSuggestTagsEvent(element)      { this.calls.push(['suggestTags', element.id]); }

	addCloneEvent(element, addToList)
	{
		this.calls.push(['clone', element.id]);
		this.addToList = addToList;
	}
}

class FakeContextMenuFactory
{
	menus = [];

	create(mediaItem)
	{
		const menu = new FakeContextMenu();
		menu.mediaItem = mediaItem;
		this.menus.push(menu);
		return menu;
	}
}

class FakeMediaService
{
	loads = [];
	moves = [];
	media = [];
	moveError = null;

	loadFilteredMediaByNodeId(nodeId, filter)
	{
		this.loads.push([nodeId, filter]);
		return Promise.resolve(this.media);
	}

	moveMedia(mediaId, nodeId)
	{
		this.moves.push([mediaId, nodeId]);
		return this.moveError === null ? Promise.resolve({success: true}) : Promise.reject(this.moveError);
	}
}

/**
 * A page with the media list: the storage, GLightbox and the DOM are replaced until the test ends.
 */
function createPage(storedView = null)
{
	const dom        = createDom(PAGE);
	const storage    = new MemoryStorage();
	const lightboxes = [];
	if (storedView !== null)
		storage.setItem('media-list-view', storedView);

	const globals = replaceGlobals({
		localStorage: storage,
		GLightbox: (options) =>
		{
			const lightbox = {options: options, destroyed: false, destroy() { this.destroyed = true; }};
			lightboxes.push(lightbox);
			return lightbox;
		}
	});

	const document           = dom.document;
	const element            = document.getElementById('mediaList');
	const mediaService       = new FakeMediaService();
	const contextMenuFactory = new FakeContextMenuFactory();
	const mediaList          = new MediaList(element, new FakeMediaFactory(document), contextMenuFactory, mediaService);

	return {
		document, element, storage, lightboxes, mediaService, contextMenuFactory, mediaList,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function shownIds(page)
{
	return [...page.element.querySelectorAll('.media-item')].map(item => item.getAttribute('data-media-id'));
}

function openContextMenu(page, mediaId)
{
	const item  = page.element.querySelector(`[data-media-id="${mediaId}"]`);
	const event = new Event('contextmenu', {cancelable: true});
	item.dispatchEvent(event);

	return {menu: page.contextMenuFactory.menus.at(-1), event: event};
}

function menuEntries(page)
{
	return [...page.document.querySelectorAll('#contextMenu li')].map(entry => entry.id);
}

Deno.test('the list view is the default and is stored', () =>
{
	using page = createPage();

	assertEquals(page.element.className, 'media-list-list-view');
	assertEquals(page.storage.getItem('media-list-view'), 'media-list-list-view');
});

Deno.test('a stored view stays and is set when the list is shown', () =>
{
	using page = createPage('media-list-grid-view');

	assertEquals(page.element.className, '');

	page.mediaList.render([]);

	assertEquals(page.element.className, 'media-list-grid-view');
	assertEquals(page.storage.getItem('media-list-view'), 'media-list-grid-view');
});

Deno.test('the buttons switch between list and grid and store it', () =>
{
	using page = createPage();

	page.document.getElementById('setGridView').dispatchEvent(new Event('click'));
	assertEquals(page.element.className, 'media-list-grid-view');
	assertEquals(page.storage.getItem('media-list-view'), 'media-list-grid-view');

	page.document.getElementById('setListView').dispatchEvent(new Event('click'));
	assertEquals(page.element.className, 'media-list-list-view');
	assertEquals(page.storage.getItem('media-list-view'), 'media-list-list-view');
});

Deno.test('a directory is loaded with the encoded filter and ends a search', async () =>
{
	using page = createPage();
	const loaded = [];
	page.element.addEventListener('directoryloaded', () => loaded.push(shownIds(page)));
	page.mediaService.media = [{media_id: 'a'}, {media_id: 'b'}];
	page.mediaList.filter   = 'images/png';

	await page.mediaList.loadMediaListByNode(5, {create: true});

	assertEquals(page.mediaService.loads, [[5, 'images%2Fpng']]);
	assertEquals(shownIds(page), ['a', 'b']);
	assertEquals(loaded, [['a', 'b']]);
});

Deno.test('a new list replaces the old one and its lightbox', () =>
{
	using page = createPage();

	page.mediaList.render([{media_id: 'a'}]);
	page.mediaList.render([{media_id: 'b'}, {media_id: 'c'}]);

	assertEquals(shownIds(page), ['b', 'c']);
	assertEquals(page.lightboxes.map(lightbox => lightbox.destroyed), [true, false]);
	assertEquals(page.lightboxes[1].options.plyr, {css: '/css/external/plyr.css', js: '/js/external/plyr.js'});
});

Deno.test('the results of a search are shown without ending the search', () =>
{
	using page = createPage();
	const loaded = [];
	page.element.addEventListener('directoryloaded', () => loaded.push(true));

	page.mediaList.showSearchResults([{media_id: 'a'}]);

	assertEquals(shownIds(page), ['a']);
	assertEquals(loaded, []);
});

Deno.test('a moved medium leaves the list', async () =>
{
	using page = createPage();
	page.mediaList.render([{media_id: 'a'}, {media_id: 'b'}]);

	await page.mediaList.moveMediaTo('a', 7);

	assertEquals(page.mediaService.moves, [['a', 7]]);
	assertEquals(shownIds(page), ['b']);
});

Deno.test('a medium that could not be moved stays in the list', async () =>
{
	using page = createPage();
	page.mediaList.render([{media_id: 'a'}]);
	page.mediaService.moveError = new Error('Not allowed');

	await assertRejects(() => page.mediaList.moveMediaTo('a', 7), Error, 'Not allowed');
	assertEquals(shownIds(page), ['a']);
});

Deno.test('the context menu of an editable picture has all actions', async () =>
{
	using page = createPage();
	page.mediaService.media = [{media_id: 'a', mimetype: 'image/jpeg'}];
	await page.mediaList.loadMediaListByNode(5, {create: true});

	const {menu, event} = openContextMenu(page, 'a');

	assertEquals(event.defaultPrevented, true);
	assertSameElement(menu.mediaItem, page.element.querySelector('[data-media-id="a"]'));
	assertEquals(menu.calls, [
		['show', 'contextmenu'], ['info', 'infoMedia'], ['remove', 'removeMedia'], ['edit', 'editMedia'],
		['clone', 'cloneMedia'], ['suggestTags', 'suggestTags']
	]);
	assertEquals(menuEntries(page), ['infoMedia', 'editMedia', 'removeMedia', 'cloneMedia', 'suggestTags']);
});

Deno.test('the tags are only suggested for pictures, videos and PDFs', async () =>
{
	for (const mimetype of ['image/svg+xml', 'audio/mpeg', 'application/widget'])
	{
		using page = createPage();
		page.mediaService.media = [{media_id: 'a', mimetype: mimetype}];
		await page.mediaList.loadMediaListByNode(5, {create: true});

		const {menu} = openContextMenu(page, 'a');

		assertEquals(menu.calls.some(call => call[0] === 'suggestTags'), false, mimetype);
		assertEquals(menuEntries(page), ['infoMedia', 'editMedia', 'removeMedia', 'cloneMedia'], mimetype);
	}
});

Deno.test('without an AI the menu has no suggestion and still works', async () =>
{
	using page = createPage();
	page.document.getElementById('suggestTags').remove();
	page.mediaService.media = [{media_id: 'a'}];
	await page.mediaList.loadMediaListByNode(5, {create: true});

	const {menu} = openContextMenu(page, 'a');

	assertEquals(menu.calls.map(call => call[0]), ['show', 'info', 'remove', 'edit', 'clone']);
});

Deno.test('without the right to create a directory only shows the information', async () =>
{
	for (const rights of [{create: false}, {}, null])
	{
		using page = createPage();
		page.mediaService.media = [{media_id: 'a'}];
		await page.mediaList.loadMediaListByNode(5, rights);

		const {menu} = openContextMenu(page, 'a');

		assertEquals(menu.calls.map(call => call[0]), ['show', 'info'], JSON.stringify(rights));
		assertEquals(menuEntries(page), ['infoMedia'], JSON.stringify(rights));
	}
});

Deno.test('a result of the search decides itself whether it may be edited', () =>
{
	using page = createPage();
	page.mediaList.showSearchResults([{media_id: 'mine', editable: true}, {media_id: 'other', editable: false}]);

	assertEquals(openContextMenu(page, 'mine').menu.calls.map(call => call[0]), ['show', 'info', 'remove', 'edit', 'clone', 'suggestTags']);
	assertEquals(openContextMenu(page, 'other').menu.calls.map(call => call[0]), ['show', 'info']);
});

Deno.test('a clone is added to the list with its own context menu', async () =>
{
	using page = createPage();
	page.mediaService.media = [{media_id: 'a'}];
	await page.mediaList.loadMediaListByNode(5, {create: true});

	openContextMenu(page, 'a').menu.addToList({media_id: 'a-clone'});

	assertEquals(shownIds(page), ['a', 'a-clone']);
	assertEquals(openContextMenu(page, 'a-clone').menu.calls[0], ['show', 'contextmenu']);
});

function startDrag(article)
{
	const dataTransfer = {
		data: null, image: null,
		setData(type, value)       { this.data = [type, value]; },
		setDragImage(image, x, y)  { this.image = [image, x, y]; }
	};

	const dragStart = new Event('dragstart');
	dragStart.dataTransfer = dataTransfer;
	article.querySelector('.media-drag-icon').dispatchEvent(dragStart);

	return dataTransfer;
}

Deno.test('the drag icon drags the whole medium', () =>
{
	using page = createPage();
	page.mediaList.showSearchResults([{media_id: 'a'}]);
	const article = page.element.querySelector('.media-item');
	const icon    = article.querySelector('.media-drag-icon');

	const dataTransfer = startDrag(article);

	assertEquals(article.getAttribute('draggable'), 'true');
	assertEquals(dataTransfer.data, ['text/plain', '']);
	assertSameElement(dataTransfer.image[0], article);
	assertEquals(dataTransfer.image.slice(1), [0, 0]);

	icon.dispatchEvent(new Event('dragend'));

	assertEquals(article.hasAttribute('draggable'), false);
});

Deno.test('a clone can be dragged at once', async () =>
{
	using page = createPage();
	page.mediaService.media = [{media_id: 'a'}];
	await page.mediaList.loadMediaListByNode(5, {create: true});

	openContextMenu(page, 'a').menu.addToList({media_id: 'a-clone'});
	const clone = page.element.querySelector('[data-media-id="a-clone"]');
	startDrag(clone);

	assertEquals(clone.getAttribute('draggable'), 'true');
});

Deno.test('a medium gets its drag handler once, also after several lists', () =>
{
	using page = createPage();
	page.mediaList.render([{media_id: 'a'}]);
	page.mediaList.showSearchResults([{media_id: 'b'}]);

	let calls     = 0;
	const dragStart = new Event('dragstart');
	dragStart.dataTransfer = {setData: () => calls++, setDragImage() {}};
	page.element.querySelector('.media-drag-icon').dispatchEvent(dragStart);

	assertEquals(calls, 1);
});
