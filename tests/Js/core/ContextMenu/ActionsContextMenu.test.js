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
import {assertEquals, assertRejects} from "@std/assert";
import {createDom}                   from "@support/dom.js";
import {replaceGlobals}              from "@support/globals.js";
import {ActionsContextMenu}          from "@js/core/ContextMenu/ActionsContextMenu.js";
import {ActionsContextMenuView}      from "@js/core/ContextMenu/ActionsContextMenuView.js";

const PAGE = `
	<template id="menuTemplate"><ul class="context-menu"><li class="open">Open</li></ul></template>
	<ul class="results-listing">
		<li><ul data-id="7"><li><button class="unit-contextmenu" data-action-id="7"><i class="icon">…</i></button></li></ul></li>
		<li><ul data-id="8"><li><button class="unit-contextmenu" data-action-id="8">…</button></li></ul></li>
	</ul>`;

/**
 * A menu of a module: its rights are the ones set by the test, an entry counts the clicks while the menu is open.
 */
class TestMenu extends ActionsContextMenu
{
	rights = {can_edit: true};
	clicks = 0;

	async determineRights()
	{
		return this.rights;
	}

	prepare()
	{
		this.view.item('open').addEventListener('click', () => this.clicks++, {signal: this.signal});
	}
}

function createPage()
{
	const dom     = createDom(PAGE);
	const globals = replaceGlobals({window: {scrollY: 100}});

	return {
		document: dom.document,
		menu:  () => new TestMenu(new ActionsContextMenuView('menuTemplate'), {showError: () => {}}),
		menus: () => dom.document.querySelectorAll('body > .context-menu'),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

async function flush()
{
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('a click on a button of the class opens a menu for its row, also on the icon inside', async () =>
{
	using page = createPage();
	const created = [];
	ActionsContextMenu.bindTo('unit-contextmenu', () => { const menu = page.menu(); created.push(menu); return menu; });

	const event = new Event('click', {bubbles: true, cancelable: true});
	page.document.querySelector('.icon').dispatchEvent(event);
	await flush();

	assertEquals(created.map(menu => menu.unitId), [7]);
	assertEquals(event.defaultPrevented, true);
	assertEquals(page.menus().length, 1);
});

Deno.test('a click elsewhere opens no menu', async () =>
{
	using page = createPage();
	let created = 0;
	ActionsContextMenu.bindTo('other-contextmenu', () => { created++; return page.menu(); });

	page.document.querySelector('.results-listing').dispatchEvent(new Event('click', {bubbles: true}));
	await flush();

	assertEquals(created, 0);
});

Deno.test('the menu lies left of the click, below it on the scrolled page', async () =>
{
	using page = createPage();
	const menu = page.menu();
	Object.defineProperty(menu.view.menu, 'offsetWidth', {value: 120});

	await menu.init({clientX: 300, clientY: 40}, page.document.querySelector('[data-action-id="7"]'));

	assertEquals([menu.view.menu.style.left, menu.view.menu.style.top], ['180px', '140px']);
});

Deno.test('a second menu closes the first', async () =>
{
	using page = createPage();
	const first  = page.menu();
	const second = page.menu();

	await first.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="7"]'));
	await second.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="8"]'));

	assertEquals(page.menus().length, 1);
	assertEquals(second.unitId, 8);
});

Deno.test('the next click closes the menu and ends the listeners of its entries', async () =>
{
	using page = createPage();
	const menu  = page.menu();
	await menu.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="7"]'));
	const entry = menu.view.item('open');

	entry.dispatchEvent(new Event('click'));
	page.document.dispatchEvent(new Event('click'));
	entry.dispatchEvent(new Event('click'));

	assertEquals(menu.clicks, 1);
	assertEquals(page.menus().length, 0);
});

Deno.test('without the right to edit no menu opens', async () =>
{
	using page = createPage();
	const menu = page.menu();
	menu.rights = {can_edit: 1};

	await menu.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="7"]'));

	assertEquals(page.menus().length, 0);
});

Deno.test('a module must give its rights and its entries', async () =>
{
	using _page = createPage();
	const menu = new ActionsContextMenu(new ActionsContextMenuView('menuTemplate'), {showError: () => {}});

	await assertRejects(() => menu.determineRights(7), Error, 'determineRights() is not implemented for unit 7');
	try
	{
		menu.prepare({});
		throw new Error('prepare() must fail');
	}
	catch (error)
	{
		assertEquals(error.message, 'prepare() is not implemented');
	}
});
