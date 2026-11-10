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
import {AiFiller}                     from "@js/playlists/compose/standard/ai/AiFiller.js";

// the panel of templates/playlists/compose.mustache, the playlist and its export button
const PAGE = `
	<template id="aiFillTemplate">
		<form class="ai-fill" id="aiFillForm">
			<input type="text" id="aiTheme" maxlength="300" required>
			<input type="number" id="aiMinutes" min="1" max="120" value="5" required>
			<button type="submit" class="primary">Suggest</button>
		</form>
		<ul class="ai-suggestion" id="aiSuggestion"></ul>
		<button type="button" id="aiInsertChecked" hidden>Insert</button>
	</template>
	<template id="aiSuggestionItemTemplate">
		<li>
			<label>
				<input type="checkbox" checked>
				<img src="" alt="">
				<span class="ai-suggestion-name"></span>
				<span class="ai-suggestion-duration"></span>
			</label>
		</li>
	</template>
	<button id="playerExport" data-release="0"><span class="label">Export</span></button>
	<ul id="playlistItems"><li></li><li></li></ul>
	<div id="itemSelectContainer"></div>`;

const LANG = {ai_inserted: 'The media are inserted.'};

function suggestion(id, seconds = 72)
{
	return {media_id: id, filename: 'Media ' + id, thumbnail: '/thumb/' + id, seconds};
}

/**
 * Answers with the suggestion set by the test, or fails with it when it is an Error.
 */
class FakePlaylistsService
{
	calls  = [];
	answer = [suggestion('a'), suggestion('b')];
	wait   = null;

	async suggestWithAi(playlistId, theme, minutes)
	{
		this.calls.push([playlistId, theme, minutes]);
		if (this.wait !== null)
			await this.wait;
		if (this.answer instanceof Error)
			throw this.answer;

		return this.answer;
	}
}

/**
 * Answers every insert with the next result, the last one stays.
 */
class FakeItemsService
{
	calls   = [];
	results = [{success: true, data: {item: {item_id: 1}, playlist_metrics: {duration: 60}}}];

	async insertMedia(id, playlistId, position)
	{
		this.calls.push([id, playlistId, position]);
		return this.results.length > 1 ? this.results.shift() : this.results[0];
	}
}

class FakeItemList
{
	calls = [];

	createPlaylistItem(item)
	{
		this.calls.push(['createPlaylistItem', item]);
	}

	displayPlaylistMetrics(metrics)
	{
		this.calls.push(['displayPlaylistMetrics', metrics]);
	}
}

/**
 * linkedom has no input.checked: like the browser it starts with the attribute and keeps what is set.
 */
function addChecked(document)
{
	const prototype = Object.getPrototypeOf(document.createElement('input'));
	const values    = new WeakMap();
	Object.defineProperty(prototype, 'checked', {
		configurable: true,
		get() { return values.has(this) ? values.get(this) : this.hasAttribute('checked'); },
		set(value) { values.set(this, Boolean(value)); }
	});

	return () => delete prototype.checked;
}

function createPage()
{
	const dom           = createDom(PAGE);
	const document      = dom.document;
	const removeChecked = addChecked(document);
	const playlists     = new FakePlaylistsService();
	const items         = new FakeItemsService();
	const itemList      = new FakeItemList();
	const messages      = [];
	const flash         = {showError: (text) => messages.push(['error', text]), showSuccess: (text) => messages.push(['success', text])};
	const filler        = new AiFiller(playlists, items, itemList, document.getElementById('playlistItems'), 7, flash, LANG);
	const container     = document.getElementById('itemSelectContainer');
	filler.show(container);

	return {
		document, playlists, items, itemList, messages, filler, container,
		[Symbol.dispose]: () => { removeChecked(); dom[Symbol.dispose](); }
	};
}

// the panel is searched in its container: getElementById() of linkedom finds the elements inside the template first
function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

async function suggest(page, theme = 'beach', minutes = '10')
{
	page.container.querySelector('#aiTheme').value   = theme;
	page.container.querySelector('#aiMinutes').value = minutes;
	const event = new Event('submit', {cancelable: true});
	page.container.querySelector('#aiFillForm').dispatchEvent(event);
	await wait();
	return event;
}

async function insertChecked(page)
{
	page.container.querySelector('#aiInsertChecked').dispatchEvent(new Event('click'));
	for (let i = 0; i < 5; i++)
		await wait();
}

function entries(page)
{
	return [...page.container.querySelectorAll('#aiSuggestion li')];
}

function insertButton(page)
{
	return page.container.querySelector('#aiInsertChecked');
}

Deno.test('the panel takes the place of the selector', () =>
{
	using page = createPage();

	assertEquals(page.container.querySelectorAll('#aiFillForm, #aiSuggestion, #aiInsertChecked').length, 3);
	assertEquals(insertButton(page).hidden, true);
});

