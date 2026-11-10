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
import {PlaylistsSelectorView}        from "@js/playlists/compose/standard/selectors/PlaylistsSelectorView.js";

// templates/generic/playlists_selector.mustache
const PAGE = `
	<template id="playlistsSelectorTemplate">
		<label><input id="playlistsFilter" type="search" placeholder="Filter"></label>
		<section id="playlistsList" class="playlists-grid-view"></section>
	</template>
	<template id="playlistTemplate">
		<article class="playlist-select-item">
			<img src="/images/icons/playlist.svg" alt="Playlist icon">
			<span class="playlist-select-name"></span>
		</article>
	</template>
	<div id="itemSelectContainer"></div>`;

/**
 * The view reads its templates when it is created, the selector is shown in the page afterward.
 * The templates leave the page then: getElementById() of linkedom finds the elements inside a template, too.
 */
function createView()
{
	const dom  = createDom(PAGE);
	const view = new PlaylistsSelectorView();
	dom.document.querySelectorAll('template').forEach(template => template.remove());
	dom.document.getElementById('itemSelectContainer').replaceChildren(view.loadSelectorTemplate());

	return {document: dom.document, view, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function shownPlaylists(page)
{
	return [...page.document.querySelectorAll('#playlistsList .playlist-select-item')]
		.map(item => [item.getAttribute('data-select-id'), item.querySelector('.playlist-select-name').textContent]);
}

Deno.test('the selector is a new copy of the template', () =>
{
	using page = createView();

	const first  = page.view.loadSelectorTemplate();
	const second = page.view.loadSelectorTemplate();

	assertEquals(first.querySelector('#playlistsList') === second.querySelector('#playlistsList'), false);
	assertEquals(first.querySelectorAll('#playlistsFilter, #playlistsList').length, 2);
});

Deno.test('the playlists are shown with their name and the id to select them', () =>
{
	using page = createView();

	page.view.displayList([{id: 4, name: 'Lobby'}, {id: 9, name: 'Hall'}]);

	assertEquals(shownPlaylists(page), [['4', 'Lobby'], ['9', 'Hall']]);
	assertEquals(page.document.querySelector('.playlist-select-item img').getAttribute('src'), '/images/icons/playlist.svg');
});

Deno.test('the next list replaces the playlists before', () =>
{
	using page = createView();
	page.view.displayList([{id: 4, name: 'Lobby'}]);

	page.view.displayList([{id: 9, name: 'Hall'}]);

	assertEquals(shownPlaylists(page), [['9', 'Hall']]);
});

Deno.test('a name is shown as text', () =>
{
	using page = createView();

	page.view.displayList([{id: 4, name: '<b>Lobby</b>'}]);

	assertEquals(shownPlaylists(page), [['4', '<b>Lobby</b>']]);
	assertSameElement(page.document.querySelector('#playlistsList b'), null);
});

Deno.test('an empty list empties the selector', () =>
{
	using page = createView();
	page.view.displayList([{id: 4, name: 'Lobby'}]);

	page.view.displayList([]);

	assertEquals(shownPlaylists(page), []);
	assertSameElement(page.document.getElementById('playlistsList').parentElement, page.document.getElementById('itemSelectContainer'));
});
