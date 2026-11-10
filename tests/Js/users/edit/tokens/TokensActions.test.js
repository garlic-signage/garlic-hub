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
import {TokensActions}  from "@js/users/edit/tokens/TokensActions.js";
import {TokensView}     from "@js/users/edit/tokens/TokensView.js";

// the user form with a link of the initial password (ClipboardTextRenderer) and the id of the user
const PAGE = `
	<input type="hidden" id="UID" value="7">
	<input type="text" id="initial_password" class="verification-link" value="https://hub.example.com/force-password?token=old&amp;lang=de" readonly>
	<button type="button" data-id="initial_password" class="copy-verification-link">Copy</button>
	<button type="button" data-id="initial_password" class="delete-verification-link">Delete</button>
	<button type="button" data-id="initial_password" class="refresh-verification-link">Refresh</button>`;

/**
 * Answers with the results set by the test.
 */
class FakeTokensService
{
	calls     = [];
	deleted   = {success: true};
	refreshed = {success: true, token: 'new'};

	async delete(UID, purpose)
	{
		this.calls.push(['delete', UID, purpose]);
		return this.deleted;
	}

	async refresh(UID, purpose)
	{
		this.calls.push(['refresh', UID, purpose]);
		return this.refreshed;
	}
}

function createPage(clipboard = () => Promise.resolve())
{
	const dom      = createDom(PAGE);
	const reloads  = [];
	const copied   = [];
	const globals  = replaceGlobals({
		location:  {reload: () => reloads.push(true)},
		navigator: {clipboard: {writeText: (text) => { copied.push(text); return clipboard(); }}}
	});
	const service  = new FakeTokensService();
	const errors   = [];
	new TokensActions(new TokensView(), {showError: (message) => errors.push(message)}, service).initActions();

	return {
		document: dom.document, service, errors, reloads, copied,
		field:  dom.document.getElementById('initial_password'),
		button: (action) => dom.document.querySelector(`.${action}-verification-link`),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

async function click(element, wait = 0)
{
	element.dispatchEvent(new Event('click'));
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
	if (wait > 0)
		await new Promise(resolve => setTimeout(resolve, wait));
}

Deno.test('the link is copied and its field flashes green', async () =>
{
	using page = createPage();
	page.field.style.backgroundColor = 'white';

	await click(page.button('copy'));

	assertEquals(page.copied, ['https://hub.example.com/force-password?token=old&lang=de']);
	assertEquals(page.field.style.backgroundColor, 'lightgreen');

	await new Promise(resolve => setTimeout(resolve, 150));
	assertEquals(page.field.style.backgroundColor, 'white');
});

Deno.test('a link which cannot be copied turns its button red', async () =>
{
	using page = createPage(() => Promise.reject(new Error('denied')));

	await click(page.button('copy'));

	assertEquals(page.button('copy').style.backgroundColor, 'red');
});

Deno.test('a deleted link reloads the form of the user', async () =>
{
	using page = createPage();

	await click(page.button('delete'));

	assertEquals(page.service.calls, [['delete', '7', 'initial_password']]);
	assertEquals(page.reloads.length, 1);
});

Deno.test('a refused delete shows the error and stays', async () =>
{
	using page = createPage();
	page.service.deleted = {success: false, error_message: 'Not allowed'};

	await click(page.button('delete'));

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(page.reloads, []);
});

Deno.test('a new token replaces the one of the link, the rest of the link stays', async () =>
{
	using page = createPage();

	await click(page.button('refresh'));

	assertEquals(page.service.calls, [['refresh', '7', 'initial_password']]);
	assertEquals(page.field.value, 'https://hub.example.com/force-password?token=new&lang=de');
});

Deno.test('a refused refresh shows the error and keeps the link', async () =>
{
	using page = createPage();
	page.service.refreshed = {success: false, error_message: 'Not allowed'};

	await click(page.button('refresh'));

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(page.field.value, 'https://hub.example.com/force-password?token=old&lang=de');
});
