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
import {stub}                         from "@std/testing/mock";
import {createDom}                    from "@support/dom.js";
import {FakeFetchClient}              from "@support/FakeFetchClient.js";
import {ExternalFileElements}         from "@js/mediapool/uploads/External/ExternalFileElements.js";
import {ExternalFileUploader}         from "@js/mediapool/uploads/External/ExternalFileUploader.js";
import {UploadedMedia}                from "@js/mediapool/uploads/UploadedMedia.js";

// the tab of templates/mediapool/overview.mustache
const PAGE = `
	<meta name="csrf-token" content="the-token">
	<section class="tab-content" id="linkTab">
		<input type="url" placeholder="https://example.com/media.jpg" id="externalLinkField">
		<button id="startExternalFileUpload" class="upload-button primary">Start</button>
	</section>`;

const FILE_URL = 'https://example.com/lobby.jpg';

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

	disableActions() { this.calls.push('disableActions'); }
	enableActions()  { this.calls.push('enableActions'); }
}

function createPage(answer = {success: true, media_id: 'abc'})
{
	const dom      = createDom(PAGE);
	const elements = new ExternalFileElements();
	const client   = new FakeFetchClient(answer);
	const dialog   = new FakeUploaderDialog();
	const view     = new FakeDirectoryView();
	const uploader = new ExternalFileUploader(elements, view, dialog, client);
	UploadedMedia.take(); // the list is static, no ids of another test

	return {
		document: dom.document, elements, client, dialog, view, uploader,
		field:  elements.externalLinkField,
		button: elements.startFileUpload,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

/**
 * linkedom has no validation: the field reports the validity the browser would give the typed text.
 */
function type(page, text, valid)
{
	page.field.value = text;
	Object.defineProperty(page.field, 'validity', {value: {valid}, configurable: true});
	page.field.dispatchEvent(new Event('input'));
}

async function clickStart(page)
{
	page.button.dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the upload starts disabled', () =>
{
	using page = createPage();

	assertEquals(page.button.disabled, true);
});

Deno.test('only a valid address enables the upload', () =>
{
	using page = createPage();

	type(page, FILE_URL, true);
	assertEquals(page.button.disabled, false);

	type(page, 'lobby.jpg', false);
	assertEquals(page.button.disabled, true);
});

Deno.test('the address is uploaded into the active folder with its origin', async () =>
{
	using page = createPage();
	type(page, FILE_URL, true);

	await clickStart(page);

	const {url, options} = page.client.requests[0];
	assertEquals(url, '/async/mediapool/uploadFromUrl');
	assertEquals(options.method, 'POST');
	assertEquals(options.body.get('node_id'), '5');
	assertEquals(options.body.get('external_link'), FILE_URL);
	assertEquals(options.body.get('csrf_token'), 'the-token');
	assertEquals(JSON.parse(options.body.get('metadata')), {origin: 'External link', page_url: FILE_URL});
	assertEquals(UploadedMedia.take(), ['abc']);
	assertEquals(page.dialog.calls, ['disableActions', 'enableActions']);
});

Deno.test('after the upload the field is empty and the upload disabled', async () =>
{
	using page = createPage();
	type(page, FILE_URL, true);

	await clickStart(page);

	assertEquals(page.field.value, '');
	assertEquals(page.button.disabled, true);
});

Deno.test('a refused upload is logged and empties the field', async () =>
{
	using page      = createPage({success: false, error_message: 'Not allowed'});
	using errorStub = stub(console, 'error');
	type(page, FILE_URL, true);

	await page.uploader.uploadFile();

	assertEquals(errorStub.calls[0].args, ['Error for file:', FILE_URL, 'Not allowed']);
	assertEquals(UploadedMedia.take(), []);
	assertEquals(page.field.value, '');
	// the button stays enabled for an empty field
	assertEquals(page.button.disabled, false);
});

Deno.test('without an active folder nothing is uploaded', async () =>
{
	using page      = createPage();
	using alertStub = stub(globalThis, 'alert');
	page.view.nodeId = 0;
	type(page, FILE_URL, true);

	await page.uploader.uploadFile();

	assertEquals(alertStub.calls[0].args, ['Choose a directory first.']);
	assertEquals(page.client.requests, []);
});
