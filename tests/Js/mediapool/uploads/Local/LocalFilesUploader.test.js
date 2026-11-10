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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {stub}                             from "@std/testing/mock";
import {createDom}                        from "@support/dom.js";
import {LocalFilesUploader}               from "@js/mediapool/uploads/Local/LocalFilesUploader.js";
import {UploadedMedia}                    from "@js/mediapool/uploads/UploadedMedia.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

// the previews of the dropped files in the dropzone of templates/mediapool/overview.mustache
const PAGE = `${CSRF_META}
	<div id="dropzonePreview">
		<div class="previewContainer" data-preview-id="a"></div>
		<div class="previewContainer" data-preview-id="b"></div>
	</div>
	<button id="startLocalFilesUpload"></button>`;

/**
 * Instead of the FetchClient: answers the uploads one after another with the given results, an Error fails.
 * Every upload reports a progress of 42.6 %.
 */
class FakeUploadClient
{
	uploads = [];
	inits   = 0;
	#results;

	constructor(...results)
	{
		this.#results = results;
	}

	initUploadWithProgress()
	{
		this.inits++;
	}

	getUploadProgressHandle()
	{
		return {handle: this.inits};
	}

	async uploadWithProgress(url, options, onProgress)
	{
		this.uploads.push({url, options});
		onProgress(42.6);
		const result = this.#results.shift() ?? {success: true, media_id: 'new'};
		if (result instanceof Error)
			throw result;

		return result;
	}

	/**
	 * The fields of the FormData of an upload, the file as its name.
	 */
	fieldsOf(index)
	{
		return [...this.uploads[index].options.body.entries()].map(([name, value]) => [name, value instanceof File ? value.name : value]);
	}
}

/**
 * The files of the dropzone with their metadata, like the LocalFilesPreviews.
 */
class FakeFilePreviews
{
	fileList     = {};
	metaDataList = {};
	handles      = [];
	removed      = [];
	#document;

	constructor(document, files)
	{
		this.#document = document;
		for (const [id, name] of Object.entries(files))
		{
			this.fileList[id]     = new File(['content'], name, {type: 'image/jpeg'});
			this.metaDataList[id] = {origin: 'File upload', page_url: ''};
		}
	}

	setUploadHandler(xhr, id)
	{
		this.handles.push([xhr, id]);
	}

	removeFromPreview(id)
	{
		this.removed.push(id);
		delete this.fileList[id];
		this.#document.querySelector(`[data-preview-id="${id}"]`).remove();
	}
}

class FakeDirectoryView
{
	nodeId = 5;

	getActiveNodeId()
	{
		return this.nodeId;
	}
}

class FakeUploaderDialog
{
	calls = [];

	disableActions()
	{
		this.calls.push('disableActions');
	}

	enableActions()
	{
		this.calls.push('enableActions');
	}
}

