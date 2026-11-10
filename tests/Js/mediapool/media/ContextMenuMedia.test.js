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

import {assertEquals, assertNotStrictEquals, assertStrictEquals} from "@std/assert";
import {createDom}               from "@support/dom.js";
import {ContextMenuMediaFactory} from "@js/mediapool/media/ContextMenuMediaFactory.js";

// the template of templates/mediapool/overview.mustache
const PAGE = `
	<template id="media-contextmenu-template">
		<ul class="context-menu">
			<li id="infoMedia"></li>
			<li id="editMedia"></li>
			<li id="suggestTags"></li>
			<li id="removeMedia"></li>
			<li id="cloneMedia"></li>
		</ul>
	</template>
	<div id="mediaList"><article class="media-item" data-media-id="abc"></article></div>`;

/**
 * Answers with the results set by the test, or fails with them when they are an Error; the suggestion can wait.
 */
class FakeMediaService
{
	calls      = [];
	suggestion = {description: 'A lobby', tags: 'lobby, hall'};
	newMedia   = {media_id: 'def'};
	removed    = {success: true};
	waitForSuggestion = null;

	async suggestTags(mediaId)
	{
		this.calls.push(['suggestTags', mediaId]);
		if (this.waitForSuggestion !== null)
			await this.waitForSuggestion;
		return this.#answer(this.suggestion);
	}

	cloneMedia(mediaId)
	{
		this.calls.push(['cloneMedia', mediaId]);
		return this.#answer(this.newMedia);
	}

	removeMedia(mediaId)
	{
		this.calls.push(['removeMedia', mediaId]);
		return this.#answer(this.removed);
	}

	#answer(result)
	{
		return result instanceof Error ? Promise.reject(result) : Promise.resolve(result);
	}
}

class FakeDialog
{
	shown = [];

	show(...args)
	{
		this.shown.push(args);
	}
}

function createPage()
{
	const dom          = createDom(PAGE);
	const document     = dom.document;
	const mediaService = new FakeMediaService();
	const editDialog   = new FakeDialog();
	const infoDialog   = new FakeDialog();
	const errors       = [];
	const factory      = new ContextMenuMediaFactory(document.getElementById('media-contextmenu-template'), editDialog,
		infoDialog, mediaService, {showError: (message) => errors.push(message)});
	const medium       = document.querySelector('.media-item');

	return {
		document, mediaService, editDialog, infoDialog, errors, factory, medium,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

/**
 * A menu shown at 120/80 for the medium, its entries are in the page then.
 */
function showMenu(page)
{
	const menu = page.factory.create(page.medium);
	menu.show({pageX: 120, pageY: 80});
	return menu;
}

async function click(page, id)
{
	page.document.getElementById(id).dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

function menus(page)
{
	return [...page.document.querySelectorAll('body > .context-menu')];
}

Deno.test('every menu is a new copy of the template', () =>
{
	using page = createPage();

	showMenu(page);
	const first = menus(page)[0];
	showMenu(page);
	const second = menus(page)[0];

	assertNotStrictEquals(first, second);
	assertEquals(second.querySelectorAll('li').length, 5);
});

Deno.test('the menu is shown where the mouse is and replaces the one before', () =>
{
	using page = createPage();

	showMenu(page);
	showMenu(page);

	const shown = menus(page);
	assertEquals(shown.length, 1);
	assertEquals([shown[0].style.left, shown[0].style.top], ['120px', '80px']);
});

Deno.test('the next click on the page closes the menu', () =>
{
	using page = createPage();
	showMenu(page);

	page.document.dispatchEvent(new Event('click'));

	assertEquals(menus(page), []);
});

Deno.test('information and edit open their dialogs for the medium', async () =>
{
	using page = createPage();
	const menu = showMenu(page);
	menu.addInfoEvent(page.document.getElementById('infoMedia'));
	menu.addEditEvent(page.document.getElementById('editMedia'));

	await click(page, 'infoMedia');
	await click(page, 'editMedia');

	assertEquals(page.infoDialog.shown.length, 1);
	assertStrictEquals(page.infoDialog.shown[0][0], page.medium);
	assertEquals(page.editDialog.shown.length, 1);
	assertStrictEquals(page.editDialog.shown[0][0], page.medium);
	assertEquals(page.editDialog.shown[0].length, 1);
});

Deno.test('the suggestion of the AI opens in the edit dialog, an overlay waits meanwhile', async () =>
{
	using page = createPage();
	const answer = Promise.withResolvers();
	page.mediaService.waitForSuggestion = answer.promise;
	showMenu(page).addSuggestTagsEvent(page.document.getElementById('suggestTags'));

	await click(page, 'suggestTags');
	assertEquals(page.document.getElementById('waitOverlay')?.parentElement, page.document.body);

	answer.resolve();
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.mediaService.calls, [['suggestTags', 'abc']]);
	assertStrictEquals(page.editDialog.shown[0][0], page.medium);
	assertEquals(page.editDialog.shown[0][1], {description: 'A lobby', tags: 'lobby, hall'});
	assertEquals(page.document.getElementById('waitOverlay'), null);
});

Deno.test('a failed suggestion shows the error, removes the overlay and opens no dialog', async () =>
{
	using page = createPage();
	page.mediaService.suggestion = new Error('The AI did not answer');
	showMenu(page).addSuggestTagsEvent(page.document.getElementById('suggestTags'));

	await click(page, 'suggestTags');

	assertEquals(page.errors, ['The AI did not answer']);
	assertEquals(page.editDialog.shown, []);
	assertEquals(page.document.getElementById('waitOverlay'), null);
});

Deno.test('a clone is handed to the list', async () =>
{
	using page  = createPage();
	const added = [];
	showMenu(page).addCloneEvent(page.document.getElementById('cloneMedia'), (media) => added.push(media));

	await click(page, 'cloneMedia');

	assertEquals(page.mediaService.calls, [['cloneMedia', 'abc']]);
	assertEquals(added, [{media_id: 'def'}]);
});

Deno.test('a failed clone shows the error and adds nothing', async () =>
{
	using page  = createPage();
	const added = [];
	page.mediaService.newMedia = new Error('Not allowed');
	showMenu(page).addCloneEvent(page.document.getElementById('cloneMedia'), (media) => added.push(media));

	await click(page, 'cloneMedia');

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(added, []);
});

Deno.test('a removed medium leaves the list', async () =>
{
	using page = createPage();
	showMenu(page).addRemoveEvent(page.document.getElementById('removeMedia'));

	await click(page, 'removeMedia');

	assertEquals(page.mediaService.calls, [['removeMedia', 'abc']]);
	assertEquals(page.document.querySelector('.media-item'), null);
});

Deno.test('a medium that could not be removed stays and the error is shown', async () =>
{
	using page = createPage();
	page.mediaService.removed = new Error('Still in a playlist');
	showMenu(page).addRemoveEvent(page.document.getElementById('removeMedia'));

	await click(page, 'removeMedia');

	assertEquals(page.errors, ['Still in a playlist']);
	assertStrictEquals(page.document.querySelector('.media-item'), page.medium);
});
