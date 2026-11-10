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
import {PlaylistsActionsContextMenu}        from "@js/playlists/overview/PlaylistsActionsContextMenu.js";
import {PlaylistsActionsContextMenuFactory} from "@js/playlists/overview/PlaylistsActionsContextMenuFactory.js";

// the menu of templates/playlists/datatable.mustache and a row of the datatable
const PAGE = `
	<template id="playlistsActionsContextMenuTemplate">
		<ul class="context-menu">
			<li class="composer"><a href="">Compose</a></li>
			<li class="settings"><a href="">Settings</a></li>
			<li class="share">Share</li>
			<li class="versions">Versions</li>
			<li class="delete" data-confirm="Delete the playlist %s?">Delete</li>
		</ul>
	</template>
	<ul class="results-listing">
		<li><ul data-id="7"><li><button class="playlist-contextmenu" data-action-id="7">…</button></li></ul></li>
	</ul>`;

const ALL_RIGHTS = {can_edit: true, can_share: true, can_version: true, can_delete: true, playlist_name: 'Lobby'};

/**
 * Answers with the rights and the delete result set by the test.
 */
class FakePlaylistsService
{
	calls   = [];
	rights  = ALL_RIGHTS;
	deleted = {success: true};

	async determineRights(playlistId)
	{
		this.calls.push(['determineRights', playlistId]);
		return this.rights;
	}

	async delete(playlistId)
	{
		this.calls.push(['delete', playlistId]);
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
	// the datatable cannot be loaded again: the row of the playlist is removed instead
	const globals  = replaceGlobals({
		window: {scrollY: 0, location: {href: 'https://hub.example.com/playlists'}},
		fetch:  () => Promise.reject(new Error('offline'))
	});
	const service  = new FakePlaylistsService();
	const share    = new FakeUnitDialog();
	const versions = new FakeUnitDialog();
	const errors   = [];
	const factory  = new PlaylistsActionsContextMenuFactory({showError: (message) => errors.push(message)}, service, share, versions);

	return {
		document: dom.document, service, share, versions, errors, factory,
		button: dom.document.querySelector('.playlist-contextmenu'),
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

async function clickDelete(page)
{
	page.document.querySelector('body > .context-menu .delete').dispatchEvent(new Event('click', {cancelable: true}));
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the factory builds the menu of the playlists from its template', () =>
{
	using page = createPage();

	const menu = page.factory.create();

	assertInstanceOf(menu, PlaylistsActionsContextMenu);
	assertEquals(menu.view.menu.querySelectorAll('li').length, 5);
});

Deno.test('the rights of the playlist are asked for the clicked row', async () =>
{
	using page = createPage();

	const menu = await open(page);

	assertEquals(page.service.calls, [['determineRights', 7]]);
	assertEquals(menu.unitId, 7);
});

Deno.test('with all rights the menu links the composer and the settings of the playlist', async () =>
{
	using page = createPage();

	await open(page);

	assertEquals(entries(page), ['composer', 'settings', 'share', 'versions', 'delete']);
	const links = [...page.document.querySelectorAll('body > .context-menu a')].map(link => link.getAttribute('href'));
	assertEquals(links, ['/playlists/compose/7', '/playlists/settings/7']);
});

Deno.test('share and versions get their entry, the playlist and the right', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_share: true, can_version: false};

	await open(page);

	const [[shareItem, shareId, canShare]] = page.share.attached;
	assertEquals(shareItem.className, 'share');
	assertEquals([shareId, canShare], [7, true]);
	assertEquals(page.versions.attached.map(([, id, allowed]) => [id, allowed]), [[7, false]]);
	assertEquals(entries(page), ['composer', 'settings', 'share', 'delete']);
});

Deno.test('without the right to delete there is no delete', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_delete: false};

	await open(page);

	assertEquals(entries(page).includes('delete'), false);
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

Deno.test('the delete asks with the name of the playlist', async () =>
{
	using page        = createPage();
	using confirmStub = stub(globalThis, 'confirm', () => false);
	await open(page);

	await clickDelete(page);

	assertEquals(confirmStub.calls[0].args, ['Delete the playlist Lobby?']);
	assertEquals(page.service.calls, [['determineRights', 7]]);
});

Deno.test('a playlist without a name is asked for with an empty name', async () =>
{
	using page        = createPage();
	using confirmStub = stub(globalThis, 'confirm', () => false);
	page.service.rights = {...ALL_RIGHTS, playlist_name: undefined};
	await open(page);

	await clickDelete(page);

	assertEquals(confirmStub.calls[0].args, ['Delete the playlist ?']);
});

Deno.test('a confirmed delete removes the playlist and its row', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	await open(page);

	await clickDelete(page);

	assertEquals(page.service.calls, [['determineRights', 7], ['delete', 7]]);
	assertEquals(page.document.querySelector('ul[data-id="7"]'), null);
});

Deno.test('a refused delete shows the error and keeps the row', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	page.service.deleted = {success: false, error_message: 'Still in use'};
	await open(page);

	await clickDelete(page);

	assertEquals(page.errors, ['Still in use']);
	assertSameElement(page.document.querySelector('ul[data-id="7"] button'), page.button);
});
