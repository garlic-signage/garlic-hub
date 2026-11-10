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

import {assertEquals} from "@std/assert";
import {FakeTime}     from "@std/testing/time";
import {createDom}    from "@support/dom.js";

// UploadDialogElements reads the button of the upload dialog from the document when the module is loaded
let MediaSearch, UploadDialogElements;
{
	using _dom = createDom('<button id="openUploadDialog"></button>');
	({MediaSearch}          = await import("@js/mediapool/media/MediaSearch.js"));
	({UploadDialogElements} = await import("@js/mediapool/uploads/UploadDialogElements.js"));
}

/**
 * The answers of searchMedia() are resolved by the test, so a slower answer can come after a faster one.
 */
class FakeMediaService
{
	searches = [];

	searchMedia(term, filter)
	{
		const answer = Promise.withResolvers();
		this.searches.push({term: term, filter: filter, answer: answer});
		return answer.promise;
	}
}

class FakeMediaList
{
	filter  = '';
	results = [];
	element;

	constructor(document)
	{
		this.element = document.createElement('div');
	}

	showSearchResults(results)
	{
		this.results.push(results);
	}
}

class FakeDirectoryView
{
	reloads = 0;

	reloadCurrentNode()
	{
		this.reloads++;
	}
}

class FakeFlashMessageHandler
{
	errors = [];

	showError(message)
	{
		this.errors.push(message);
	}
}

function createFilter(document)
{
	const select = document.createElement('select');
	for (const kind of ['', 'images', 'videos'])
		select.appendChild(new Option(kind, kind));
	return select;
}

function createSearch(document)
{
	const parts = {
		input:         document.createElement('input'),
		filter:        createFilter(document),
		mediaService:  new FakeMediaService(),
		mediaList:     new FakeMediaList(document),
		directoryView: new FakeDirectoryView(),
		currentPath:   {innerText: 'Lobby'},
		flash:         new FakeFlashMessageHandler(),
	};
	UploadDialogElements.openUploadDialog.disabled = false;

	new MediaSearch(parts.input, parts.filter, parts.mediaService, parts.mediaList, parts.directoryView,
		parts.currentPath, parts.flash, {search_results: 'Search results'}).init();

	return parts;
}

function type(parts, text)
{
	parts.input.value = text;
	parts.input.dispatchEvent(new Event('input'));
}

function chooseFilter(parts, filter)
{
	parts.filter.value = filter;
	parts.filter.dispatchEvent(new Event('change'));
}

/**
 * Lets the time pass and then the answers be handled.
 */
async function wait(time, milliseconds = MediaSearch.DELAY_MS)
{
	await time.tickAsync(milliseconds);
	await time.runMicrotasks();
}

/**
 * A finished search with the given results.
 */
async function search(time, parts, text, results)
{
	type(parts, text);
	await wait(time);
	parts.mediaService.searches.at(-1).answer.resolve(results);
	await time.runMicrotasks();
}

Deno.test('one letter does not search', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	type(parts, 'a');
	await wait(time);
	type(parts, ' a  ');
	await wait(time);

	assertEquals(parts.mediaService.searches, []);
	assertEquals(parts.directoryView.reloads, 0);
});

Deno.test('the search starts 300 ms after the last input with the trimmed term and the filter', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);
	parts.mediaList.filter = 'images';

	type(parts, 'lo');
	await wait(time, 200);
	type(parts, ' lobby ');
	await wait(time, 299);
	assertEquals(parts.mediaService.searches.length, 0);

	await wait(time, 1);
	assertEquals(parts.mediaService.searches.map(s => [s.term, s.filter]), [['lobby', 'images']]);
});

Deno.test('the results are shown with their number and the upload is disabled', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	await search(time, parts, 'lobby', [{media_id: 'a'}, {media_id: 'b'}]);

	assertEquals(parts.mediaList.results, [[{media_id: 'a'}, {media_id: 'b'}]]);
	assertEquals(parts.currentPath.innerText, 'Search results (2)');
	assertEquals(UploadDialogElements.openUploadDialog.disabled, true);
});

Deno.test('a slower answer of an older input does not overwrite the newer one', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	type(parts, 'lob');
	await wait(time);
	type(parts, 'lobby');
	await wait(time);

	const [older, newer] = parts.mediaService.searches;
	newer.answer.resolve([{media_id: 'new'}]);
	await time.runMicrotasks();
	older.answer.resolve([{media_id: 'old'}]);
	await time.runMicrotasks();

	assertEquals(parts.mediaList.results, [[{media_id: 'new'}]]);
	assertEquals(parts.currentPath.innerText, 'Search results (1)');
});

Deno.test('an emptied field brings the directory back once', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);
	await search(time, parts, 'lobby', []);

	type(parts, '');
	await wait(time);
	type(parts, 'l');
	await wait(time);

	assertEquals(parts.directoryView.reloads, 1);
});

Deno.test('the answer of a search is ignored when the field was emptied meanwhile', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	type(parts, 'lobby');
	await wait(time);
	type(parts, '');
	await wait(time);
	parts.mediaService.searches[0].answer.resolve([{media_id: 'a'}]);
	await time.runMicrotasks();

	assertEquals(parts.mediaList.results, []);
	assertEquals(parts.currentPath.innerText, 'Lobby');
	assertEquals(UploadDialogElements.openUploadDialog.disabled, false);
});

Deno.test('a failed search shows the error and leaves the list', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	type(parts, 'lobby');
	await wait(time);
	parts.mediaService.searches[0].answer.reject(new Error('Not allowed'));
	await time.runMicrotasks();

	assertEquals(parts.flash.errors, ['Not allowed']);
	assertEquals(parts.mediaList.results, []);
	assertEquals(UploadDialogElements.openUploadDialog.disabled, false);
});

Deno.test('the filter reloads the directory when there is no search', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);

	chooseFilter(parts, 'videos');
	await wait(time);

	assertEquals(parts.mediaList.filter, 'videos');
	assertEquals(parts.directoryView.reloads, 1);
	assertEquals(parts.mediaService.searches, []);
});

Deno.test('the filter searches again at once during a search and replaces the waiting input', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);
	await search(time, parts, 'lobby', []);

	type(parts, 'lobby hall');
	chooseFilter(parts, 'videos');
	await wait(time);

	assertEquals(parts.mediaService.searches.map(s => [s.term, s.filter]), [['lobby', ''], ['lobby hall', 'videos']]);
	assertEquals(parts.directoryView.reloads, 0);
});

Deno.test('a directory chosen in the tree ends the search and empties the field', async () =>
{
	using time  = new FakeTime();
	using dom   = createDom();
	const parts = createSearch(dom.document);
	await search(time, parts, 'lobby', []);

	parts.mediaList.element.dispatchEvent(new Event('directoryloaded'));
	chooseFilter(parts, 'videos');

	assertEquals(parts.input.value, '');
	assertEquals(parts.directoryView.reloads, 1);
	assertEquals(parts.mediaService.searches.length, 1);
});

Deno.test('a directory loaded without a search keeps the field', () =>
{
	using dom   = createDom();
	const parts = createSearch(dom.document);
	parts.input.value = 'l';

	parts.mediaList.element.dispatchEvent(new Event('directoryloaded'));

	assertEquals(parts.input.value, 'l');
});
