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

import {assertEquals, assertRejects} from "@std/assert";
import {createDom}                   from "@support/dom.js";
import {FolderTagging}               from "@js/mediapool/media/FolderTagging.js";

// the dialog of templates/mediapool/overview.mustache
const PAGE = `
	<dialog id="folderTaggingDialog">
		<p id="folderTaggingStatus"></p>
		<progress id="folderTaggingProgress" value="0" max="1"></progress>
		<button id="cancelFolderTagging" type="button"></button>
	</dialog>`;

const LANG = {
	tag_folder_nothing: 'Nothing to tag',
	tag_folder_status:  '{done} of {total}',
	tag_folder_done:    '{tagged} files tagged',
	tag_folder_failed:  'Not tagged: {files}',
};

function medium(mediaId, mimetype = 'image/jpeg', tags = [], description = '')
{
	return {media_id: mediaId, filename: mediaId + '.file', mimetype: mimetype, tags: tags, media_description: description};
}

/**
 * Answers every suggestion with "Suggested <id>" and the tags "a, b"; failures and waiting are set per medium.
 */
class FakeMediaService
{
	media          = [];
	loadError      = null;
	failSuggestion = [];
	failEdit       = [];
	suggestions    = {};
	waitFor        = {};
	suggested      = [];
	edits          = [];
	onSuggest      = () => {};

	loadMediaByNodeId(nodeId)
	{
		this.loadedNode = nodeId;
		return this.loadError === null ? Promise.resolve(this.media) : Promise.reject(this.loadError);
	}

	async suggestTags(mediaId)
	{
		this.suggested.push(mediaId);
		this.onSuggest(mediaId);
		if (this.waitFor[mediaId] !== undefined)
			await this.waitFor[mediaId];
		if (this.failSuggestion.includes(mediaId))
			throw new Error('The AI did not answer');

		return this.suggestions[mediaId] ?? {description: 'Suggested ' + mediaId, tags: 'a, b'};
	}

	editMedia(mediaId, filename, description, tags)
	{
		this.edits.push([mediaId, filename, description, tags]);
		return this.failEdit.includes(mediaId) ? Promise.reject(new Error('Not allowed')) : Promise.resolve({success: true});
	}
}

class FakeFlashMessageHandler
{
	messages = [];

	showInformation(message) { this.messages.push(['information', message]); }
	showSuccess(message)     { this.messages.push(['success', message]); }
	showError(message)       { this.messages.push(['error', message]); }
}

function createTagging()
{
	const dom    = createDom(PAGE);
	const dialog = dom.document.getElementById('folderTaggingDialog');
	const state  = {opened: 0, closed: 0};
	// linkedom has no dialog methods
	dialog.showModal = () => state.opened++;
	dialog.close     = () => state.closed++;

	const mediaService = new FakeMediaService();
	const flash        = new FakeFlashMessageHandler();

	return {
		dialog, state, mediaService, flash,
		cancel:   dom.document.getElementById('cancelFolderTagging'),
		status:   dom.document.getElementById('folderTaggingStatus'),
		progress: dom.document.getElementById('folderTaggingProgress'),
		tagging:  new FolderTagging(mediaService, flash, LANG),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('pictures, videos and PDFs can be tagged, but no SVG', () =>
{
	const taggable = {
		'image/jpeg': true, 'image/png': true, 'video/mp4': true, 'application/pdf': true,
		'image/svg+xml': false, 'audio/mpeg': false, 'application/widget': false, 'text/plain': false, '': false,
	};

	for (const [mimetype, expected] of Object.entries(taggable))
		assertEquals(FolderTagging.isTaggable(mimetype), expected, mimetype);
});

Deno.test('a folder without untagged pictures says so and opens no dialog', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('tagged', 'image/jpeg', ['lobby']), medium('logo', 'image/svg+xml'), medium('song', 'audio/mpeg')];

	assertEquals(await page.tagging.run(5), 0);

	assertEquals(page.mediaService.loadedNode, 5);
	assertEquals(page.mediaService.suggested, []);
	assertEquals(page.flash.messages, [['information', 'Nothing to tag']]);
	assertEquals(page.state.opened, 0);
});

