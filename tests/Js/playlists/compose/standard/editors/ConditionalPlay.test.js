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

import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {ConditionalPlay} from "@js/playlists/compose/standard/editors/ConditionalPlay.js";

// the export button of templates/playlists/compose.mustache and the button of the item
const PAGE = `
	<button id="playerExport" data-release="0"><span class="label"></span></button>
	<button id="conditionalPlayAction" class="bi bi-clock"></button>`;

const EDITOR = {
	success: true, html: '<input id="enable_conditional_play">',
	data: {item_id: 42, item_name: 'Lobby', conditional: {date: {from: '2026-01-01', until: ''}}}
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
	inits  = [];
	values = {date: {from: '2026-01-01', until: ''}, time: {}, weekdays: {}};

	init(conditional)
	{
		this.inits.push(conditional);
	}

	collectValues()
	{
		return this.values;
	}
}

class FakeService
{
	calls       = [];
	editor      = EDITOR;
	storeAnswer = {success: true};

	async fetchEditor(id)
	{
		this.calls.push(['fetchEditor', id]);
		return this.editor;
	}

	async store(id, data)
	{
		this.calls.push(['store', id, data]);
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
		editor:       new ConditionalPlay(dialog, form, service),
		actionButton: dom.document.getElementById('conditionalPlayAction'),
		exportButton: dom.document.getElementById('playerExport'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

async function save(page)
{
	const event = new Event('submit', {cancelable: true});
	await page.dialog.save(event);
	return event;
}

Deno.test('the editor of the item is loaded', async () =>
{
	using page = createPage();

	assertEquals(await page.editor.fetch(42), EDITOR);

	assertEquals(page.service.calls, [['fetchEditor', 42]]);
});

Deno.test('the dialog shows the item with its editor and the saved conditions', async () =>
{
	using page = createPage();
	await page.editor.fetch(42);

	page.editor.initDialog(page.actionButton);

	assertEquals(page.dialog.calls, [
		['setTitle', 'Lobby'], ['setId', 42], ['setContent', EDITOR.html], ['onSave'], ['onCancel'], ['openDialog']
	]);
	assertEquals(page.form.inits, [EDITOR.data.conditional]);
});

Deno.test('the conditions are saved for the item, the playlist waits for the export', async () =>
{
	using page = createPage();
	await page.editor.fetch(42);
	page.editor.initDialog(page.actionButton);

	const event = await save(page);

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.service.calls[1], ['store', 42, page.form.values]);
	assertEquals(page.dialog.calls.at(-1), ['closeDialog']);
	assertEquals(page.exportButton.classList.contains('notify-save'), true);
	assertEquals(page.actionButton.classList.contains('icon-values-setted'), true);
});

Deno.test('without conditions the button of the item shows no values', async () =>
{
	using page = createPage();
	page.actionButton.classList.add('icon-values-setted');
	page.form.values = {};
	await page.editor.fetch(42);
	page.editor.initDialog(page.actionButton);

	await save(page);

	assertEquals(page.actionButton.classList.contains('icon-values-setted'), false);
});

Deno.test('a refused save shows the error and keeps the dialog open', async () =>
{
	using page = createPage();
	page.service.storeAnswer = {success: false, error_message: 'Date until is before date from'};
	await page.editor.fetch(42);
	page.editor.initDialog(page.actionButton);

	await save(page);

	assertEquals(page.dialog.calls.at(-1), ['setErrorMessage', 'Date until is before date from']);
	assertEquals(page.dialog.calls.some(call => call[0] === 'closeDialog'), false);
	assertEquals(page.exportButton.classList.contains('notify-save'), false);
});