function createPage(files = {a: 'lobby.jpg', b: 'hall.jpg'}, ...results)
{
	const dom           = createDom(PAGE);
	const document      = dom.document;
	const previews      = new FakeFilePreviews(document, files);
	const client        = new FakeUploadClient(...results);
	const dialog        = new FakeUploaderDialog();
	const directoryView = new FakeDirectoryView();
	const domElements   = {startFileUpload: document.getElementById('startLocalFilesUpload')};
	const uploader      = new LocalFilesUploader(previews, domElements, directoryView, dialog, client);
	UploadedMedia.take(); // the list is static, no ids of another test

	return {
		document, previews, client, dialog, directoryView, domElements, uploader,
		container: (id) => document.querySelector(`[data-preview-id="${id}"]`),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

/**
 * uploadFiles() starts the uploads without waiting for them.
 */
async function uploadAll(page)
{
	page.uploader.uploadFiles();
	for (let i = 0; i < 10; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the start button is disabled until a file is chosen', () =>
{
	using page = createPage();

	assertEquals(page.domElements.startFileUpload.disabled, true);
	assertStrictEquals(page.uploader.filePreviews, page.previews);
});

Deno.test('the start button uploads the files', async () =>
{
	using page = createPage({a: 'lobby.jpg'});

	page.domElements.startFileUpload.dispatchEvent(new Event('click'));
	for (let i = 0; i < 10; i++)
		await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.client.uploads.length, 1);
});

Deno.test('without files nothing is uploaded', async () =>
{
	using alertStub = stub(globalThis, 'alert');
	using page      = createPage({});

	await uploadAll(page);

	// the alert is never shown: the fileList is an object without length, the start button is disabled anyway
	assertEquals(alertStub.calls, []);
	assertEquals(page.client.uploads, []);
	assertEquals(page.dialog.calls, []);
});

Deno.test('without a chosen directory nothing is uploaded', async () =>
{
	using alertStub = stub(globalThis, 'alert');
	using page      = createPage();
	page.directoryView.nodeId = 0;

	await uploadAll(page);

	assertEquals(alertStub.calls[0].args, ['Choose a directory first.']);
	assertEquals(page.client.uploads, []);
});

Deno.test('every file is posted with the directory, its metadata and the CSRF token', async () =>
{
	using page = createPage();

	await uploadAll(page);

	assertEquals(page.client.uploads.map(upload => [upload.url, upload.options.method]), [
		['/async/mediapool/uploadLocalFile', 'POST'], ['/async/mediapool/uploadLocalFile', 'POST']
	]);
	const metadata = JSON.stringify({origin: 'File upload', page_url: ''});
	assertEquals(page.client.fieldsOf(0), [['file', 'lobby.jpg'], ['node_id', '5'], ['metadata', metadata], ['csrf_token', 'the-token']]);
	assertEquals(page.client.fieldsOf(1)[0], ['file', 'hall.jpg']);
});

Deno.test('the previews get the handle of the running upload to abort it', async () =>
{
	using page = createPage();

	await uploadAll(page);

	assertEquals(page.client.inits, 2);
	assertEquals(page.previews.handles, [[{handle: 1}, 'a'], [{handle: 2}, 'b']]);
});

Deno.test('the progress is shown in a bar of the preview', async () =>
{
	using page = createPage({a: 'lobby.jpg'}, {success: false, error_message: 'kept for the look'});
	using _err = stub(console, 'error');

	await uploadAll(page);

	const bar = page.container('a').querySelector('#progressContainer #progressBar');
	assertEquals([bar.style.display, bar.style.width, bar.textContent], ['block', '42.6%', '43%']);
});

Deno.test('an uploaded file leaves the previews and is remembered for the tagging', async () =>
{
	using page = createPage({a: 'lobby.jpg', b: 'hall.jpg'}, {success: true, media_id: 'm1'}, {success: true, media_id: 'm2'});

	await uploadAll(page);

	assertEquals(page.previews.removed, ['a', 'b']);
	assertEquals(UploadedMedia.take(), ['m1', 'm2']);
	assertEquals(page.dialog.calls, ['disableActions', 'enableActions', 'disableActions', 'enableActions']);
});

Deno.test('a file whose preview was removed before is skipped', async () =>
{
	using page = createPage();
	page.container('a').remove();

	await uploadAll(page);

	assertEquals(page.client.uploads.length, 1);
	assertEquals(page.client.fieldsOf(0)[0], ['file', 'hall.jpg']);
});

Deno.test('a refused file is logged, stays in the previews and the next one is uploaded', async () =>
{
	using errorStub = stub(console, 'error');
	using page      = createPage({a: 'lobby.jpg', b: 'hall.jpg'}, {success: false, error_message: 'Too large'}, {success: true, media_id: 'm2'});

	await uploadAll(page);

	assertEquals(errorStub.calls.map(call => call.args), [['Error for file:', 'lobby.jpg', 'Too large']]);
	assertEquals(page.previews.removed, ['b']);
	assertEquals(page.container('a').className, 'previewContainer');
	assertEquals(UploadedMedia.take(), ['m2']);
});

Deno.test('a refusal without a message is logged as unknown error', async () =>
{
	using errorStub = stub(console, 'error');
	using page      = createPage({a: 'lobby.jpg'}, {success: false});

	await uploadAll(page);

	assertEquals(errorStub.calls[0].args, ['Error for file:', 'lobby.jpg', 'Unknown error']);
});

Deno.test('a failed upload marks its preview, the next one is uploaded', async () =>
{
	using logStub = stub(console, 'log');
	using page    = createPage({a: 'lobby.jpg', b: 'hall.jpg'}, new Error('Timeout: Server did not respond.'));

	await uploadAll(page);

	assertEquals(logStub.calls[0].args, ['Upload failed for file:', 'lobby.jpg', '\n', 'Timeout: Server did not respond.']);
	assertEquals(page.container('a').className, 'previewContainerError');
	assertEquals(page.previews.removed, ['b']);
	assertEquals(page.dialog.calls.at(-1), 'enableActions');
});

Deno.test('an aborted upload is only logged', async () =>
{
	using logStub = stub(console, 'log');
	using page    = createPage({a: 'lobby.jpg'}, new Error('Upload aborted.'));

	await uploadAll(page);

	assertEquals(logStub.calls[0].args, ['Upload aborted for file:', 'lobby.jpg']);
	assertEquals(page.container('a').className, 'previewContainer');
	assertEquals(UploadedMedia.take(), []);
});
