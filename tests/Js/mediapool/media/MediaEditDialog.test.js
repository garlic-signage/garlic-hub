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
import {MediaEditDialog} from "@js/mediapool/media/MediaEditDialog.js";

// the dialog of templates/mediapool/overview.mustache and two media of the list
const PAGE = `
	<dialog id="editMediaDialog">
		<button id="closeEditMediaDialog"></button>
		<form method="dialog">
			<input type="text" id="editFilename">
			<textarea id="editDescription"></textarea>
			<input type="text" id="editTags">
			<button id="submitEditMedia" type="submit"></button>
		</form>
	</dialog>
	<article class="media-item" data-media-id="abc">
		<img data-title="Lobby" data-description="The lobby" data-tags=" Lobby ,hall,, ">
		<span class="media-filename">Lobby</span>
	</article>
	<article class="media-item" data-media-id="def">
		<img data-title="Canteen">
		<span class="media-filename">Canteen</span>
	</article>`;

class FakeMediaService
{
	edits = [];
	error = null;

	editMedia(mediaId, filename, description, tags)
	{
		this.edits.push([mediaId, filename, description, tags]);
		return this.error === null ? Promise.resolve({success: true}) : Promise.reject(this.error);
	}
}

function createEditDialog()
{
	const dom          = createDom(PAGE);
	const document     = dom.document;
	const mediaService = new FakeMediaService();
	const errors       = [];

	return {
		document, mediaService, errors,
		dialog:      document.getElementById('editMediaDialog'),
		filename:    document.getElementById('editFilename'),
		description: document.getElementById('editDescription'),
		tags:        document.getElementById('editTags'),
		medium:      (mediaId) => document.querySelector(`[data-media-id="${mediaId}"]`),
		editDialog:  new MediaEditDialog(mediaService, {showError: (message) => errors.push(message)}),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function fields(page)
{
	return [page.filename.value, page.description.value, page.tags.value];
}

/**
 * @return {Event} the click, its default would submit the form and close the dialog
 */
async function save(page)
{
	const click = new Event('click', {cancelable: true});
	page.document.getElementById('submitEditMedia').dispatchEvent(click);
	await new Promise(resolve => setTimeout(resolve, 0));
	return click;
}

function stored(page, mediaId)
{
	const img = page.medium(mediaId).querySelector('img');
	return [
		page.medium(mediaId).querySelector('.media-filename').textContent,
		img.getAttribute('data-title'), img.getAttribute('data-description'), img.getAttribute('data-tags')
	];
}

Deno.test('the dialog starts with the name, the description and the cleaned tags of the medium', () =>
{
	using page = createEditDialog();

	page.editDialog.show(page.medium('abc'));

	assertEquals(fields(page), ['Lobby', 'The lobby', 'Lobby, hall']);
	assertEquals(page.dialog.hasAttribute('open'), true);
});

Deno.test('a medium without description and tags starts with empty fields', () =>
{
	using page = createEditDialog();

	page.editDialog.show(page.medium('def'));

	assertEquals(fields(page), ['Canteen', '', '']);
});

Deno.test('a suggestion adds its description and only the new tags', () =>
{
	using page = createEditDialog();

	page.editDialog.show(page.medium('abc'), {description: 'A bright hall', tags: ['HALL', 'screen', 'lobby', 'reception']});

	assertEquals(fields(page), ['Lobby', 'The lobby\nA bright hall', 'Lobby, hall, screen, reception']);
});

Deno.test('a suggestion for a medium without description and tags fills them', () =>
{
	using page = createEditDialog();

	page.editDialog.show(page.medium('def'), {description: 'A canteen', tags: ['food']});

	assertEquals(fields(page), ['Canteen', 'A canteen', 'food']);
});

Deno.test('a suggestion without description keeps the one of the medium', () =>
{
	using page = createEditDialog();

	page.editDialog.show(page.medium('abc'), {description: '', tags: []});

	assertEquals(fields(page), ['Lobby', 'The lobby', 'Lobby, hall']);
});

Deno.test('a saved change is sent with the tags as list and shown in the list', async () =>
{
	using page = createEditDialog();
	page.editDialog.show(page.medium('abc'));
	page.filename.value    = 'Main lobby';
	page.description.value = 'The main lobby';
	page.tags.value        = 'lobby, , entrance ';

	const click = await save(page);

	assertEquals(page.mediaService.edits, [['abc', 'Main lobby', 'The main lobby', ['lobby', 'entrance']]]);
	assertEquals(stored(page, 'abc'), ['Main lobby', 'Main lobby', 'The main lobby', 'lobby, entrance']);
	assertEquals(page.errors, []);
	assertEquals(click.defaultPrevented, true);
	assertEquals(page.dialog.hasAttribute('open'), false);
});

Deno.test('a failed save shows the error and keeps the medium and the open dialog with the input', async () =>
{
	using page = createEditDialog();
	page.mediaService.error = new Error('Not allowed');
	page.editDialog.show(page.medium('abc'));
	page.filename.value = 'Main lobby';

	const click = await save(page);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(stored(page, 'abc'), ['Lobby', 'Lobby', 'The lobby', ' Lobby ,hall,, ']);
	assertEquals(click.defaultPrevented, true);
	assertEquals(page.dialog.hasAttribute('open'), true);
	assertEquals(page.filename.value, 'Main lobby');
});

Deno.test('the save belongs to the medium shown last', async () =>
{
	using page = createEditDialog();
	page.editDialog.show(page.medium('abc'));
	page.editDialog.show(page.medium('def'));

	await save(page);

	assertEquals(page.mediaService.edits.map(edit => edit[0]), ['def']);
	assertEquals(stored(page, 'abc')[0], 'Lobby');
});

Deno.test('the close button cancels', () =>
{
	using page = createEditDialog();
	page.editDialog.show(page.medium('abc'));

	page.document.getElementById('closeEditMediaDialog').dispatchEvent(new Event('click'));

	assertEquals(page.dialog.hasAttribute('open'), false);
	assertEquals(page.dialog.returnValue, 'cancel');
});
