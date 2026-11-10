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
import {PlaylistsSelector}            from "@js/playlists/compose/standard/selectors/PlaylistsSelector.js";

const PAGE = '<div id="itemSelectContainer"></div>';

/**
 * Answers with the playlists set by the test, or fails with them when they are an Error.
 */
class FakePlaylistsService
{
	calls  = [];
	answer = [{id: 4, name: 'Lobby'}];

	async findPlaylists(mode, name)
	{
		this.calls.push([mode, name]);
		if (this.answer instanceof Error)
			throw this.answer;

		return this.answer;
	}
}

/**
 * The selector of the page: its list is the section of templates/generic/playlists_selector.mustache.
 */
class FakeView
{
	displayed = [];
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	loadSelectorTemplate()
	{
		const fragment = this.#document.createDocumentFragment();
		const list     = this.#document.createElement('section');
		list.id        = 'playlistsList';
		fragment.appendChild(list);
		return fragment;
	}

	displayList(items)
	{
		this.displayed.push(items);
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	const service  = new FakePlaylistsService();
	const view     = new FakeView(dom.document);
	const selector = new PlaylistsSelector(service, view);

	return {
		document: dom.document, service, view, selector,
		container: dom.document.getElementById('itemSelectContainer'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('the selector replaces the content of the container and shows the internal playlists', async () =>
{
	using page = createPage();
	page.container.innerHTML = '<p>media</p>';

	await page.selector.showSelector(page.container);

	assertEquals(page.container.querySelector('p'), null);
	assertSameElement(page.selector.getItemsContainer(), page.container.querySelector('#playlistsList'));
	assertEquals(page.service.calls, [['internal', '']]);
	assertEquals(page.view.displayed, [[{id: 4, name: 'Lobby'}]]);
});

Deno.test('the found playlists are the items to drag', async () =>
{
	using page = createPage();
	assertEquals(page.selector.items, null);

	await page.selector.showSelector(page.container);

	assertEquals(page.selector.items, [{id: 4, name: 'Lobby'}]);
});

Deno.test('a failed search shows no list', async () =>
{
	using page = createPage();
	page.service.answer = new Error('Unexpected end of JSON input');

	await assertRejects(() => page.selector.showSelector(page.container), Error, 'Unexpected end of JSON input');

	assertEquals(page.view.displayed, []);
});
