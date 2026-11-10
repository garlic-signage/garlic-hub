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
import {stub}                           from "@std/testing/mock";
import {createDom}                      from "@support/dom.js";
import {replaceGlobals}                 from "@support/globals.js";
import {UsersActionsContextMenu}        from "@js/users/datatable/UsersActionsContextMenu.js";
import {UsersActionsContextMenuFactory} from "@js/users/datatable/UsersActionsContextMenuFactory.js";

// the menu of templates/users/datatable.mustache and a row of the datatable
const PAGE = `
	<template id="usersActionsContextMenuTemplate">
		<ul class="context-menu">
			<li class="edit"><a href="">Edit</a></li>
			<li class="rights"><a href="">Rights</a></li>
			<li class="delete" data-confirm="Delete the user?">Delete</li>
		</ul>
	</template>
	<ul class="results-listing">
		<li><ul class="results-body" data-id="7"><li><button class="user-contextmenu" data-action-id="7">…</button></li></ul></li>
	</ul>`;

const ALL_RIGHTS = {can_edit: true, can_rights: true, can_delete: true};

class FakeUserService
{
	calls   = [];
	rights  = ALL_RIGHTS;
	deleted = {success: true};

	async determineRights(UID)
	{
		this.calls.push(['determineRights', UID]);
		return this.rights;
	}

	async deleteUser(UID)
	{
		this.calls.push(['deleteUser', UID]);
		return this.deleted;
	}
}

function createPage()
{
	const dom     = createDom(PAGE);
	// the datatable cannot be loaded again: the row of the user is removed instead
	const globals = replaceGlobals({window: {scrollY: 0, location: {href: 'https://hub.example.com/users'}}, fetch: () => Promise.reject(new Error('offline'))});
	const service = new FakeUserService();
	const errors  = [];
	const factory = new UsersActionsContextMenuFactory({showError: (message) => errors.push(message)}, service);

	return {
		document: dom.document, service, errors, factory,
		button: dom.document.querySelector('.user-contextmenu'),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

async function open(page)
{
	const menu = page.factory.create();
	await menu.init({clientX: 300, clientY: 40}, page.button);
	return menu;
}

function entries(page)
{
	return [...page.document.querySelectorAll('body > .context-menu li')].map(item => item.className);
}

function links(page)
{
	return [...page.document.querySelectorAll('body > .context-menu a')].map(link => link.getAttribute('href'));
}

Deno.test('the factory builds the menu of the users from its template', () =>
{
	using page = createPage();

	const menu = page.factory.create();

	assertInstanceOf(menu, UsersActionsContextMenu);
	assertEquals(menu.view.menu.querySelectorAll('li').length, 3);
});

Deno.test('with all rights the menu links the form and the rights of the clicked user', async () =>
{
	using page = createPage();

	const menu = await open(page);

	assertEquals(page.service.calls, [['determineRights', 7]]);
	assertEquals(menu.unitId, 7);
	assertEquals(entries(page), ['edit', 'rights', 'delete']);
	assertEquals(links(page), ['/users/edit/7', '/users/rights/7']);
});

Deno.test('each right alone shows its entry and the menu', async (t) =>
{
	const cases = {
		can_edit:   ['edit'],
		can_rights: ['rights'],
		can_delete: ['delete'],
	};

	for (const [right, expected] of Object.entries(cases))
	{
		await t.step(right, async () =>
		{
			using page = createPage();
			page.service.rights = {can_edit: false, can_rights: false, can_delete: false, [right]: true};

			await open(page);

			assertEquals(entries(page), expected);
		});
	}
});

Deno.test('without any right there is no menu, also not for rights which are only truthy', async () =>
{
	for (const rights of [{can_edit: false, can_rights: false, can_delete: false}, {can_edit: 1, can_rights: 'yes', can_delete: 1}])
	{
		using page = createPage();
		page.service.rights = rights;

		await open(page);

		assertEquals(entries(page), []);
	}
});

Deno.test('rights that could not be loaded show the error', async () =>
{
	using page = createPage();
	page.service.rights = {success: false, error_message: 'Not allowed'};

	await open(page);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(entries(page), []);
});

Deno.test('a confirmed delete deletes the user and its row', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	await open(page);

	page.document.querySelector('body > .context-menu .delete').dispatchEvent(new Event('click', {cancelable: true}));
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.service.calls.at(-1), ['deleteUser', 7]);
	assertEquals(page.document.querySelector('ul[data-id="7"]'), null);
});
