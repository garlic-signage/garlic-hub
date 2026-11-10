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
import {PlayerActionsContextMenu}           from "@js/player/datatable/PlayerActionsContextMenu.js";
import {PlayerActionsContextMenuFactory}    from "@js/player/datatable/PlayerActionsContextMenuFactory.js";

// the menu of templates/player/datatable.mustache and a row of the datatable with the playlist of the player
const PAGE = `
	<template id="playerActionsContextMenuTemplate">
		<ul class="context-menu">
			<li class="assign">Assign</li>
			<li class="unassign">Unassign</li>
			<li class="push">Push</li>
			<li class="goto"><a href="">Go to playlist</a></li>
			<li class="preview">Preview</li>
			<li class="share">Share</li>
			<li class="delete" data-confirm="Delete the player?">Delete</li>
		</ul>
	</template>
	<ul class="results-listing">
		<li><ul class="results-body" data-id="7">
			<li class="playlist_id">Lobby</li>
			<li><button class="player-contextmenu" data-action-id="7">…</button></li>
		</ul></li>
	</ul>`;

const ALL_RIGHTS = {
	can_edit: true, can_delete: true, can_share: true, has_playlist: true, playlist_id: 5, is_intranet: true,
	uuid: '11111111-1111-4111-8111-111111111111', player_name: 'Eingang'
};

/**
 * Answers with the rights and the results set by the test.
 */
class FakePlayerService
{
	calls    = [];
	rights   = ALL_RIGHTS;
	replaced = {success: true, playlist_name: 'Neue Liste'};
	pushed   = {success: true, message: 'Sent'};

	async determineRights(playerId)
	{
		this.calls.push(['determineRights', playerId]);
		return this.rights;
	}

	async replacePlaylist(playerId, playlistId)
	{
		this.calls.push(['replacePlaylist', playerId, playlistId]);
		return this.replaced;
	}

	async pushPlaylist(playerId)
	{
		this.calls.push(['pushPlaylist', playerId]);
		return this.pushed;
	}

	async delete(playerId)
	{
		this.calls.push(['delete', playerId]);
		return {success: true};
	}
}

/**
 * The autocomplete of the playlists: it takes the cell and gives a hidden field, which reports the chosen playlist.
 */
class FakeAutocomplete
{
	created  = null;
	field    = null;
	idName   = null;
	restored = null;
	hidden;

	constructor(document)
	{
		this.hidden = document.createElement('input');
	}

	initWithCreateFields(field, idName)
	{
		this.field  = field;
		this.idName = idName;
	}

	getHiddenIdElement()
	{
		return this.hidden;
	}

	restore(name)
	{
		this.restored = name;
	}

	choose(playlistId)
	{
		this.hidden.value = playlistId;
		this.hidden.dispatchEvent(new Event('selectedFromDataList'));
	}
}

function createPage()
{
	const dom          = createDom(PAGE);
	// the datatable cannot be loaded again: the row of the player is removed instead
	const globals      = replaceGlobals({window: {scrollY: 0, location: {href: 'https://hub.example.com/player'}}, fetch: () => Promise.reject(new Error('offline'))});
	const service      = new FakePlayerService();
	const autocomplete = new FakeAutocomplete(dom.document);
	const previews     = [];
	const shared       = [];
	const messages     = [];
	const factory      = new PlayerActionsContextMenuFactory(
		{
			showError:        (message) => messages.push('error: ' + message),
			showSuccess:      (message) => messages.push('success: ' + message),
			clearAllMessages: () => {}
		},
		{create: (name, url) => { autocomplete.created = [name, url]; return autocomplete; }},
		service,
		{open: (uuid, name) => previews.push([uuid, name])},
		{attachTo: (item, unitId, allowed) => { shared.push([item, unitId, allowed]); if (!allowed) item.remove(); }}
	);

	return {
		document: dom.document, service, autocomplete, previews, shared, messages, factory,
		button: dom.document.querySelector('.player-contextmenu'),
		cell:   dom.document.querySelector('li.playlist_id'),
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

async function flush()
{
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

async function click(page, className)
{
	page.document.querySelector(`body > .context-menu .${className}`).dispatchEvent(new Event('click', {cancelable: true}));
	await flush();
}

Deno.test('the factory builds the menu of the players from its template', () =>
{
	using page = createPage();

	const menu = page.factory.create();

	assertInstanceOf(menu, PlayerActionsContextMenu);
	assertEquals(menu.view.menu.querySelectorAll('li').length, 7);
});

Deno.test('the rights of the player are asked for the clicked row', async () =>
{
	using page = createPage();

	const menu = await open(page);

	assertEquals(page.service.calls, [['determineRights', 7]]);
	assertEquals(menu.unitId, 7);
});

Deno.test('a player with a playlist in the intranet has every entry, the link goes to its playlist', async () =>
{
	using page = createPage();

	await open(page);

	assertEquals(entries(page), ['assign', 'unassign', 'push', 'goto', 'preview', 'share', 'delete']);
	assertEquals(page.document.querySelector('body > .context-menu .goto a').getAttribute('href'), '/playlists/compose/5');
});

Deno.test('a player without a playlist can only get one', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, has_playlist: false};

	await open(page);

	assertEquals(entries(page), ['assign', 'share', 'delete']);
});

Deno.test('only a player in the intranet gets the playlist pushed', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, is_intranet: false};

	await open(page);

	assertEquals(entries(page), ['assign', 'unassign', 'goto', 'preview', 'share', 'delete']);
});

