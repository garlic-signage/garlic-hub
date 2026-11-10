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
import {assertEquals}  from "@std/assert";
import {createDom}     from "@support/dom.js";
import {AccesskeyEdit} from "@js/playlists/compose/standard/editors/types/AccesskeyEdit.js";

// the template of templates/playlists/trigger.mustache
const PAGE = `
	<template id="accesskeyTemplate">
		<div class="trigger-form">
			<p><button type="button" class="bi bi-trash remove naked-button">Remove</button></p>
			<label><input type="text" class="edit-accesskey" maxlength="1" pattern="[a-zA-Z0-9]*"></label>
		</div>
	</template>
	<div id="accesskeysContainer"></div>`;

/**
 * An editor in the container of the page, as the TriggerForm adds it.
 */
function createEditor(data = {})
{
	const dom    = createDom(PAGE);
	const editor = new AccesskeyEdit();
	editor.init(data);
	const container = dom.document.getElementById('accesskeysContainer');
	container.appendChild(editor.getEditor());

	return {
		editor, container, field: container.querySelector('.edit-accesskey'),
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

Deno.test('a new access key is empty and exists', () =>
{
	using page = createEditor();

	assertEquals(page.editor.exists, true);
	assertEquals(page.editor.getValues(), {accesskey: ''});
});

Deno.test('a saved access key is shown and given back', () =>
{
	using page = createEditor({accesskey: 'k'});

	assertEquals(page.field.value, 'k');
	assertEquals(page.editor.getValues(), {accesskey: 'k'});
});

Deno.test('the changed key is given back', () =>
{
	using page = createEditor({accesskey: 'k'});

	page.field.value = '7';

	assertEquals(page.editor.getValues(), {accesskey: '7'});
});

Deno.test('only letters and digits can be typed', () =>
{
	using page = createEditor();

	for (const key of ['a', 'Z', '0', '9'])
		assertEquals(press(page.field, key), false, key);

	for (const key of ['-', ' ', '#', 'ä', '.'])
		assertEquals(press(page.field, key), true, key);
});

Deno.test('the remove button takes the access key away', () =>
{
	using page = createEditor({accesskey: 'k'});

	page.container.querySelector('.remove').dispatchEvent(new Event('click', {bubbles: true}));

	assertEquals(page.editor.exists, false);
	assertEquals(page.container.querySelector('.trigger-form'), null);
});
