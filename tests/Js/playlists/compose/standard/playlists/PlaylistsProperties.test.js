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


import {assertEquals}        from "@std/assert";
import {createDom}           from "@support/dom.js";
import {replaceGlobals}      from "@support/globals.js";
import {PlaylistsProperties} from "@js/playlists/compose/standard/playlists/PlaylistsProperties.js";

// the properties of templates/playlists/compose.mustache
function page(release = '0')
{
	return `
	<strong id="playlistDuration">00:00:00</strong>
	<strong id="totalItems">0</strong>
	<strong id="totalFilesize">0 MB</strong>
	<div><input type="checkbox" value="1" id="toggleShuffle"></div>
	<div><select id="shufflePicking"></select></div>
	<strong id="releaseWaiting" hidden></strong>
	<button id="playerExport" data-release="${release}"><span class="label">Export</span></button>`;
}

// the texts of the page: the static methods take the global lang of the page, display() the one of the constructor
const LANG = {picking_all: 'All', release_requested: 'Release requested', released: 'Released', request_release: 'Request release'};

const METRICS = {duration: 125, count_items: 3, filesize: 1536};

/**
 * Answers every request with the result set for it, an Error fails.
 */
class FakePlaylistsService
{
	calls   = [];
	answers = {};

	#answer(name, ...args)
	{
		this.calls.push([name, ...args]);
		const answer = this.answers[name] ?? {success: true, playlist_metrics: METRICS};
		return answer instanceof Error ? Promise.reject(answer) : Promise.resolve(answer);
	}

