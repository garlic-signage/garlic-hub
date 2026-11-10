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
import {NotifyEdit}   from "@js/playlists/compose/standard/editors/types/NotifyEdit.js";

// the template of templates/playlists/trigger.mustache
const PAGE = `
	<template id="notifyTemplate">
		<div class="trigger-form">
			<p><button type="button" class="bi bi-trash remove naked-button">Remove</button></p>
			<label><input type="text" class="edit-notify" pattern="[a-zA-Z0-9]*"></label>
		</div>
	</template>
	<div id="notifiesContainer"></div>`;

/**
 * An editor in the container of the page, as the TriggerForm adds it.
 */
function createEditor(data = {})
{
	const dom    = createDom(PAGE);
	const editor = new NotifyEdit();
	editor.init(data);
	const container = dom.document.getElementById('notifiesContainer');
	container.appendChild(editor.getEditor());

	return {
		editor, container, field: container.querySelector('.edit-notify'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function press(field, key)
{
	const event = new Event('keypress', {cancelable: true});
	event.key   = key;
	field.dispatchEvent(event);
	return event.defaultPrevented;
}

Deno.test('a new notification is empty and exists', () =>
{
	using page = createEditor();

	assertEquals(page.editor.exists, true);
	assertEquals(page.editor.getValues(), {notify: ''});
});

Deno.test('a saved notification is shown and given back', () =>
{
	using page = createEditor({notify: 'doorbell'});

	assertEquals(page.field.value, 'doorbell');
	assertEquals(page.editor.getValues(), {notify: 'doorbell'});
});

Deno.test('the changed name is given back', () =>
{
	using page = createEditor({notify: 'doorbell'});

	page.field.value = 'gate2';

	assertEquals(page.editor.getValues(), {notify: 'gate2'});
});

Deno.test('only letters and digits can be typed', () =>
{
	using page = createEditor();

	for (const key of ['a', 'Z', '0', '9'])
		assertEquals(press(page.field, key), false, key);

	for (const key of ['-', ' ', '#', 'ä', '.'])
		assertEquals(press(page.field, key), true, key);
});

Deno.test('the remove button takes the notification away', () =>
{
	using page = createEditor({notify: 'doorbell'});

	page.container.querySelector('.remove').dispatchEvent(new Event('click', {bubbles: true}));

	assertEquals(page.editor.exists, false);
	assertEquals(page.container.querySelector('.trigger-form'), null);
});
