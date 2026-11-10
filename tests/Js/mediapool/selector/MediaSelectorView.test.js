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
import {MediaSelectorView}            from "@js/mediapool/selector/MediaSelectorView.js";

// the template of templates/generic/media_selector.mustache
const PAGE = `
	<template id="mediaSelectorTemplate">
		<div class="mediaSelectContainer">
			<aside><div id="mediapoolTree"></div></aside>
			<section id="mediaList" class="media-list-grid-view"></section>
		</div>
	</template>
	<div id="dialog"></div>`;

/**
 * Builds media like the MediaFactory: renderSimple() gives the article.
 */
class FakeMediaFactory
{
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(mediaData)
	{
		const document = this.#document;
		return {
			mediaId: mediaData.media_id,
			renderSimple()
			{
				const article = document.createElement('article');
				article.className = 'media-item';
				article.setAttribute('data-media-id', mediaData.media_id);
				return article;
			}
		};
	}
}

/**
 * The view is created when the template is in the page, the selector is shown in the dialog afterward.
 * The template leaves the page then: getElementById() of linkedom finds the elements inside a template, too.
 */
function createView()
{
	const dom  = createDom(PAGE);
	const view = new MediaSelectorView(new FakeMediaFactory(dom.document));
	dom.document.getElementById('mediaSelectorTemplate').remove();
	dom.document.getElementById('dialog').replaceChildren(view.loadSelectorTemplate());

	return {document: dom.document, view, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function shownItems(page)
{
	return [...page.document.querySelectorAll('#dialog #mediaList .media-item')]
		.map(item => [item.getAttribute('data-media-id'), item.getAttribute('data-select-id')]);
}

Deno.test('the template is a new copy for every selector', () =>
{
	using page = createView();

	const first  = page.view.loadSelectorTemplate();
	const second = page.view.loadSelectorTemplate();

	assertEquals(first.querySelector('.mediaSelectContainer') === second.querySelector('.mediaSelectContainer'), false);
	assertEquals(first.querySelectorAll('#mediapoolTree, #mediaList').length, 2);
});

Deno.test('the list is the one of the selector in the page', () =>
{
	using page = createView();

	assertSameElement(page.view.getMediaListElement(), page.document.querySelector('#dialog #mediaList'));
});

Deno.test('the media are shown with their id to select them', () =>
{
	using page = createView();

	page.view.displayMediaList([{media_id: 'a'}, {media_id: 'b'}]);

	assertEquals(shownItems(page), [['a', 'a'], ['b', 'b']]);
	assertEquals(Object.keys(page.view.mediaItems), ['a', 'b']);
	assertEquals(page.view.mediaItems.a.mediaId, 'a');
});

Deno.test('the next directory replaces the media in the list, the media stay known', () =>
{
	using page = createView();

	page.view.displayMediaList([{media_id: 'a'}, {media_id: 'b'}]);
	page.view.displayMediaList([{media_id: 'c'}]);

	assertEquals(shownItems(page), [['c', 'c']]);
	assertEquals(Object.keys(page.view.mediaItems), ['a', 'b', 'c']);
});

Deno.test('an empty directory empties the list', () =>
{
	using page = createView();
	page.view.displayMediaList([{media_id: 'a'}]);

	page.view.displayMediaList([]);

	assertEquals(shownItems(page), []);
});
