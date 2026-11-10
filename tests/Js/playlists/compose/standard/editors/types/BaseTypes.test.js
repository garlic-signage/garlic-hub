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
import {assertSameElement, createDom} from "@support/dom.js";
import {BaseTypes}                    from "@js/playlists/compose/standard/editors/types/BaseTypes.js";

// a trigger form like the ones of templates/playlists/trigger.mustache
const PAGE = `
	<template id="exampleTemplate">
		<div class="trigger-form">
			<p><button type="button" class="remove"></button></p>
			<input type="text" class="edit-example">
		</div>
	</template>
	<div id="container"></div>`;

Deno.test('a new editor does not exist yet and has the id 0', () =>
{
	const editor = new BaseTypes();

	assertEquals([editor.exists, editor.id, editor.node], [false, 0, null]);
});

Deno.test('the id is set by the manager', () =>
{
	const editor = new BaseTypes();

	editor.id = 3;

	assertEquals(editor.id, 3);
});

Deno.test('the editor is a new copy of its template', () =>
{
	using dom = createDom(PAGE);
	const first  = new BaseTypes();
	const second = new BaseTypes();

	first.cloneNode('exampleTemplate');
	second.cloneNode('exampleTemplate');

	assertSameElement(first.getEditor(), first.node);
	assertEquals(first.node.querySelector('.edit-example') === second.node.querySelector('.edit-example'), false);
	assertEquals(dom.document.getElementById('exampleTemplate').content.querySelectorAll('.trigger-form').length, 1);
});

Deno.test('the remove button takes the form out of the page and the editor does not exist any more', () =>
{
	using dom    = createDom(PAGE);
	const editor = new BaseTypes();
	editor.cloneNode('exampleTemplate');
	editor.addRemoveListener();
	assertEquals(editor.exists, true);
	const container = dom.document.getElementById('container');
	container.appendChild(editor.getEditor());

	container.querySelector('.remove').dispatchEvent(new Event('click', {bubbles: true}));

	assertEquals(editor.exists, false);
	assertEquals(container.querySelector('.trigger-form'), null);
});