Deno.test('the untagged media are tagged one after the other and the dialog shows the progress', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('a'), medium('tagged', 'image/jpeg', ['x']), medium('b', 'video/mp4')];
	const statusAtSuggestion = [];
	page.mediaService.onSuggest = () => statusAtSuggestion.push([page.status.textContent, page.progress.value]);

	assertEquals(await page.tagging.run(5), 2);

	assertEquals(page.mediaService.edits, [
		['a', 'a.file', 'Suggested a', 'a, b'],
		['b', 'b.file', 'Suggested b', 'a, b'],
	]);
	assertEquals(page.progress.max, 2);
	assertEquals(statusAtSuggestion, [['0 of 2', 0], ['1 of 2', 1]]);
	assertEquals(page.status.textContent, '2 of 2');
	assertEquals(page.state, {opened: 1, closed: 1});
	assertEquals(page.flash.messages, [['success', '2 files tagged']]);
});

Deno.test('the suggested description is added to the one there', async () =>
{
	using page = createTagging();
	page.mediaService.media = [
		medium('own', 'image/jpeg', [], '  My lobby '),
		Object.assign(medium('none'), {media_description: null}),
		medium('empty-suggestion', 'image/jpeg', [], 'Kept'),
	];
	page.mediaService.suggestions = {'empty-suggestion': {description: '', tags: 'c'}};

	await page.tagging.run(5);

	assertEquals(page.mediaService.edits.map(edit => edit[2]), ['My lobby\nSuggested own', 'Suggested none', 'Kept']);
});

Deno.test('only the given media are tagged, e.g. the ones just uploaded', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('a'), medium('b'), medium('c')];

	assertEquals(await page.tagging.run(5, ['c', 'a', 'unknown']), 2);

	assertEquals(page.mediaService.suggested, ['a', 'c']);
});

Deno.test('a failed file is named and the others are tagged', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('a'), medium('b'), medium('c')];
	page.mediaService.failSuggestion = ['a'];
	page.mediaService.failEdit       = ['c'];

	assertEquals(await page.tagging.run(5), 1);

	assertEquals(page.status.textContent, '3 of 3');
	assertEquals(page.flash.messages, [['success', '1 files tagged'], ['error', 'Not tagged: a.file, c.file']]);
	assertEquals(page.state.closed, 1);
});

Deno.test('when every file fails there is no success message', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('a')];
	page.mediaService.failSuggestion = ['a'];

	assertEquals(await page.tagging.run(5), 0);

	assertEquals(page.flash.messages, [['error', 'Not tagged: a.file']]);
});

Deno.test('cancel stops after the file in work', async () =>
{
	using page = createTagging();
	const first = Promise.withResolvers();
	page.mediaService.media   = [medium('a'), medium('b')];
	page.mediaService.waitFor = {a: first.promise};

	const running = page.tagging.run(5);
	await new Promise(resolve => setTimeout(resolve, 0));
	page.cancel.dispatchEvent(new Event('click'));
	assertEquals(page.cancel.disabled, true);
	first.resolve();

	assertEquals(await running, 1);
	assertEquals(page.mediaService.suggested, ['a']);
	assertEquals(page.mediaService.edits.map(edit => edit[0]), ['a']);
	assertEquals(page.state.closed, 1);
	assertEquals(page.flash.messages, [['success', '1 files tagged']]);
});

Deno.test('a new run after a cancel tags again', async () =>
{
	using page = createTagging();
	page.mediaService.media = [medium('a')];
	page.cancel.dispatchEvent(new Event('click'));

	assertEquals(await page.tagging.run(5), 1);
	assertEquals(page.cancel.disabled, false);
});

Deno.test('Escape does not close the dialog while the work goes on', () =>
{
	using page = createTagging();
	const escape = new Event('cancel', {cancelable: true});

	page.dialog.dispatchEvent(escape);

	assertEquals(escape.defaultPrevented, true);
});

Deno.test('a folder that cannot be loaded throws and opens no dialog', async () =>
{
	using page = createTagging();
	page.mediaService.loadError = new Error('Not allowed');

	await assertRejects(() => page.tagging.run(5), Error, 'Not allowed');
	assertEquals(page.state.opened, 0);
});
