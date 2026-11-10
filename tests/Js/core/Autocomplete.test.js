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
import {stub}                         from "@std/testing/mock";
import {FakeTime}                     from "@std/testing/time";
import {assertSameElement, createDom} from "@support/dom.js";
import {Autocomplete}                 from "@js/core/Autocomplete.js";

const ENDPOINT = '/async/playlists/find/';

/**
 * Has the elements of the AutocompleteView, but finds or builds nothing.
 */
class FakeView
{
	exists   = true;
	calls    = [];
	inputElement;
	hiddenElement;
	datalistElement;

	constructor(document)
	{
		this.inputElement    = document.createElement('input');
		this.hiddenElement   = document.createElement('input');
		this.datalistElement = document.createElement('datalist');
	}

	initExisting(fieldName)
	{
		this.calls.push(['initExisting', fieldName]);
	}

	initCreate(parent, fieldName, wrapClassName)
	{
		this.calls.push(['initCreate', parent, fieldName, wrapClassName]);
	}

	restore(newName)
	{
		this.calls.push(['restore', newName]);
		return 'restored';
	}
}

/**
 * No real Response: its body is read in the background, which the fake time does not wait for.
 */
function json(body, status = 200)
{
	const response = {ok: status >= 200 && status < 300, status: status, json: () => Promise.resolve(body)};
	return () => Promise.resolve(response);
}

function addOptions(view, options)
{
	for (const [name, id] of options)
	{
		const option = view.datalistElement.ownerDocument.createElement('option');
		option.value = name;
		option.setAttribute('data-value', id);
		view.datalistElement.appendChild(option);
	}
}

function countSelections(view)
{
	const selections = [];
	view.hiddenElement.addEventListener('selectedFromDataList', () => selections.push(view.hiddenElement.value));
	return selections;
}

function type(view, text)
{
	view.inputElement.value = text;
	view.inputElement.dispatchEvent(new Event('input'));
}

/**
 * Lets the time pass and then the answer of fetch() be handled, before the test checks it.
 */
async function wait(time, milliseconds)
{
	await time.tickAsync(milliseconds);
	await time.runMicrotasks();
}

function createAutocomplete(view)
{
	const autocomplete = new Autocomplete(view, 'playlist', ENDPOINT);
	autocomplete.initWithExistingFields();
	return autocomplete;
}

Deno.test('existing fields are looked up by the field name', () =>
{
	using dom   = createDom();
	const view  = new FakeView(dom.document);

	createAutocomplete(view);

	assertEquals(view.calls, [['initExisting', 'playlist']]);
});

Deno.test('without existing fields typing fetches nothing', async () =>
{
	using time       = new FakeTime();
	using dom        = createDom();
	using fetchStub  = stub(globalThis, 'fetch', json([]));
	const view       = new FakeView(dom.document);
	view.exists      = false;

	createAutocomplete(view);
	type(view, 'abc');
	await wait(time, Autocomplete.DEBOUNCE_DELAY);

	assertEquals(fetchStub.calls.length, 0);
});

Deno.test('created fields fetch the suggestions for the current text at once', async () =>
{
	using dom       = createDom();
	using fetchStub = stub(globalThis, 'fetch', json([{id: 7, name: 'Lobby'}]));
	const view      = new FakeView(dom.document);
	const parent    = dom.document.createElement('span');
	view.inputElement.value = 'Lob by&';

	new Autocomplete(view, 'playlist', ENDPOINT).initWithCreateFields(parent, 'playlist_id');
	await fetchStub.calls[0].returned;
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(view.calls, [['initCreate', parent, 'playlist', 'playlist_id']]);
	assertEquals(fetchStub.calls[0].args[0], ENDPOINT + 'Lob%20by%26');
	assertEquals(view.datalistElement.querySelectorAll('option').length, 1);
});

Deno.test('typing fetches once, 300 ms after the last key, with the last text', async () =>
{
	using time      = new FakeTime();
	using dom       = createDom();
	using fetchStub = stub(globalThis, 'fetch', json([]));
	const view      = new FakeView(dom.document);
	createAutocomplete(view);

	type(view, 'a');
	await wait(time, 200);
	type(view, 'ab');
	await wait(time, 299);
	assertEquals(fetchStub.calls.length, 0);

	await wait(time, 1);
	assertEquals(fetchStub.calls.length, 1);
	assertEquals(fetchStub.calls[0].args[0], ENDPOINT + 'ab');
});

Deno.test('the suggestions replace the old options and the input gets a focus event', async () =>
{
	using time       = new FakeTime();
	using dom        = createDom();
	using _fetchStub = stub(globalThis, 'fetch', json([{id: 1, name: 'Lobby'}, {id: 2, name: 'Canteen'}]));
	const view       = new FakeView(dom.document);
	const focused    = [];
	view.inputElement.addEventListener('focus', () => focused.push(true));
	addOptions(view, [['Old', 99]]);
	createAutocomplete(view);

	type(view, 'x');
	await wait(time, Autocomplete.DEBOUNCE_DELAY);

	const options = [...view.datalistElement.querySelectorAll('option')].map(option => [option.value, option.getAttribute('data-value')]);
	assertEquals(options, [['Lobby', '1'], ['Canteen', '2']]);
	assertEquals(focused.length, 1);
});

