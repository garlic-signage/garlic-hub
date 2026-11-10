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
import {assertSameElement, createDom}     from "@support/dom.js";
import {FakeFetchClient}                  from "@support/FakeFetchClient.js";
import {BaseUploader}                     from "@js/mediapool/uploads/BaseUploader.js";
import {UploadedMedia}                    from "@js/mediapool/uploads/UploadedMedia.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';
const FILE_URL  = 'https://example.com/lobby.jpg';

class FakeDirectoryView
{
	#nodeId;

	constructor(nodeId)
	{
		this.#nodeId = nodeId;
	}

	getActiveNodeId()
	{
		return this.#nodeId;
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

/**
 * An uploader with an enabled start button, as after choosing a file.
 */
function createUploader(result, nodeId = 5)
{
	const domElements = {startFileUpload: {disabled: false}};
	const dialog      = new FakeUploaderDialog();
	const client      = new FakeFetchClient(result);
	const uploader    = new BaseUploader(domElements, new FakeDirectoryView(nodeId), dialog, client);
	uploader.enableUploadButton();
	UploadedMedia.take(); // the list is static, no ids of another test

	return {uploader: uploader, domElements: domElements, dialog: dialog, client: client};
}

Deno.test('a new uploader starts with a disabled start button', () =>
{
	const domElements = {startFileUpload: {disabled: false}};

	new BaseUploader(domElements, new FakeDirectoryView(5), new FakeUploaderDialog(), new FakeFetchClient({}));

	assertEquals(domElements.startFileUpload.disabled, true);
});

Deno.test('the start button is enabled and disabled', () =>
{
	const {uploader, domElements} = createUploader({});

	assertEquals(domElements.startFileUpload.disabled, false);
	uploader.disableUploadButton();
	assertEquals(domElements.startFileUpload.disabled, true);
	uploader.enableUploadButton();
	assertEquals(domElements.startFileUpload.disabled, false);
});

Deno.test('the subclasses get the parts of the constructor', () =>
{
	const domElements   = {startFileUpload: {disabled: false}};
	const directoryView = new FakeDirectoryView(5);
	const dialog        = new FakeUploaderDialog();
	const client        = new FakeFetchClient({});

	const uploader = new BaseUploader(domElements, directoryView, dialog, client);

	assertStrictEquals(uploader.domElements, domElements);
	assertStrictEquals(uploader.directoryView, directoryView);
	assertStrictEquals(uploader.uploaderDialog, dialog);
	assertStrictEquals(uploader.fetchClient, client);
});

Deno.test('a progress bar is added to the container', () =>
{
	using dom       = createDom('<div id="container"></div>');
	const container = dom.document.getElementById('container');
	const {uploader} = createUploader({});

	const progressBar = uploader.createProgressbar(container);

	assertEquals(container.innerHTML, '<div id="progressContainer"><progress id="progressBar"></progress></div>');
	assertSameElement(container.querySelector('#progressBar'), progressBar);
});

Deno.test('an external file without a chosen directory is not uploaded', async () =>
{
	using alertStub = stub(globalThis, 'alert');
	const {uploader, dialog, client} = createUploader({success: true, media_id: 'abc'}, 0);

	await uploader.uploadExternalFile(FILE_URL);

	assertEquals(alertStub.calls[0].args, ['Choose a directory first.']);
	assertEquals(client.requests, []);
	assertEquals(dialog.calls, []);
});

Deno.test('an external file is posted to the chosen directory and remembered for the tagging', async () =>
{
	using _dom = createDom(CSRF_META);
	const {uploader, domElements, dialog, client} = createUploader({success: true, media_id: 'abc'});

	await uploader.uploadExternalFile(FILE_URL);

	const {url, options} = client.requests[0];
	assertEquals(url, '/async/mediapool/uploadFromUrl');
	assertEquals(options.method, 'POST');
	assertEquals([...options.body.entries()], [['node_id', '5'], ['external_link', FILE_URL], ['csrf_token', 'the-token']]);
	assertEquals(UploadedMedia.take(), ['abc']);
	assertEquals(domElements.startFileUpload.disabled, true);
	assertEquals(dialog.calls, ['disableActions', 'enableActions']);
});

Deno.test('the metadata of an external file are sent as JSON', async () =>
{
	using _dom = createDom(CSRF_META);
	const {uploader, client} = createUploader({success: true, media_id: 'abc'});

	await uploader.uploadExternalFile(FILE_URL, {author: 'Jane', license: 'CC0'});

	assertEquals(JSON.parse(client.requests[0].options.body.get('metadata')), {author: 'Jane', license: 'CC0'});
});

Deno.test('a refused upload is logged and changes nothing', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');
	const {uploader, domElements, dialog} = createUploader({success: false, error_message: 'Not allowed'});

	await uploader.uploadExternalFile(FILE_URL);

	assertEquals(errorStub.calls[0].args, ['Error for file:', FILE_URL, 'Not allowed']);
	assertEquals(UploadedMedia.take(), []);
	assertEquals(domElements.startFileUpload.disabled, false);
	assertEquals(dialog.calls, ['disableActions', 'enableActions']);
});

Deno.test('an empty answer is logged as unknown error', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');

	for (const answer of [null, '', {success: false}])
	{
		const {uploader} = createUploader(answer);
		await uploader.uploadExternalFile(FILE_URL);
	}

	assertEquals(errorStub.calls.map(call => call.args[2]), ['Unknown error', 'Unknown error', 'Unknown error']);
	assertEquals(UploadedMedia.take(), []);
});

