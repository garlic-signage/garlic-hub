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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {createDom}                        from "@support/dom.js";

// Media imports the DirectoryView, it loads UploadDialogElements, which reads the document when it is loaded
let Media, DirectoryView;
{
	using _dom = createDom('<button id="openUploadDialog"></button>');
	({Media}         = await import("@js/mediapool/media/Media.js"));
	({DirectoryView} = await import("@js/mediapool/treeview/DirectoryView.js"));
}

// the template of templates/mediapool/overview.mustache
const TEMPLATE = `
	<template id="mediaTemplate">
		<article class="media-item" draggable="false">
			<figure draggable="false">
				<i class="media-drag-icon bi bi-grip-vertical" draggable="true"></i>
				<img src="" alt="Media Thumbnail" draggable="false">
				<i class="media-type-icon bi" draggable="false"></i>
				<a href="" class="glightbox media-view-icon bi-eye bi" draggable="false"></a>
				<figcaption class="media-info" draggable="false">
					<p class="wrap-media-filename"><span class="media-filename"></span></p>
					<p class="wrap-media-owner"><span class="media-owner"></span></p>
					<p class="wrap-media-path" style="display: none;"><span class="media-path"></span></p>
					<p class="wrap-media-mimetype"><span class="media-mimetype"></span></p>
					<p class="wrap-media-filesize"><span class="media-filesize"></span></p>
					<p class="wrap-media-dimensions" style="display: none;"><span class="media-dimensions"></span>px</p>
					<p class="wrap-media-duration" style="display: none;"><span class="media-duration"></span></p>
				</figcaption>
			</figure>
		</article>
	</template>`;

function mediaData(changes = {})
{
	return {
		media_id: 'abc', checksum: 'f00d', extension: 'jpg', filename: 'Lobby', mimetype: 'image/jpeg',
		media_description: 'The lobby', username: 'admin', tags: ['lobby', 'hall'],
		urls: {thumbnail: '/media.php?thumb=abc', original: '/media.php?original=abc'},
		metadata: {size: 1536, dimensions: {width: 1920, height: 1080}},
		...changes
	};
}

/**
 * A medium with a copy of the template, as the MediaFactory builds it.
 */
