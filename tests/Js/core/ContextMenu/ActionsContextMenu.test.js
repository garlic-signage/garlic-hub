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
import {DOMParser}                   from "linkedom";
import {createDom}                   from "@support/dom.js";
import {replaceGlobals}              from "@support/globals.js";
import {ActionsContextMenu}          from "@js/core/ContextMenu/ActionsContextMenu.js";
import {ActionsContextMenuView}      from "@js/core/ContextMenu/ActionsContextMenuView.js";

const PAGE = `
	<template id="menuTemplate"><ul class="context-menu"><li class="open">Open</li><li class="edit"><a href="#">Edit</a></li><li class="delete" data-confirm="Delete it?">Delete</li></ul></template>
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

function createPage(globalsToReplace = {})
{
	const dom     = createDom(PAGE);
	const globals = replaceGlobals({window: {scrollY: 100}, ...globalsToReplace});
	const errors  = [];

	return {
		document: dom.document, errors,
		menu:  () => new TestMenu(new ActionsContextMenuView('menuTemplate'), {showError: (message) => errors.push(message)}),
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

Deno.test('rights which cannot be loaded show the error and open no menu', async () =>
{
	using page = createPage();
	const menu = page.menu();
	menu.rights = {success: false, error_message: 'No access'};

	await menu.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="7"]'));

	assertEquals(page.errors, ['No access']);
	assertEquals(page.menus().length, 0);

	menu.flashMessageHandler.showError('for the modules');
	assertEquals(page.errors, ['No access', 'for the modules']);
});

Deno.test('a menu closed a second time does not forget the open one', async () =>
{
	using page = createPage();
	const [first, second, third] = [page.menu(), page.menu(), page.menu()];
	const button = page.document.querySelector('[data-action-id="7"]');

	await first.init({clientX: 0, clientY: 0}, button);
	await second.init({clientX: 0, clientY: 0}, button);
	first.close();
	await third.init({clientX: 0, clientY: 0}, button);

	assertEquals(page.menus().length, 1);
	assertEquals(page.menus()[0] === third.view.menu, true);
});

Deno.test('the view sets the link of an entry and removes entries, also missing ones', () =>
{
	using _page = createPage();
	const view = new ActionsContextMenuView('menuTemplate');

	view.setLink('edit', '/users/edit/7');
	view.remove('open');
	view.remove('missing');

	assertEquals(view.item('edit').querySelector('a').getAttribute('href'), '/users/edit/7');
	assertEquals(view.item('open'), null);
});

/**
 * A menu with the delete entry, opened for the unit 7. The answers of confirm and of the refresh come from the test.
 */
async function openDelete({canDelete = true, confirmed = true, deleted = {success: true}, refreshed = true, confirmText = null} = {})
{
	const asked   = [];
	const removed = [];
	const page    = createPage({
		confirm: (message) => { asked.push(message); return confirmed; },
		window:  {scrollY: 0, location: {href: 'https://hub.example.com/player'}, history: {replaceState: () => {}}},
		DOMParser,
		// the rows loaded again: the server has row 7 still, so a refresh keeps it
		fetch:   () => Promise.resolve({ok: refreshed, redirected: false,
			text: async () => '<ul class="results-listing"><li><ul data-id="7"></ul></li><li><ul data-id="8"></ul></li></ul>'})
	});
	const menu = page.menu();
	menu.prepare = () => menu.addDelete(canDelete, async (unitId) => { removed.push(unitId); return deleted; }, confirmText);
	await menu.init({clientX: 0, clientY: 0}, page.document.querySelector('[data-action-id="7"]'));

	return {page, menu, asked, removed};
}

Deno.test('without the right to delete the entry is gone', async () =>
{
	const {page, menu} = await openDelete({canDelete: false});
	using _page = page;

	assertEquals(menu.view.item('delete'), null);
	assertEquals(menu.view.item('open') !== null, true);
});

Deno.test('a delete asks first, with the text of the entry or the given one', async () =>
{
	for (const [confirmText, expected] of [[null, 'Delete it?'], ['Really?', 'Really?']])
	{
		const {page, menu, asked, removed} = await openDelete({confirmed: false, confirmText});
		using _page = page;

		const click = new Event('click', {cancelable: true});
		menu.view.item('delete').dispatchEvent(click);
		await flush();

		assertEquals(asked, [expected]);
		assertEquals(removed, []);
		assertEquals(click.defaultPrevented, true);
	}
});

Deno.test('a deleted unit loses its row only when the table cannot be loaded again', async () =>
{
	for (const [refreshed, rows] of [[false, ['8']], [true, ['7', '8']]])
	{
		const {page, menu, removed} = await openDelete({refreshed});
		using _page = page;

		menu.view.item('delete').dispatchEvent(new Event('click'));
		await flush();

		assertEquals(removed, [7]);
		assertEquals([...page.document.querySelectorAll('.results-listing ul[data-id]')].map(row => row.dataset.id), rows);
		assertEquals(page.errors, []);
	}
});

Deno.test('a row which is gone already is no error', async () =>
{
	const {page, menu, removed} = await openDelete({refreshed: false});
	using _page = page;
	page.document.querySelector('.results-listing ul[data-id="7"]').closest('li').remove();

	menu.view.item('delete').dispatchEvent(new Event('click'));
	await flush();

	assertEquals(removed, [7]);
	assertEquals(page.document.querySelectorAll('.results-listing ul[data-id]').length, 1);
});

Deno.test('a failed delete shows the error and keeps the row', async () =>
{
	const {page, menu} = await openDelete({deleted: {success: false, error_message: 'In use'}});
	using _page = page;

	menu.view.item('delete').dispatchEvent(new Event('click'));
	await flush();

	assertEquals(page.errors, ['In use']);
	assertEquals(page.document.querySelectorAll('.results-listing ul[data-id]').length, 2);
});

Deno.test('the delete entry ends with the menu', async () =>
{
	const {page, menu, asked} = await openDelete();
	using _page = page;

	menu.close();
	menu.view.item('delete').dispatchEvent(new Event('click'));
	await flush();

	assertEquals(asked, []);
});