Deno.test('the theme and the minutes ask the AI for media, the form is not sent', async () =>
{
	using page = createPage();

	const event = await suggest(page, 'beach', '10');

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.playlists.calls, [[7, 'beach', 10]]);
});

Deno.test('the suggestion is a ticked list with thumbnail, name and duration', async () =>
{
	using page = createPage();

	await suggest(page);

	const [first, second] = entries(page);
	assertEquals([first.getAttribute('data-media-id'), second.getAttribute('data-media-id')], ['a', 'b']);
	assertEquals(first.querySelector('img').getAttribute('src'), '/thumb/a');
	assertEquals(first.querySelector('img').getAttribute('alt'), 'Media a');
	assertEquals(first.querySelector('.ai-suggestion-name').textContent, 'Media a');
	assertEquals(first.querySelector('.ai-suggestion-duration').textContent, '00:01:12');
	assertEquals(first.querySelector('input').checked, true);
	assertEquals(insertButton(page).hidden, false);
});

Deno.test('a new suggestion replaces the one before, an empty one hides the insert', async () =>
{
	using page = createPage();
	await suggest(page);

	page.playlists.answer = [];
	await suggest(page);

	assertEquals(entries(page).length, 0);
	assertEquals(insertButton(page).hidden, true);
});

Deno.test('an overlay waits for the AI', async () =>
{
	using page   = createPage();
	const answer = Promise.withResolvers();
	page.playlists.wait = answer.promise;

	const suggesting = suggest(page);
	assertSameElement(page.document.getElementById('waitOverlay')?.parentElement, page.document.body);

	answer.resolve();
	await suggesting;
	await wait();
	assertSameElement(page.document.getElementById('waitOverlay'), null);
});

Deno.test('a failed suggestion shows the error and removes the overlay', async () =>
{
	using page = createPage();
	page.playlists.answer = new Error('The AI did not answer');

	await suggest(page);

	assertEquals(page.messages, [['error', 'The AI did not answer']]);
	assertEquals(entries(page).length, 0);
	assertSameElement(page.document.getElementById('waitOverlay'), null);
});

Deno.test('the ticked media are added at the end of the playlist', async () =>
{
	using page = createPage();
	page.playlists.answer = [suggestion('a'), suggestion('b'), suggestion('c')];
	await suggest(page);
	entries(page)[1].querySelector('input').checked = false;

	await insertChecked(page);

	assertEquals(page.items.calls, [['a', 7, 3], ['c', 7, 3]]);
	assertEquals(page.itemList.calls.filter(call => call[0] === 'createPlaylistItem').length, 2);
	assertEquals(entries(page).map(entry => entry.getAttribute('data-media-id')), ['b']);
	assertEquals(insertButton(page).hidden, false);
	assertEquals(page.messages, [['success', 'The media are inserted.']]);
	assertEquals(page.document.getElementById('playerExport').classList.contains('notify-save'), true);
	assertSameElement(page.document.getElementById('waitOverlay'), null);
});

Deno.test('when all are inserted the insert is hidden', async () =>
{
	using page = createPage();
	await suggest(page);

	await insertChecked(page);

	assertEquals(entries(page).length, 0);
	assertEquals(insertButton(page).hidden, true);
});

Deno.test('a refused insert stops, the media before stay inserted', async () =>
{
	using page = createPage();
	page.playlists.answer = [suggestion('a'), suggestion('b'), suggestion('c')];
	page.items.results = [
		{success: true, data: {item: {item_id: 1}, playlist_metrics: {duration: 60}}},
		{success: false, error_message: 'The playlist is full'}
	];
	await suggest(page);

	await insertChecked(page);

	assertEquals(page.items.calls.map(call => call[0]), ['a', 'b']);
	assertEquals(entries(page).map(entry => entry.getAttribute('data-media-id')), ['b', 'c']);
	assertEquals(page.messages, [['error', 'The playlist is full'], ['success', 'The media are inserted.']]);
	assertEquals(page.document.getElementById('playerExport').classList.contains('notify-save'), true);
});

Deno.test('nothing inserted asks for no export', async () =>
{
	using page = createPage();
	page.items.results = [{success: false, error_message: 'Not allowed'}];
	await suggest(page);

	await insertChecked(page);

	assertEquals(page.messages, [['error', 'Not allowed']]);
	assertEquals(page.document.getElementById('playerExport').classList.contains('notify-save'), false);
	assertEquals(entries(page).length, 2);
});

Deno.test('without a ticked medium nothing is inserted', async () =>
{
	using page = createPage();
	await suggest(page);
	entries(page).forEach(entry => entry.querySelector('input').checked = false);

	await insertChecked(page);

	assertEquals(page.items.calls, []);
	assertEquals(page.messages, []);
});
