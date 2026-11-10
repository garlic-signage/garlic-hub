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
import {CreateContextMenu}            from "@js/playlists/overview/CreateContextMenu.js";

// the add button of templates/generic/datatable.mustache and the menu of templates/playlists/datatable.mustache
const PAGE = `
	<a href="/playlists/settings" class="add-unit" id="createUnitAction">Add</a>
	<template id="playlistContextMenuTemplate">
		<ul class="context-menu">
			<li><a href="/playlists/settings/master">Master</a></li>
			<li><a href="/playlists/settings/internal">Internal</a></li>
		</ul>
	</template>`;

function createPage()
{
	const dom    = createDom(PAGE);
	const button = dom.document.getElementById('createUnitAction');
	new CreateContextMenu(button, dom.document.getElementById('playlistContextMenuTemplate')).init();

	return {document: dom.document, button, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function clickAdd(page)
{
	const event = new Event('click', {bubbles: true, cancelable: true});
	Object.assign(event, {pageX: 120, pageY: 80});
	page.button.dispatchEvent(event);
	return event;
}

function menus(page)
{
	return [...page.document.querySelectorAll('body > .context-menu')];
}

Deno.test('the add button shows the kinds of playlists where the mouse is', () =>
{
	using page = createPage();

	const event = clickAdd(page);

	assertEquals(event.defaultPrevented, true);
	const [menu] = menus(page);
	assertEquals([menu.style.left, menu.style.top], ['120px', '80px']);
	assertEquals([...menu.querySelectorAll('a')].map(link => link.getAttribute('href')), ['/playlists/settings/master', '/playlists/settings/internal']);
});

Deno.test('the click of the button does not close its menu at once', () =>
{
	using page = createPage();
	let reached = 0;
	page.document.addEventListener('click', () => reached++);

	clickAdd(page);

	assertEquals(reached, 0);
	assertEquals(menus(page).length, 1);
});

Deno.test('the next click on the page closes the menu', () =>
{
	using page = createPage();
	clickAdd(page);

	page.document.dispatchEvent(new Event('click'));

	assertEquals(menus(page).length, 0);
});

Deno.test('the menu is shown once, also after a second click', () =>
{
	using page = createPage();
	clickAdd(page);
	const first = menus(page)[0];

	clickAdd(page);

	assertEquals(menus(page).length, 1);
	assertSameElement(menus(page)[0], first);
});