	toggleShuffle(playlistId)                  { return this.#answer('toggleShuffle', playlistId); }
	shufflePicking(playlistId, shufflePicking) { return this.#answer('shufflePicking', playlistId, shufflePicking); }
	export(playlistId)                         { return this.#answer('export', playlistId); }
	requestRelease(playlistId)                 { return this.#answer('requestRelease', playlistId); }
}

function createPage(release = '0')
{
	const dom      = createDom(page(release));
	const globals  = replaceGlobals({lang: LANG});
	const document = dom.document;
	const service  = new FakePlaylistsService();
	// the browser takes a number as innerHTML, linkedom only a text
	for (const id of ['playlistDuration', 'totalItems', 'totalFilesize'])
		textAsInnerHtml(document.getElementById(id));
	const errors   = [];
	const properties = new PlaylistsProperties(service, {showError: (message) => errors.push(message)}, LANG);

	return {
		document, service, errors, properties,
		shuffle: document.getElementById('toggleShuffle'),
		picking: document.getElementById('shufflePicking'),
		exportButton: document.getElementById('playerExport'),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function textAsInnerHtml(element)
{
	const property = findProperty(element, 'innerHTML');
	Object.defineProperty(element, 'innerHTML', {
		get() { return property.get.call(this); },
		set(value) { property.set.call(this, String(value)); }
	});
}

function findProperty(object, name)
{
	for (let prototype = Object.getPrototypeOf(object); prototype !== null; prototype = Object.getPrototypeOf(prototype))
	{
		const property = Object.getOwnPropertyDescriptor(prototype, name);
		if (property !== undefined)
			return property;
	}
	return undefined;
}

function text(page, id)
{
	return page.document.getElementById(id).innerHTML;
}

function options(page)
{
	return [...page.picking.querySelectorAll('option')].map(option => [option.value, option.textContent, option.selected === true]);
}

function isNotified(page)
{
	return page.exportButton.classList.contains('notify-save');
}

function label(page)
{
	return page.exportButton.querySelector('.label').textContent;
}

function isWaiting(page)
{
	return !page.document.getElementById('releaseWaiting').hidden;
}

async function click(element, type = 'click')
{
	element.dispatchEvent(new Event(type));
	await new Promise(resolve => setTimeout(resolve, 0));
}

function playlist(changes = {})
{
	return {shuffle: 0, shuffle_picking: 0, export_time: '2026-11-10 10:00:00', last_update: '2026-11-10 09:00:00', release_status: '', ...changes};
}

Deno.test('the metrics show duration, number of items and size', () =>
{
	using page = createPage();

	page.properties.display(METRICS);

	assertEquals([text(page, 'playlistDuration'), text(page, 'totalItems'), text(page, 'totalFilesize')], ['00:02:05', '3', '1.50 KB']);
});

Deno.test('without shuffle the picking is disabled and has no choice', () =>
{
	using page = createPage();

	page.properties.display(METRICS);

	assertEquals(page.picking.disabled, true);
	assertEquals(options(page), []);
});

Deno.test('with shuffle the picking offers every number of items, the last one is all', () =>
{
	using page = createPage();
	page.properties.setOptions(playlist({shuffle: 1, shuffle_picking: 2}));

	page.properties.display(METRICS);

	assertEquals(page.picking.disabled, false);
	assertEquals(options(page), [['1', '1', false], ['2', '2', true], ['3', 'All', false]]);
});

Deno.test('a picking larger than the items after a delete becomes all', () =>
{
	using page = createPage();
	page.properties.setOptions(playlist({shuffle: 1, shuffle_picking: 5}));

	page.properties.display(METRICS);

	assertEquals(options(page).at(-1), ['3', 'All', true]);
});

Deno.test('the options set the shuffle', () =>
{
	using page = createPage();

	page.properties.setOptions(playlist({shuffle: 1}));
	assertEquals(page.shuffle.checked, true);

	page.properties.setOptions(playlist({shuffle: 0}));
	assertEquals(page.shuffle.checked, false);
});

Deno.test('a playlist changed after the last export asks for the export', () =>
{
	{
		using page = createPage();
		page.properties.setOptions(playlist({export_time: '2026-11-10 08:00:00'}));
		assertEquals(isNotified(page), true);
	}
	{
		using page = createPage();
		page.properties.setOptions(playlist());
		assertEquals(isNotified(page), false);
	}
});

Deno.test('while the release is on the button shows the state of the release', () =>
{
	const cases = [
		['ready', true, 'Release requested', false, true],
		['released', true, 'Released', false, false],
		['', false, 'Request release', true, false],
	];

	for (const [status, disabled, text, notified, waiting] of cases)
	{
		using page = createPage('1');

		page.properties.setOptions(playlist({release_status: status}));

		assertEquals([page.exportButton.disabled, label(page), isNotified(page), isWaiting(page)], [disabled, text, notified, waiting], status);
	}
});

Deno.test('a change while the release waits asks for it again and ends the waiting', () =>
{
	using page = createPage('1');
	page.properties.setOptions(playlist({release_status: 'ready'}));

	PlaylistsProperties.notifySave();

	assertEquals([page.exportButton.disabled, label(page), isNotified(page), isWaiting(page)], [false, 'Request release', true, false]);
});

Deno.test('a change without release only asks for the export', () =>
{
	using page = createPage();

	PlaylistsProperties.notifySave();

	assertEquals([isNotified(page), label(page), page.exportButton.disabled], [true, 'Export', false]);

	PlaylistsProperties.removeSave();
	assertEquals(isNotified(page), false);
});

Deno.test('the shuffle is switched on, shows the picking and asks for the export', async () =>
{
	using page = createPage();
	page.properties.init(7);
	page.picking.parentElement.style.display = 'none';

	page.shuffle.checked = true;
	await click(page.shuffle);

	assertEquals(page.service.calls, [['toggleShuffle', 7]]);
	assertEquals(page.picking.parentElement.style.display, 'block');
	assertEquals(text(page, 'totalItems'), '3');
	assertEquals(isNotified(page), true);
});

Deno.test('the shuffle switched off hides the picking', async () =>
{
	using page = createPage();
	page.properties.init(7);

	page.shuffle.checked = false;
	await click(page.shuffle);

	assertEquals(page.picking.parentElement.style.display, 'none');
	assertEquals(page.picking.disabled, true);
});

Deno.test('a shuffle which could not be saved is undone and the error shown', async () =>
{
	for (const answer of [{success: false, error_message: 'Not allowed'}, new Error('Not allowed')])
	{
		using page = createPage();
		page.service.answers.toggleShuffle = answer;
		page.properties.init(7);

		page.shuffle.checked = true;
		await click(page.shuffle);

		assertEquals(page.shuffle.checked, false);
		assertEquals(page.errors, ['Not allowed']);
		assertEquals(isNotified(page), false);
	}
});

Deno.test('a new picking is saved and asks for the export', async () =>
{
	using page = createPage();
	page.properties.setOptions(playlist({shuffle: 1, shuffle_picking: 1}));
	page.properties.display(METRICS);
	page.properties.init(7);

	page.picking.value = '2';
	await click(page.picking, 'change');

	assertEquals(page.service.calls, [['shufflePicking', 7, '2']]);
	assertEquals(isNotified(page), true);
});

Deno.test('a picking which could not be saved goes back to the one before', async () =>
{
	using page = createPage();
	page.service.answers.shufflePicking = {success: false, error_message: 'Not allowed'};
	page.properties.setOptions(playlist({shuffle: 1, shuffle_picking: 1}));
	page.properties.display(METRICS);
	page.properties.init(7);

	page.picking.value = '2';
	await click(page.picking, 'change');

	assertEquals(page.picking.value, '1');
	assertEquals(page.errors, ['Not allowed']);
});

Deno.test('the export removes the hint to export', async () =>
{
	using page = createPage();
	page.properties.init(7);
	PlaylistsProperties.notifySave();

	await click(page.exportButton);

	assertEquals(page.service.calls, [['export', 7]]);
	assertEquals(isNotified(page), false);
});

Deno.test('a failed export keeps the hint and shows the error', async () =>
{
	using page = createPage();
	page.service.answers.export = {success: false, error_message: 'Player offline'};
	page.properties.init(7);
	PlaylistsProperties.notifySave();

	await click(page.exportButton);

	assertEquals(page.errors, ['Player offline']);
	assertEquals(isNotified(page), true);
});

Deno.test('while the release is on the button asks for the release instead of the export', async () =>
{
	using page = createPage('1');
	page.properties.init(7);
	PlaylistsProperties.notifySave();

	await click(page.exportButton);

	assertEquals(page.service.calls, [['requestRelease', 7]]);
	assertEquals([page.exportButton.disabled, label(page), isNotified(page)], [true, 'Release requested', false]);
});

Deno.test('a release which could not be requested stays to be asked for', async () =>
{
	using page = createPage('1');
	page.service.answers.requestRelease = {success: false, error_message: 'Not allowed'};
	page.properties.init(7);
	PlaylistsProperties.notifySave();

	await click(page.exportButton);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals([page.exportButton.disabled, label(page), isNotified(page)], [false, 'Request release', true]);
});
