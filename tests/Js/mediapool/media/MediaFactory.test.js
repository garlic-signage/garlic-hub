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

import {assertEquals, assertInstanceOf} from "@std/assert";
import {createDom}                      from "@support/dom.js";

// MediaFactory imports Media, it loads UploadDialogElements, which reads the document when it is loaded
let MediaFactory, Media;
{
	using _dom = createDom('<button id="openUploadDialog"></button>');
	({MediaFactory} = await import("@js/mediapool/media/MediaFactory.js"));
	({Media}        = await import("@js/mediapool/media/Media.js"));
}

// the template of templates/mediapool/overview.mustache
const PAGE = `
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
	</template>
	<template id="otherTemplate">
		<article class="media-item other" draggable="false">
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

function mediaData(id)
{
	return {media_id: id, checksum: 'f00d', extension: 'jpg', filename: 'Lobby', mimetype: 'image/jpeg',
		urls: {thumbnail: '/thumb/' + id, original: '/original/' + id}, metadata: {size: 1}};
}

Deno.test('a medium is built from its data', () =>
{
	using dom     = createDom(PAGE);
	const factory = new MediaFactory(dom.document.getElementById('mediaTemplate'));

	const media = factory.create(mediaData('abc'));

	assertInstanceOf(media, Media);
	assertEquals(media.mediaId, 'abc');
	assertEquals(media.renderSimple().getAttribute('data-media-id'), 'abc');
});

Deno.test('every medium gets its own copy of the template', () =>
{
	using dom      = createDom(PAGE);
	const template = dom.document.getElementById('mediaTemplate');
	const factory  = new MediaFactory(template);

	const first  = factory.create(mediaData('a')).renderSimple();
	const second = factory.create(mediaData('b')).renderSimple();

	assertEquals(first === second, false);
	assertEquals([first.getAttribute('data-media-id'), second.getAttribute('data-media-id')], ['a', 'b']);
	assertEquals(template.content.querySelector('.media-item').hasAttribute('data-media-id'), false);
});

Deno.test('the template can be exchanged', () =>
{
	using dom     = createDom(PAGE);
	const factory = new MediaFactory(dom.document.getElementById('mediaTemplate'));

	factory.mediaTemplate = dom.document.getElementById('otherTemplate');

	assertEquals(factory.create(mediaData('a')).renderSimple().classList.contains('other'), true);
});
