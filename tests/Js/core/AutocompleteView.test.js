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
import {assertEquals, assertInstanceOf} from "@std/assert";
import {assertSameElement, createDom}   from "@support/dom.js";
import {Autocomplete}                   from "@js/core/Autocomplete.js";
import {AutocompleteFactory}            from "@js/core/AutocompleteFactory.js";
import {AutocompleteView}               from "@js/core/AutocompleteView.js";

const FIELDS = '<input id="playlist_search"><datalist id="playlist_suggestions"></datalist><input type="hidden" id="playlist">';

Deno.test('the factory gives an autocomplete with its view', () =>
{
	assertInstanceOf(new AutocompleteFactory().create('playlist', '/async/playlists/find/'), Autocomplete);
});

Deno.test('the fields of the page are found by the name', () =>
{
	using dom = createDom(FIELDS);
	const view = new AutocompleteView();

	view.initExisting('playlist');

	assertEquals(view.exists, true);
	assertSameElement(view.inputElement, dom.document.getElementById('playlist_search'));
	assertSameElement(view.datalistElement, dom.document.getElementById('playlist_suggestions'));
	assertSameElement(view.hiddenElement, dom.document.getElementById('playlist'));
});

Deno.test('a missing field means there is no autocomplete', async (t) =>
{
	for (const missing of ['playlist_search', 'playlist_suggestions', 'playlist'])
	{
		await t.step(missing, () =>
		{
			using dom = createDom(FIELDS);
			dom.document.getElementById(missing).remove();
			const view = new AutocompleteView();

			view.initExisting('playlist');

			assertEquals(view.exists, false);
		});
	}
});

Deno.test('the fields are created in place of the cell with its text', () =>
{
	using dom = createDom('<ul><li class="playlist_id" id="cell">Lobby</li></ul>');
	const view = new AutocompleteView();

	view.initCreate(dom.document.getElementById('cell'), 'playlist_name', 'playlist-edit');

	const item = dom.document.querySelector('ul > li');
	assertEquals(item.className, 'playlist-edit');
	assertEquals([...item.children].map(child => [child.tagName, child.id, child.getAttribute('type'), child.getAttribute('list'), child.getAttribute('autocomplete'), child.className]), [
		['INPUT', 'playlist_name_search', 'text', 'playlist_name_suggestions', 'off', 'autocomplete'],
		['DATALIST', 'playlist_name_suggestions', null, null, null, ''],
		['INPUT', 'playlist_name', 'hidden', null, null, '']
	]);
	assertEquals(view.inputElement.value, 'Lobby');
	assertSameElement(view.inputElement, dom.document.getElementById('playlist_name_search'));
});

Deno.test('the cell comes back with the new name as text', () =>
{
	using dom = createDom('<ul><li class="playlist_id" id="cell">Lobby</li></ul>');
	const view = new AutocompleteView();
	const cell = dom.document.getElementById('cell');
	view.initCreate(cell, 'playlist_name', 'playlist-edit');

	view.restore('<b>Kantine</b>');

	assertSameElement(dom.document.querySelector('ul > li'), cell);
	assertEquals(cell.innerHTML, '&lt;b&gt;Kantine&lt;/b&gt;');
});
