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
import {TouchEdit}    from "@js/playlists/compose/standard/editors/types/TouchEdit.js";

// the template of templates/playlists/trigger.mustache
const PAGE = `
	<template id="touchTemplate">
		<div class="trigger-form">
			<p><button type="button" class="bi bi-trash remove naked-button">Remove</button></p>
			<label>
				<select class="edit-touch-item"><option value="0">-</option></select>
			</label>
		</div>
	</template>
	<div id="touchesContainer"></div>`;

const TOUCHABLE = [{item_id: 11, item_name: 'Lobby'}, {item_id: 12, item_name: 'Hall'}];

/**
 * An editor with the touchable items of the playlist in the container of the page, as the TriggerForm adds it.
 */
function createEditor(data = {}, touchable = TOUCHABLE)
{
	const dom    = createDom(PAGE);
	const editor = new TouchEdit();
	editor.touchableMedialist = touchable;
	editor.init(data);
	const container = dom.document.getElementById('touchesContainer');
	container.appendChild(editor.getEditor());

	return {
		editor, container, select: container.querySelector('.edit-touch-item'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function options(select)
{
	return [...select.querySelectorAll('option')].map(option => [option.getAttribute('value'), option.textContent]);
}

Deno.test('the touchable items of the playlist can be chosen after the empty choice', () =>
{
	using page = createEditor();

	assertEquals(options(page.select), [['0', '-'], ['11', 'Lobby'], ['12', 'Hall']]);
	assertEquals(page.editor.exists, true);
});

Deno.test('a new touch has no item', () =>
{
	using page = createEditor();

	assertEquals(page.editor.getValues(), {touch_item_id: '0'});
});

Deno.test('a saved touch shows its item and gives it back', () =>
{
	using page = createEditor({touch_item_id: 12});

	assertEquals(page.editor.getValues(), {touch_item_id: '12'});
});

Deno.test('the chosen item is given back', () =>
{
	using page = createEditor();

	page.select.value = '11';

	assertEquals(page.editor.getValues(), {touch_item_id: '11'});
});

Deno.test('a playlist without touchable items offers only the empty choice', () =>
{
	using page = createEditor({}, []);

	assertEquals(options(page.select), [['0', '-']]);
});

Deno.test('the remove button takes the touch away without an error', () =>
{
	using page = createEditor({touch_item_id: 11});
	const button = page.container.querySelector('.remove');

	// linkedom throws the error of a listener from dispatchEvent(), the browser would report it as uncaught
	button.dispatchEvent(new Event('click', {bubbles: true}));

	assertEquals(page.editor.exists, false);
	assertEquals(page.container.querySelector('.trigger-form'), null);
});
