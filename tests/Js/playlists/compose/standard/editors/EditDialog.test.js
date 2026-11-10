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
import {EditDialog}   from "@js/playlists/compose/standard/editors/EditDialog.js";

// the dialog of templates/playlists/compose.mustache
const PAGE = `
	<dialog id="editItemDialog">
		<header>
			<span>Item: </span><span id="editItemName"></span>
			<button id="closeEditDialog" class="close-button bi bi-x" aria-label="Close"></button>
		</header>
		<div id="editItemErrorMessage"></div>
		<form method="dialog" id="editItemForm">
			<div id="editItemContent"></div>
			<input type="hidden" id="editItemId" name="editItemId">
			<footer>
				<button value="submit" type="submit" class="primary">Save</button>
				<button value="cancel" formnovalidate autofocus>Cancel</button>
			</footer>
		</form>
	</dialog>`;

/**
 * linkedom ignores the signal of addEventListener(): like the browser, an abort removes the listener.
 * Returns the function which restores linkedom.
 */
function addSignalSupport(document)
{
	let prototype = Object.getPrototypeOf(document.createElement('div'));
	while (!Object.hasOwn(prototype, 'addEventListener'))
		prototype = Object.getPrototypeOf(prototype);

	const original = prototype.addEventListener;
	prototype.addEventListener = function (type, listener, options)
	{
		original.call(this, type, listener, options);
		options?.signal?.addEventListener('abort', () => this.removeEventListener(type, listener, options));
	};

	return () => prototype.addEventListener = original;
}

/**
 * linkedom cannot set innerText: the title and the error message get a plain property.
 */
function createPage()
{
	const dom           = createDom(PAGE);
	const document      = dom.document;
	const removeSignals = addSignalSupport(document);
	for (const id of ['editItemName', 'editItemErrorMessage'])
		Object.defineProperty(document.getElementById(id), 'innerText', {value: '', writable: true});

	return {
		document,
		element: (id) => document.getElementById(id),
		dialog:  document.getElementById('editItemDialog'),
		[Symbol.dispose]: () => { removeSignals(); dom[Symbol.dispose](); }
	};
}

function fire(element, type)
{
	const event = new Event(type, {bubbles: true, cancelable: true});
	element.dispatchEvent(event);
	return event;
}

Deno.test('the dialog opens without the error of the last edit', () =>
{
	using page = createPage();
	const editDialog = new EditDialog();
	editDialog.setErrorMessage('Not saved');

	editDialog.openDialog();

	assertEquals(page.dialog.hasAttribute('open'), true);
	assertEquals(page.element('editItemErrorMessage').innerText, '');
});

Deno.test('the item is shown with its id, name and editor', () =>
{
	using page = createPage();
	const editDialog = new EditDialog();

	editDialog.setId('42');
	editDialog.setTitle('Lobby');
	editDialog.setContent('<input id="field">');
	editDialog.setErrorMessage('Not saved');

	assertEquals(page.element('editItemId').value, '42');
	assertEquals(page.element('editItemName').innerText, 'Lobby');
	assertEquals(page.element('editItemContent').querySelectorAll('#field').length, 1);
	assertEquals(page.element('editItemErrorMessage').innerText, 'Not saved');
});

Deno.test('the width is set for a wide editor and reset when the dialog closes', () =>
{
	using page = createPage();
	const editDialog = new EditDialog();
	editDialog.openDialog();

	editDialog.setWidth('80%');
	assertEquals(page.dialog.style.width, '80%');

	editDialog.closeDialog();
	assertEquals(page.dialog.hasAttribute('open'), false);
	assertEquals(page.dialog.style.width, '');
});

Deno.test('empty() removes the id and the editor', () =>
{
	using page = createPage();
	const editDialog = new EditDialog();
	editDialog.setId('42');
	editDialog.setContent('<input id="field">');

	editDialog.empty();

	assertEquals(page.element('editItemId').value, '');
	assertEquals(page.element('editItemContent').innerHTML, '');
});

Deno.test('the save callback is called for every submit until the dialog closes', () =>
{
	using page  = createPage();
	const editDialog = new EditDialog();
	const saved = [];
	editDialog.onSave((event) => saved.push(event.type));
	editDialog.openDialog();

	// after an error the user saves again
	fire(page.element('editItemForm'), 'submit');
	fire(page.element('editItemForm'), 'submit');
	assertEquals(saved, ['submit', 'submit']);

	editDialog.closeDialog();
	fire(page.element('editItemForm'), 'submit');
	assertEquals(saved.length, 2);
});

Deno.test('cancel and the close button empty and close the dialog without the form', () =>
{
	for (const button of ['button[value="cancel"]', '#closeEditDialog'])
	{
		using page = createPage();
		const editDialog = new EditDialog();
		editDialog.onCancel();
		editDialog.openDialog();
		editDialog.setId('42');

		const event = fire(page.dialog.querySelector(button), 'click');

		assertEquals(event.defaultPrevented, true, button);
		assertEquals(page.dialog.hasAttribute('open'), false, button);
		assertEquals(page.element('editItemId').value, '', button);
	}
});

Deno.test('the listeners of an edit end with its dialog, the next edit has its own', () =>
{
	using page  = createPage();
	const saved = [];
	const first = new EditDialog();
	first.onSave(() => saved.push('first'));
	first.onCancel();
	first.openDialog();
	first.closeDialog();

	const second = new EditDialog();
	second.onSave(() => saved.push('second'));
	second.openDialog();
	fire(page.element('editItemForm'), 'submit');

	assertEquals(saved, ['second']);
	// the cancel of the first edit is gone too: the dialog stays open
	fire(page.element('closeEditDialog'), 'click');
	assertEquals(page.dialog.hasAttribute('open'), true);
});