Deno.test('a newer request aborts the older one, so its answer cannot replace the newer one', async () =>
{
	using time      = new FakeTime();
	using dom       = createDom();
	using fetchStub = stub(globalThis, 'fetch', (url, config) => new Promise((resolve, reject) => {
		config.signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')));
	}));
	using errorStub = stub(console, 'error');
	const view      = new FakeView(dom.document);
	createAutocomplete(view);

	type(view, 'a');
	await wait(time, Autocomplete.DEBOUNCE_DELAY);
	type(view, 'ab');
	await wait(time, Autocomplete.DEBOUNCE_DELAY);

	assertEquals(fetchStub.calls[0].args[1].signal.aborted, true);
	assertEquals(fetchStub.calls[1].args[1].signal.aborted, false);
	assertEquals(errorStub.calls.length, 0);
});

Deno.test('an HTTP error is logged and keeps the options', async () =>
{
	using time       = new FakeTime();
	using dom        = createDom();
	using _fetchStub = stub(globalThis, 'fetch', json({}, 500));
	using errorStub  = stub(console, 'error');
	const view       = new FakeView(dom.document);
	addOptions(view, [['Lobby', 1]]);
	createAutocomplete(view);

	type(view, 'x');
	await wait(time, Autocomplete.DEBOUNCE_DELAY);

	assertEquals(errorStub.calls.length, 1);
	assertEquals(errorStub.calls[0].args[1].message, 'HTTP-Error: 500');
	assertEquals(view.datalistElement.querySelectorAll('option').length, 1);
});

Deno.test('choosing a suggestion sets the id and reports the selection', () =>
{
	using dom        = createDom();
	const view       = new FakeView(dom.document);
	const selections = countSelections(view);
	addOptions(view, [['Lobby', 1], ['Canteen', 2]]);
	createAutocomplete(view);

	view.inputElement.value = 'Canteen';
	view.inputElement.dispatchEvent(new Event('change'));

	assertEquals(view.hiddenElement.value, '2');
	assertEquals(view.inputElement.dataset.id, '2');
	assertEquals(selections, ['2']);
});

Deno.test('a text without a suggestion selects nothing', () =>
{
	using dom        = createDom();
	const view       = new FakeView(dom.document);
	const selections = countSelections(view);
	addOptions(view, [['Lobby', 1]]);
	createAutocomplete(view);

	view.inputElement.value = 'Lob';
	view.inputElement.dispatchEvent(new Event('change'));
	view.inputElement.dispatchEvent(new Event('blur'));

	assertEquals(view.hiddenElement.value, '');
	assertEquals(selections, []);
});

Deno.test('a name with quotes and backslashes is found', () =>
{
	using dom        = createDom();
	const view       = new FakeView(dom.document);
	const selections = countSelections(view);
	addOptions(view, [['Say "hi" \\ bye', 3]]);
	createAutocomplete(view);

	view.inputElement.value = 'Say "hi" \\ bye';
	view.inputElement.dispatchEvent(new Event('change'));

	assertEquals(selections, ['3']);
});

Deno.test('the blur after a choice does not select a second time', () =>
{
	using dom        = createDom();
	const view       = new FakeView(dom.document);
	const selections = countSelections(view);
	addOptions(view, [['Lobby', 1]]);
	createAutocomplete(view);

	view.inputElement.value = 'Lobby';
	view.inputElement.dispatchEvent(new Event('change'));
	view.inputElement.dispatchEvent(new Event('blur'));

	assertEquals(selections, ['1']);
});

Deno.test('a blur selects the typed suggestion when the datalist sent no change', () =>
{
	using dom        = createDom();
	const view       = new FakeView(dom.document);
	const selections = countSelections(view);
	addOptions(view, [['Lobby', 1]]);
	createAutocomplete(view);

	view.inputElement.value = 'Lobby';
	view.inputElement.dispatchEvent(new Event('blur'));

	assertEquals(selections, ['1']);
});

Deno.test('the input fields are set and cleared', () =>
{
	using dom          = createDom();
	const view         = new FakeView(dom.document);
	const autocomplete = createAutocomplete(view);

	autocomplete.setInputFields('5', 'Lobby');
	assertEquals(view.hiddenElement.value, '5');
	assertEquals(view.inputElement.dataset.id, '5');
	assertEquals(view.inputElement.value, 'Lobby');

	addOptions(view, [['Lobby', 5]]);
	autocomplete.clearAll();
	assertEquals(view.hiddenElement.value, '');
	assertEquals(view.inputElement.value, '');
	assertEquals(view.datalistElement.querySelectorAll('option').length, 0);
});

Deno.test('the elements and restore() come from the view', () =>
{
	using dom          = createDom();
	const view         = new FakeView(dom.document);
	const autocomplete = createAutocomplete(view);

	assertSameElement(autocomplete.getHiddenIdElement(), view.hiddenElement);
	assertSameElement(autocomplete.getEditFieldElement(), view.inputElement);
	assertEquals(autocomplete.restore('New name'), 'restored');
	assertEquals(view.calls[1], ['restore', 'New name']);
});
