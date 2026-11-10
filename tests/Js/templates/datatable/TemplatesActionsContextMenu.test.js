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
import {assertEquals, assertInstanceOf}     from "@std/assert";
import {stub}                               from "@std/testing/mock";
import {assertSameElement, createDom}       from "@support/dom.js";
import {replaceGlobals}                     from "@support/globals.js";
import {TemplatesActionsContextMenu}        from "@js/templates/datatable/TemplatesActionsContextMenu.js";
import {TemplatesActionsContextMenuFactory} from "@js/templates/datatable/TemplatesActionsContextMenuFactory.js";

// the menu of templates/templates/datatable.mustache and a row of the datatable
const PAGE = `
	<template id="templatesActionsContextMenuTemplate">
		<ul class="context-menu">
			<li class="composer"><a href="">Compose</a></li>
			<li class="settings"><a href="">Settings</a></li>
			<li class="copy">Copy</li>
			<li class="share">Share</li>
			<li class="versions">Versions</li>
			<li class="delete" data-confirm="Delete the template?">Delete</li>
		</ul>
	</template>
	<ul class="results-listing">
		<li><ul data-id="7"><li><button class="template-contextmenu" data-action-id="7">…</button></li></ul></li>
	</ul>`;

const ALL_RIGHTS = {can_edit: true, can_copy: true, can_share: true, can_version: true, can_delete: true};

/**
 * Answers with the rights, the copy and the delete result set by the test.
 */
class FakeTemplatesService
{
	calls   = [];
	rights  = ALL_RIGHTS;
	copied  = {success: true, template_id: 12};
	deleted = {success: true};

	async determineRights(templateId)
	{
		this.calls.push(['determineRights', templateId]);
		return this.rights;
	}

	async copy(templateId)
	{
		this.calls.push(['copy', templateId]);
		return this.copied;
	}

	async delete(templateId)
	{
		this.calls.push(['delete', templateId]);
		return this.deleted;
	}
}

/**
 * The share and the versions dialog take their entry, without the right they remove it.
 */
class FakeUnitDialog
{
	attached = [];

	attachTo(menuItem, unitId, allowed)
	{
		this.attached.push([menuItem, unitId, allowed]);
		if (!allowed)
			menuItem.remove();
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	// the datatable cannot be loaded again: the row of the template is removed instead
	const location = {href: 'https://hub.example.com/templates'};
	const globals  = replaceGlobals({
		window: {scrollY: 0, location},
		fetch:  () => Promise.reject(new Error('offline'))
	});
	const service  = new FakeTemplatesService();
	const share    = new FakeUnitDialog();
	const versions = new FakeUnitDialog();
	const errors   = [];
	const factory  = new TemplatesActionsContextMenuFactory({showError: (message) => errors.push(message)}, service, share, versions);

	return {
		document: dom.document, service, share, versions, errors, factory, location,
		button: dom.document.querySelector('.template-contextmenu'),
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

async function click(page, className)
{
	const event = new Event('click', {cancelable: true});
	page.document.querySelector(`body > .context-menu .${className}`).dispatchEvent(event);
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
	return event;
}

Deno.test('the factory builds the menu of the templates from its template', () =>
{
	using page = createPage();

	const menu = page.factory.create();

	assertInstanceOf(menu, TemplatesActionsContextMenu);
	assertEquals(menu.view.menu.querySelectorAll('li').length, 6);
});

Deno.test('the rights of the template are asked for the clicked row', async () =>
{
	using page = createPage();

	const menu = await open(page);

	assertEquals(page.service.calls, [['determineRights', 7]]);
	assertEquals(menu.unitId, 7);
});

Deno.test('with all rights the menu links the composer and the settings of the template', async () =>
{
	using page = createPage();

	await open(page);

	assertEquals(entries(page), ['composer', 'settings', 'copy', 'share', 'versions', 'delete']);
	const links = [...page.document.querySelectorAll('body > .context-menu a')].map(link => link.getAttribute('href'));
	assertEquals(links, ['/templates/composer/7', '/templates/settings/7']);
});

Deno.test('share and versions get their entry, the template and the right', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_share: false, can_version: true};

	await open(page);

	const [[versionsItem, versionsId, canVersion]] = page.versions.attached;
	assertEquals(versionsItem.className, 'versions');
	assertEquals([versionsId, canVersion], [7, true]);
	assertEquals(page.share.attached.map(([, id, allowed]) => [id, allowed]), [[7, false]]);
	assertEquals(entries(page), ['composer', 'settings', 'copy', 'versions', 'delete']);
});

Deno.test('without the right to copy or to delete their entries go', async () =>
{
	for (const [right, entry] of [['can_copy', 'copy'], ['can_delete', 'delete']])
	{
		using page = createPage();
		page.service.rights = {...ALL_RIGHTS, [right]: false};

		await open(page);

		assertEquals(entries(page).includes(entry), false, right);
	}
});

Deno.test('the copy is offered only with the right set to true', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_copy: 1};

	await open(page);

	assertEquals(entries(page).includes('copy'), false);
});

Deno.test('without the right to edit there is no menu', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_edit: false};

	await open(page);

	assertEquals(entries(page), []);
});

Deno.test('rights that could not be loaded show the error', async () =>
{
	using page = createPage();
	page.service.rights = {success: false, error_message: 'Not allowed'};

	await open(page);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(entries(page), []);
});

Deno.test('the copy opens in the composer', async () =>
{
	using page = createPage();
	await open(page);

	const event = await click(page, 'copy');

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.service.calls, [['determineRights', 7], ['copy', 7]]);
	assertEquals(page.location.href, '/templates/composer/12');
});

Deno.test('a refused copy shows the error and stays on the page', async () =>
{
	using page = createPage();
	page.service.copied = {success: false, error_message: 'Not allowed to copy'};
	await open(page);

	await click(page, 'copy');

	assertEquals(page.errors, ['Not allowed to copy']);
	assertEquals(page.location.href, 'https://hub.example.com/templates');
});

Deno.test('the delete asks with the text of the entry', async () =>
{
	using page        = createPage();
	using confirmStub = stub(globalThis, 'confirm', () => false);
	await open(page);

	await click(page, 'delete');

	assertEquals(confirmStub.calls[0].args, ['Delete the template?']);
	assertEquals(page.service.calls, [['determineRights', 7]]);
});

Deno.test('a confirmed delete removes the template and its row', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	await open(page);

	await click(page, 'delete');

	assertEquals(page.service.calls, [['determineRights', 7], ['delete', 7]]);
	assertSameElement(page.document.querySelector('ul[data-id="7"]'), null);
});

Deno.test('a refused delete shows the error and keeps the row', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	page.service.deleted = {success: false, error_message: 'Still in use'};
	await open(page);

	await click(page, 'delete');

	assertEquals(page.errors, ['Still in use']);
	assertSameElement(page.document.querySelector('ul[data-id="7"] button'), page.button);
});
