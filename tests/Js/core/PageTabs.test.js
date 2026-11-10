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
import {assertEquals}   from "@std/assert";
import {createDom}      from "@support/dom.js";
import {replaceGlobals} from "@support/globals.js";
import {PageTabs}       from "@js/core/PageTabs.js";

const PAGE = `
	<section id="page" class="page-tabs">
		<button class="tab-button" data-tab="playlists">Playlists</button>
		<button class="tab-button" data-tab="player 2">Player</button>
		<section class="tab-content" data-module="playlists"></section>
		<section class="tab-content" data-module="player 2"></section>
	</section>`;

function createPage(hash)
{
	const dom      = createDom(PAGE);
	const replaced = [];
	const globals  = replaceGlobals({location: {hash}, history: {replaceState: (state, title, url) => replaced.push(url)}});
	PageTabs.init(dom.document.getElementById('page'));

	return {
		document: dom.document, replaced,
		active: () => [...dom.document.querySelectorAll('.active')].map(element => element.dataset.tab ?? element.dataset.module),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('without a tab in the url the first is open', () =>
{
	using page = createPage('');

	assertEquals(page.active(), ['playlists', 'playlists']);
});

Deno.test('the tab of the url is open, also with a space in its name', () =>
{
	using page = createPage('#player%202');

	assertEquals(page.active(), ['player 2', 'player 2']);
});

Deno.test('an unknown tab in the url opens the first', () =>
{
	using page = createPage('#nothing');

	assertEquals(page.active(), ['playlists', 'playlists']);
});

Deno.test('a click opens its tab and writes it into the url', () =>
{
	using page = createPage('');

	page.document.querySelector('[data-tab="player 2"]').dispatchEvent(new Event('click'));

	assertEquals(page.active(), ['player 2', 'player 2']);
	assertEquals(page.replaced, ['#player 2']);
});

Deno.test('a page without tabs is no error', () =>
{
	using dom      = createDom('<section id="page"></section>');
	using _globals = replaceGlobals({location: {hash: ''}, history: {replaceState: () => {}}});

	PageTabs.init(dom.document.getElementById('page'));

	assertEquals(dom.document.querySelectorAll('.active').length, 0);
});
