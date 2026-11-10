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


import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {replaceGlobals}  from "@support/globals.js";
import {PlaylistPreview} from "@js/playlists/compose/standard/items/PlaylistPreview.js";

/**
 * GLightbox of public/js/external: records the options and where it was opened.
 */
function fakeGLightbox()
{
	const boxes = [];
	const factory = (options) =>
	{
		const box = {options, openedAt: null, destroyed: false,
			openAt(index) { this.openedAt = index; },
			destroy()     { this.destroyed = true; }};
		boxes.push(box);
		return box;
	};
	return {factory, boxes};
}

class FakeItemsService
{
	calls   = [];
	preview = {success: true, data: []};
	nested  = {success: true, data: {items: []}};

	async loadPreview(playlistId)
	{
		this.calls.push(['loadPreview', playlistId]);
		return this.preview;
	}

	async loadByPlaylistId(playlistId)
	{
		this.calls.push(['loadByPlaylistId', playlistId]);
		return this.nested;
	}
}

const ITEMS = [
	{item_id: 1, item_name: 'Lobby', type: 'image', url: '/media/1.jpg'},
	{item_id: 2, item_name: 'Clip', type: 'video', url: '/media/2.mp4'},
	{item_id: 3, item_name: 'Song', type: 'audio', url: '/media/3.mp3'},
	{item_id: 4, item_name: 'Menu', type: 'pdf', url: '/media/4.pdf'},
	{item_id: 5, item_name: 'Clock', type: 'widget', url: '/widget.php?id=5'},
	{item_id: 6, item_name: 'News', type: 'playlist', playlist_id: 9},
];

function createPage()
{
	const dom      = createDom();
	const lightbox = fakeGLightbox();
	const globals  = replaceGlobals({GLightbox: lightbox.factory});
	const service  = new FakeItemsService();
	const errors   = [];

	return {
		document: dom.document, service, errors, boxes: lightbox.boxes,
		preview: new PlaylistPreview(service, {showError: (message) => errors.push(message)}),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the preview shows every item of the playlist in the lightbox, opened at the chosen one', async () =>
{
	using page = createPage();
	page.service.preview.data = ITEMS;

	await page.preview.open(7, '4');

	assertEquals(page.service.calls[0], ['loadPreview', 7]);
	const [box] = page.boxes;
	assertEquals(box.openedAt, 3);
	assertEquals([box.options.width, box.options.height, box.options.loop, box.options.autoplayVideos], ['90vw', '90vh', false, true]);
	assertEquals(box.options.plyr, {css: '/css/external/plyr.css', js: '/js/external/plyr.js'});
});

Deno.test('every kind of item has its own element', async () =>
{
	using page = createPage();
	page.service.preview.data = ITEMS;

	await page.preview.open(7, 1);

	const [image, video, audio, pdf, widget, playlist] = page.boxes[0].options.elements;
	assertEquals(image, {href: '/media/1.jpg', type: 'image', title: 'Lobby'});
	assertEquals(video, {href: '/media/2.mp4', type: 'video', title: 'Clip'});
	assertEquals(pdf, {href: '/media/4.pdf', type: 'external', title: 'Menu'});
	assertEquals(widget, {href: '/widget.php?id=5', type: 'external', title: 'Clock'});
	assertEquals(audio.title, 'Song');
	// linkedom keeps src of an audio as property only
	assertEquals([audio.content.tagName, audio.content.src, audio.content.controls], ['AUDIO', '/media/3.mp3', true]);
	assertEquals(audio.content.classList.contains('preview-audio'), true);
	assertEquals([playlist.title, playlist.content.tagName], ['News', 'UL']);
});

Deno.test('a nested playlist shows the list of its items', async () =>
{
	using page = createPage();
	page.service.preview.data = [ITEMS[5]];
	page.service.nested.data.items = [
		{item_name: 'Weather', item_duration: 15, urls: {thumbnail: '/thumb/w.jpg'}},
		{item_name: 'Sports', item_duration: 90, urls: {thumbnail: '/thumb/s.jpg'}},
	];

	await page.preview.open(7, 6);
	await wait();

	const list = page.boxes[0].options.elements[0].content;
	assertEquals(page.service.calls[1], ['loadByPlaylistId', 9]);
	assertEquals(list.classList.contains('preview-nested-playlist'), true);
	assertEquals([...list.querySelectorAll('li')].map(entry => [
		entry.querySelector('img').getAttribute('src'), entry.querySelectorAll('span')[0].textContent, entry.querySelector('.preview-nested-duration').textContent
	]), [['/thumb/w.jpg', 'Weather', '00:00:15'], ['/thumb/s.jpg', 'Sports', '00:01:30']]);
});

Deno.test('a nested playlist which could not be loaded stays an empty list', async () =>
{
	using page = createPage();
	page.service.preview.data = [ITEMS[5]];
	page.service.nested = {success: false, error_message: 'Not allowed'};

	await page.preview.open(7, 6);
	await wait();

	assertEquals(page.boxes[0].options.elements[0].content.children.length, 0);
});

Deno.test('an unknown item opens the preview at the first item', async () =>
{
	using page = createPage();
	page.service.preview.data = ITEMS;

	await page.preview.open(7, 99);

	assertEquals(page.boxes[0].openedAt, 0);
});

Deno.test('a playlist without items opens nothing', async () =>
{
	using page = createPage();

	await page.preview.open(7, 1);

	assertEquals(page.boxes, []);
});

Deno.test('a preview which could not be loaded shows the error', async () =>
{
	using page = createPage();
	page.service.preview = {success: false, error_message: 'Not allowed'};

	await page.preview.open(7, 1);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(page.boxes, []);
});

Deno.test('the next preview replaces the lightbox before', async () =>
{
	using page = createPage();
	page.service.preview.data = ITEMS;

	await page.preview.open(7, 1);
	await page.preview.open(7, 2);

	assertEquals(page.boxes.map(box => box.destroyed), [true, false]);
	assertEquals(page.boxes[1].openedAt, 1);
});
