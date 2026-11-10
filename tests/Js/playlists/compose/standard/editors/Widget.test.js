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
import {createDom}    from "@support/dom.js";
import {Widget}       from "@js/playlists/compose/standard/editors/Widget.js";

// the export button of templates/playlists/compose.mustache
const PAGE = '<button id="playerExport" data-release="0"><span class="label"></span></button>';

const CONFIGURATION = {
	success: true,
	data: {item_id: 42, item_name: 'Weather', preferences: {city: {types: 'text'}}, values: {city: 'Berlin'}}
};

/**
 * Records the calls of the item; the save callback can be called by the test.
 */
class FakeEditDialog
{
	calls = [];
	save  = null;

	setTitle(title)          { this.calls.push(['setTitle', title]); }
	setId(id)                { this.calls.push(['setId', id]); }
	setContent(content)      { this.calls.push(['setContent', content]); }
	setErrorMessage(message) { this.calls.push(['setErrorMessage', message]); }
	openDialog()             { this.calls.push(['openDialog']); }
	closeDialog()            { this.calls.push(['closeDialog']); }
	onCancel()               { this.calls.push(['onCancel']); }

	onSave(callback)
	{
		this.calls.push(['onSave']);
		this.save = callback;
	}
}

class FakeForm
{
	parsed = [];
	values = {city: 'Hamburg'};

	parsePreferences(preferences, values, namespace)
	{
		this.parsed.push([preferences, values, namespace]);
	}

	getForm()
	{
		return '<ul class="form_edit"></ul>';
	}

	collectValues()
	{
		return this.values;
	}
}

class FakeService
{
	calls         = [];
	configuration = CONFIGURATION;
	storeAnswer   = {success: true};

	async fetchConfiguration(id)
	{
		this.calls.push(['fetchConfiguration', id]);
		return this.configuration;
	}

	async storeWidgetValues(id, data)
	{
		this.calls.push(['storeWidgetValues', id, data]);
		return this.storeAnswer;
	}
}

function createPage()
{
	const dom     = createDom(PAGE);
	const dialog  = new FakeEditDialog();
	const form    = new FakeForm();
	const service = new FakeService();

	return {
		dialog, form, service,
		editor:       new Widget(dialog, form, service),
		exportButton: dom.document.getElementById('playerExport'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

async function openEditor(page)
{
	await page.editor.fetch(42);
	page.editor.initDialog();
}

async function save(page)
{
	const event = new Event('submit', {cancelable: true});
	await page.dialog.save(event);
	return event;
}

Deno.test('the configuration of the widget is loaded', async () =>
{
	using page = createPage();

	assertEquals(await page.editor.fetch(42), CONFIGURATION);

	assertEquals(page.service.calls, [['fetchConfiguration', 42]]);
});

Deno.test('the dialog shows the form of the preferences with the saved values, named by the item', async () =>
{
	using page = createPage();

	await openEditor(page);

	assertEquals(page.form.parsed, [[{city: {types: 'text'}}, {city: 'Berlin'}, 42]]);
	assertEquals(page.dialog.calls, [
		['setTitle', 'Weather'], ['setId', 42], ['setContent', '<ul class="form_edit"></ul>'], ['onSave'], ['onCancel'], ['openDialog']
	]);
});

Deno.test('the values are saved for the item, the playlist waits for the export', async () =>
{
	using page = createPage();
	await openEditor(page);

	const event = await save(page);

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.service.calls[1], ['storeWidgetValues', 42, {city: 'Hamburg'}]);
	assertEquals(page.dialog.calls.at(-1), ['closeDialog']);
	assertEquals(page.exportButton.classList.contains('notify-save'), true);
});

Deno.test('a refused save shows the error and keeps the dialog open', async () =>
{
	using page = createPage();
	page.service.storeAnswer = {success: false, error_message: 'City is mandatory'};
	await openEditor(page);

	await save(page);

	assertEquals(page.dialog.calls.at(-1), ['setErrorMessage', 'City is mandatory']);
	assertEquals(page.dialog.calls.some(call => call[0] === 'closeDialog'), false);
	assertEquals(page.exportButton.classList.contains('notify-save'), false);
});