Deno.test('the share dialog gets its entry, the player and the right', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_share: false, can_delete: false};

	await open(page);

	const [[item, unitId, allowed]] = page.shared;
	assertEquals([item.className, unitId, allowed], ['share', 7, false]);
	assertEquals(entries(page), ['assign', 'unassign', 'push', 'goto', 'preview']);
});

Deno.test('without the right to edit there is no menu', async () =>
{
	using page = createPage();
	page.service.rights = {...ALL_RIGHTS, can_edit: false};

	await open(page);

	assertEquals(entries(page), []);
});

Deno.test('the preview plays as the player', async () =>
{
	using page = createPage();
	await open(page);

	await click(page, 'preview');

	assertEquals(page.previews, [['11111111-1111-4111-8111-111111111111', 'Eingang']]);
});

Deno.test('the unassign takes the playlist and empties its cell', async () =>
{
	using page = createPage();
	await open(page);

	await click(page, 'unassign');

	assertEquals(page.service.calls.at(-1), ['replacePlaylist', 7, 0]);
	assertEquals(page.cell.textContent, '');
});

Deno.test('a refused unassign shows the error and keeps the playlist in the cell', async () =>
{
	using page = createPage();
	page.service.replaced = {success: false, error_message: 'Not allowed'};
	await open(page);

	await click(page, 'unassign');

	assertEquals(page.messages, ['error: Not allowed']);
	assertEquals(page.cell.textContent, 'Lobby');
});

Deno.test('the assign searches the playlists for players in the cell of the row', async () =>
{
	using page = createPage();
	await open(page);

	await click(page, 'assign');

	assertEquals(page.autocomplete.created, ['playlist_name', '/async/playlists/find/for-player/']);
	assertSameElement(page.autocomplete.field, page.cell);
	assertEquals(page.autocomplete.idName, 'playlist_id');
});

Deno.test('the chosen playlist goes onto the player and its name into the cell', async () =>
{
	using page = createPage();
	await open(page);
	await click(page, 'assign');

	page.autocomplete.choose('9');
	await flush();

	assertEquals(page.service.calls.at(-1), ['replacePlaylist', 7, '9']);
	assertEquals(page.autocomplete.restored, 'Neue Liste');
});

Deno.test('a refused playlist shows the error and keeps the field', async () =>
{
	using page = createPage();
	page.service.replaced = {success: false, error_message: 'No master playlist'};
	await open(page);
	await click(page, 'assign');

	page.autocomplete.choose('9');
	await flush();

	assertEquals(page.messages, ['error: No master playlist']);
	assertEquals(page.autocomplete.restored, null);
});

Deno.test('the push sends the playlist to the player', async () =>
{
	using page = createPage();
	await open(page);

	await click(page, 'push');

	assertEquals(page.service.calls.at(-1), ['pushPlaylist', 7]);
	assertEquals(page.messages, ['success: Sent']);
});

Deno.test('a confirmed delete deletes the player', async () =>
{
	using page     = createPage();
	using _confirm = stub(globalThis, 'confirm', () => true);
	await open(page);

	await click(page, 'delete');

	assertEquals(page.service.calls.at(-1), ['delete', 7]);
	assertEquals(page.document.querySelector('ul[data-id="7"]'), null);
});