function createMedia(changes = {})
{
	const dom      = createDom(TEMPLATE);
	const template = dom.document.getElementById('mediaTemplate');
	const media    = new Media(template.content.cloneNode(true), mediaData(changes));

	return {media, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function text(item, selector)
{
	return item.querySelector(selector).textContent;
}

function isShown(item, selector)
{
	return item.querySelector(selector).parentElement.style.display === 'block';
}

Deno.test('the data of a medium are prepared for the list', () =>
{
	using page = createMedia();
	const media = page.media;

	assertEquals(media.mediaId, 'abc');
	assertEquals(media.thumbnailUrl, '/media.php?thumb=abc');
	assertEquals(media.originalUrl, '/media.php?original=abc');
	assertEquals(media.fileName, 'f00d.jpg');
	assertEquals(media.filename, 'Lobby');
	assertEquals(media.filesize, '1.50 KB');
	assertEquals(media.dimensions, '1920x1080');
	assertEquals(media.duration, 0);
});

Deno.test('dimensions and a duration exist only with values', () =>
{
	for (const metadata of [{size: 1}, {size: 1, dimensions: {}, duration: 0}])
	{
		using page = createMedia({metadata: metadata});
		assertEquals([page.media.dimensions, page.media.duration], ['', 0], JSON.stringify(metadata));
	}

	using page = createMedia({metadata: {size: 1, duration: 72.48}});
	assertEquals(page.media.duration, 72.48);
});

Deno.test('the article carries the data the consumers need', () =>
{
	using page = createMedia();

	const item = page.media.renderForDisplayInMediaPool();

	assertStrictEquals(page.media.mediaItem, item);
	assertEquals(item.getAttribute('data-media-id'), 'abc');
	assertEquals(item.getAttribute('data-original-url'), '/media.php?original=abc');
	assertEquals(item.getAttribute('data-file-name'), 'f00d.jpg');
	assertEquals(item.getAttribute('data-mimetype'), 'image/jpeg');

	const img = item.querySelector('img');
	assertEquals(img.getAttribute('src'), '/media.php?thumb=abc');
	assertEquals(img.getAttribute('alt'), 'Thumbnail: Lobby');
	assertEquals(img.getAttribute('data-title'), 'Lobby');
	assertEquals(img.getAttribute('data-description'), 'The lobby');
	assertEquals(img.getAttribute('data-tags'), 'lobby, hall');
});

Deno.test('without description and tags the edit dialog starts empty', () =>
{
	using page = createMedia({media_description: null, tags: undefined});

	const img = page.media.renderForDisplayInMediaPool().querySelector('img');

	assertEquals(img.getAttribute('data-description'), '');
	assertEquals(img.getAttribute('data-tags'), '');
});

Deno.test('the icon shows the kind of the medium', () =>
{
	const icons = {
		'audio/mpeg': 'bi-music-note', 'video/mp4': 'bi-film', 'image/png': 'bi-image', 'application/pdf': 'bi-filetype-pdf',
		'application/zip': 'bi-file-zip', 'application/widget': 'bi-file-zip', 'application/wgt': 'bi-file-zip',
		'text/plain': 'bi-file-earmark',
	};

	for (const [mimetype, icon] of Object.entries(icons))
	{
		using page = createMedia({mimetype: mimetype});
		const item = page.media.renderSimple();
		assertEquals(item.querySelector('.media-type-icon').classList.contains(icon), true, mimetype);
	}
});

Deno.test('the eye opens the original of pictures, videos, audios and PDFs in the lightbox', () =>
{
	for (const mimetype of ['image/jpeg', 'video/mp4', 'audio/mpeg', 'application/pdf'])
	{
		using page = createMedia({mimetype: mimetype});
		const link = page.media.renderForDisplayInMediaPool().querySelector('a');

		assertEquals(link.getAttribute('href'), '/media.php?original=abc', mimetype);
		assertEquals(link.classList.contains('glightbox'), true, mimetype);
	}
});

Deno.test('a widget opens its sandboxed preview as external page', () =>
{
	using page = createMedia({mimetype: 'application/widget', urls: {thumbnail: 't', original: 'o', preview: '/widget.php?id=abc'}});

	const link = page.media.renderForDisplayInMediaPool().querySelector('a');

	assertEquals(link.getAttribute('href'), '/widget.php?id=abc');
	assertEquals(link.dataset.type, 'external');
});

Deno.test('other files and widgets without preview have no eye', () =>
{
	for (const mimetype of ['application/zip', 'application/widget', 'text/plain'])
	{
		using page = createMedia({mimetype: mimetype});
		const link = page.media.renderForDisplayInMediaPool().querySelector('a');

		assertEquals(link.classList.contains('glightbox'), false, mimetype);
		assertEquals(link.style.display, 'none', mimetype);
	}
});

Deno.test('the caption shows owner, name, size and kind, dimensions only with values', () =>
{
	using page = createMedia();

	const item = page.media.renderForDisplayInMediaPool();

	assertEquals(text(item, '.media-owner'), 'admin');
	assertEquals(text(item, '.media-filename'), 'Lobby');
	assertEquals(text(item, '.media-filesize'), '1.50 KB');
	assertEquals(text(item, '.media-mimetype'), 'image/jpeg');
	assertEquals(text(item, '.media-dimensions'), '1920x1080');
	assertEquals(isShown(item, '.media-dimensions'), true);
	assertEquals(isShown(item, '.media-duration'), false);
	assertEquals(isShown(item, '.media-path'), false);
	assertEquals(item.hasAttribute('data-editable'), false);
});

Deno.test('a video shows its rounded duration', () =>
{
	using page = createMedia({mimetype: 'video/mp4', metadata: {size: 1, duration: 72.48}});

	const item = page.media.renderForDisplayInMediaPool();

	assertEquals(text(item, '.media-duration'), '00:01:12');
	assertEquals(isShown(item, '.media-duration'), true);
	assertEquals(isShown(item, '.media-dimensions'), false);
});

Deno.test('a result of the search shows its directory and whether it may be edited', () =>
{
	for (const editable of [true, false])
	{
		using page = createMedia({path: 'Lobby / Screens', editable: editable});

		const item = page.media.renderForDisplayInMediaPool();

		assertEquals(text(item, '.media-path'), 'Lobby / Screens');
		assertEquals(isShown(item, '.media-path'), true);
		assertEquals(item.getAttribute('data-editable'), String(editable));
	}
});

Deno.test('dragging the article tells the tree which medium it is', () =>
{
	using page = createMedia();
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = '';

	page.media.renderForDisplayInMediaPool().dispatchEvent(new Event('dragstart'));

	assertEquals(DirectoryView.workaroundShitForMediaIdBecauseOfChrome, 'abc');
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = '';
});

Deno.test('the simple view for the selectors has only the thumbnail and the data', () =>
{
	using page = createMedia();

	const item = page.media.renderSimple();

	assertStrictEquals(page.media.mediaItem, item);
	assertEquals(item.getAttribute('data-media-id'), 'abc');
	assertEquals(item.querySelector('img').getAttribute('src'), '/media.php?thumb=abc');
	assertEquals(text(item, '.media-filename'), '');
	assertEquals(item.querySelector('a').getAttribute('href'), '');
});
