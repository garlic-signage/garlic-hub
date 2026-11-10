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
import {ContextMenuView} from "@js/playlists/compose/multizone/Views/ContextMenuView.js";

// the menu of templates/playlists/multizone.mustache
const PAGE = `
	<template id="context-menu">
		<ul class="context-menu">
			<li id="duplicate_zone">Duplicate</li>
			<li id="delete_zone">Delete</li>
			<li id="move_background">Background</li>
		</ul>
	</template>`;

function menus(document)
{
	return [...document.body.querySelectorAll(':scope > div')];
}

Deno.test('the menu is shown where the mouse was clicked', () =>
{
	using dom  = createDom(PAGE);
	const view = new ContextMenuView();

	view.show(120, 80);

	const [menu] = menus(dom.document);
	assertEquals([menu.style.position, menu.style.left, menu.style.top, String(menu.style.zIndex)], ['absolute', '120px', '80px', '1000']);
	assertEquals([...menu.querySelectorAll('li')].map(item => item.id), ['duplicate_zone', 'delete_zone', 'move_background']);
});

Deno.test('the menu has no menu of the browser', () =>
{
	using dom  = createDom(PAGE);
	const view = new ContextMenuView();
	view.show(0, 0);

	const event = new Event('contextmenu', {cancelable: true});
	menus(dom.document)[0].dispatchEvent(event);

	assertEquals(event.defaultPrevented, true);
});

Deno.test('a click on an entry calls its handler', () =>
{
	using dom   = createDom(PAGE);
	const view  = new ContextMenuView();
	const calls = [];
	view.show(0, 0);

	view.onClick('delete_zone', () => calls.push('delete'));
	// getElementById() of linkedom finds the entry in the template first
	menus(dom.document)[0].querySelector('#delete_zone').dispatchEvent(new Event('click'));

	assertEquals(calls, ['delete']);
});

Deno.test('the menu is removed, also when none is shown', () =>
{
	using dom  = createDom(PAGE);
	const view = new ContextMenuView();

	view.remove();
	view.show(0, 0);
	view.remove();
	view.remove();

	assertEquals(menus(dom.document), []);
});

Deno.test('every menu is a new copy of the template', () =>
{
	using dom  = createDom(PAGE);
	const view = new ContextMenuView();

	view.show(0, 0);
	view.remove();
	view.show(10, 10);

	assertEquals(menus(dom.document).length, 1);
	assertEquals(dom.document.getElementById('context-menu').content.querySelectorAll('li').length, 3);
});