Deno.test('an aborted or failed upload is logged and the actions are enabled again', async () =>
{
	using _dom    = createDom(CSRF_META);
	using logStub = stub(console, 'log');

	const aborted = createUploader(new Error('Upload aborted.'));
	await aborted.uploader.uploadExternalFile(FILE_URL);

	const failed = createUploader(new Error('Timeout: Server did not respond.'));
	await failed.uploader.uploadExternalFile(FILE_URL);

	assertEquals(logStub.calls[0].args, ['Upload aborted for file:', FILE_URL]);
	assertEquals(logStub.calls[1].args, ['Upload failed for file:', FILE_URL, '\n', 'Timeout: Server did not respond.']);
	assertEquals(aborted.dialog.calls, ['disableActions', 'enableActions']);
	assertEquals(failed.dialog.calls, ['disableActions', 'enableActions']);
	assertEquals(UploadedMedia.take(), []);
});

Deno.test('without CSRF token nothing is sent and the actions are enabled again', async () =>
{
	using _dom    = createDom();
	using logStub = stub(console, 'log');
	const {uploader, dialog, client} = createUploader({success: true, media_id: 'abc'});

	await uploader.uploadExternalFile(FILE_URL);

	assertEquals(client.requests, []);
	assertEquals(logStub.calls[0].args[3], 'No CSRF token found in meta tag');
	assertEquals(dialog.calls, ['disableActions', 'enableActions']);
});

Deno.test('the upload tells whether the file was uploaded', async () =>
{
	using _dom     = createDom(CSRF_META);
	using _error   = stub(console, 'error');
	using _log     = stub(console, 'log');
	using _alert   = stub(globalThis, 'alert');
	const answers  = [
		[{success: true, media_id: 'abc'}, 5, true], [{success: false, error_message: 'Not allowed'}, 5, false],
		[null, 5, false], [new Error('Upload aborted.'), 5, false], [new Error('Timeout'), 5, false],
		[{success: true, media_id: 'abc'}, 0, false],
	];

	for (const [answer, nodeId, uploaded] of answers)
	{
		const {uploader} = createUploader(answer, nodeId);
		assertEquals(await uploader.uploadExternalFile(FILE_URL), uploaded, `${JSON.stringify(answer)} / ${nodeId}`);
	}
	UploadedMedia.take();
});
