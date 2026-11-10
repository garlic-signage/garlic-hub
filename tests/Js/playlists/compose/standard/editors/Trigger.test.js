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
import {Trigger}      from "@js/playlists/compose/standard/editors/Trigger.js";

// the export button of templates/playlists/compose.mustache and the button of the item
const PAGE = `
	<button id="playerExport" data-release="0"><span class="label"></span></button>
	<button id="editTriggerAction" class="bi bi-lightning"></button>`;

const EDITOR = {
	success: true, html: '<button id="addWallclock"></button>',
	item_data: {item_id: 42, item_name: 'Lobby', begin_trigger: {wallclocks: [{iso_date: '2026-01-01T08:00'}]}},
	touchable_medialist: {7: 'Button'}
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
	setWidth(width)          { this.calls.push(['setWidth', width]); }
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
	values = {wallclocks: [{iso_date: '2026-01-01T08:00'}]};

	init(triggerData, touchableMedialist)
	{
		this.inits.push([triggerData, touchableMedialist]);
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

	async fetchBeginTrigger(id)
	{
		this.calls.push(['fetchBeginTrigger', id]);
		return this.editor;
	}

	async storeBeginTrigger(id, data)
	{
		this.calls.push(['storeBeginTrigger', id, data]);
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
		editor:       new Trigger(dialog, form, service),
		actionButton: dom.document.getElementById('editTriggerAction'),
		exportButton: dom.document.getElementById('playerExport'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

async function openEditor(page)
{
	await page.editor.fetchBeginTrigger(42);
	page.editor.initDialog(page.actionButton);
}

async function save(page)
{
	const event = new Event('submit', {cancelable: true});
	await page.dialog.save(event);
	return event;
}

Deno.test('the triggers of the item are loaded', async () =>
{
	using page = createPage();

	assertEquals(await page.editor.fetchBeginTrigger(42), EDITOR);

	assertEquals(page.service.calls, [['fetchBeginTrigger', 42]]);
});

Deno.test('the dialog shows the item with its triggers and the media to touch, wider than the others', async () =>
{
	using page = createPage();

	await openEditor(page);

	assertEquals(page.dialog.calls, [
		['setTitle', 'Lobby'], ['setId', 42], ['setContent', EDITOR.html], ['onSave'], ['onCancel'], ['openDialog'],
		// after the opening: the closing resets it for the other editors
		['setWidth', '80%']
	]);
	assertEquals(page.form.inits, [[EDITOR.item_data.begin_trigger, EDITOR.touchable_medialist]]);
});

Deno.test('the triggers are saved for the item, the playlist waits for the export', async () =>
{
	using page = createPage();
	await openEditor(page);

	const event = await save(page);

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.service.calls[1], ['storeBeginTrigger', 42, page.form.values]);
	assertEquals(page.dialog.calls.at(-1), ['closeDialog']);
	assertEquals(page.exportButton.classList.contains('notify-save'), true);
	assertEquals(page.actionButton.classList.contains('icon-values-setted'), true);
});

Deno.test('every kind of trigger marks the button of the item', async () =>
{
	for (const kind of ['wallclocks', 'accesskeys', 'touches', 'notifies'])
	{
		using page = createPage();
		page.form.values = {[kind]: [{}]};
		await openEditor(page);

		await save(page);

		assertEquals(page.actionButton.classList.contains('icon-values-setted'), true, kind);
	}
});

Deno.test('without triggers the button of the item shows no values', async () =>
{
	using page = createPage();
	page.actionButton.classList.add('icon-values-setted');
	page.form.values = {};
	await openEditor(page);

	await save(page);

	assertEquals(page.actionButton.classList.contains('icon-values-setted'), false);
});

Deno.test('a refused save shows the error and keeps the dialog open', async () =>
{
	using page = createPage();
	page.service.storeAnswer = {success: false, error_message: 'Wrong date'};
	await openEditor(page);

	await save(page);

	assertEquals(page.dialog.calls.at(-1), ['setErrorMessage', 'Wrong date']);
	assertEquals(page.dialog.calls.some(call => call[0] === 'closeDialog'), false);
	assertEquals(page.exportButton.classList.contains('notify-save'), false);
});
