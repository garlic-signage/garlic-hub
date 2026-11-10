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


import {assertEquals, assertMatch}    from "@std/assert";
import {stub}                         from "@std/testing/mock";
import {assertSameElement, createDom} from "@support/dom.js";
import {replaceGlobals}               from "@support/globals.js";
import {BasePreviewHandler}           from "@js/mediapool/uploads/Preview/BasePreviewHandler.js";

const LOBBY = {name: 'lobby.jpg', size: 1572864, type: 'image/jpeg'};
const UUID  = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

/**
 * Creates the previews of the PreviewFactory: an image with the metadata of the file, or fails for "unknown" files.
 */
class FakePreviewFactory
{
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(file)
	{
		if (file.type === 'unknown')
			throw new Error('File type: unknown is not supported');

		const document = this.#document;
		return {
			createPreview: () => document.createElement('img'),
			extractMetadata: () => ({name: file.name, size: '1.50 MB', type: file.type})
		};
	}
}

class FakeUploader
{
	calls = [];

	enableUploadButton()  { this.calls.push('enable'); }
	disableUploadButton() { this.calls.push('disable'); }
}

/**
 * A handler on https: the ids come from crypto.randomUUID().
 */
function createPage(protocol = 'https:')
{
	const dom      = createDom('<div id="previewArea"></div>');
	const globals  = replaceGlobals({location: {protocol: protocol}});
	const area     = dom.document.getElementById('previewArea');
	const handler  = new BasePreviewHandler(area, new FakePreviewFactory(dom.document));
	const uploader = new FakeUploader();
	handler.fileUploader = uploader;

	return {
		document: dom.document, area, handler, uploader,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function previews(page)
{
	return [...page.area.querySelectorAll('.previewContainer')];
}

Deno.test('a file gets a preview with its metadata and enables the upload', () =>
{
	using page = createPage();

	page.handler.addFile(LOBBY, null);

	const [container] = previews(page);
	const id = container.getAttribute('data-preview-id');
	assertMatch(id, UUID);
	assertEquals(page.handler.fileList, {[id]: LOBBY});
	assertEquals(page.handler.metaDataList, {});
	assertEquals(container.querySelector('.tooltip').innerHTML, 'lobby.jpg<br>1.50 MB<br>image/jpeg');
	assertEquals(container.querySelector('.closeButton').textContent, '×');
	assertEquals(container.querySelector('img').className, 'previewElement');
	assertEquals(page.uploader.calls, ['enable']);
});

Deno.test('the metadata of a recording are kept for the upload', () =>
{
	using page = createPage();

	page.handler.addFile(LOBBY, {duration: 12});

	const id = previews(page)[0].getAttribute('data-preview-id');
	assertEquals(page.handler.metaDataList, {[id]: {duration: 12}});
});

Deno.test('a file the factory does not know is refused with a message', () =>
{
	using page      = createPage();
	using alertStub = stub(globalThis, 'alert');

	page.handler.addFile({name: 'setup.exe', size: 1, type: 'unknown'}, {duration: 1});

	assertEquals(alertStub.calls[0].args, ['setup.exe not supported.Error: File type: unknown is not supported']);
	assertEquals(previews(page), []);
	assertEquals([page.handler.fileList, page.handler.metaDataList], [{}, {}]);
	assertEquals(page.uploader.calls, []);
});

Deno.test('the tooltip is shown while the mouse is over the preview', () =>
{
	using page = createPage();
	page.handler.addFile(LOBBY, null);
	const [container] = previews(page);
	const tooltip     = container.querySelector('.tooltip');

	container.dispatchEvent(new Event('mouseenter'));
	assertEquals(tooltip.style.visibility, 'visible');

	container.dispatchEvent(new Event('mouseleave'));
	assertEquals(tooltip.style.visibility, 'hidden');
});

Deno.test('the close button removes the file, the last one disables the upload', () =>
{
	using page = createPage();
	page.handler.addFile(LOBBY, {origin: 'File upload'});
	page.handler.addFile({...LOBBY, name: 'hall.jpg'}, {origin: 'File upload'});
	const [first, second] = previews(page);

	first.querySelector('.closeButton').dispatchEvent(new Event('click'));
	assertEquals(previews(page).length, 1);
	assertSameElement(previews(page)[0], second);
	assertEquals(Object.values(page.handler.fileList).map(file => file.name), ['hall.jpg']);
	assertEquals(page.uploader.calls, ['enable', 'enable']);

	second.querySelector('.closeButton').dispatchEvent(new Event('click'));
	assertEquals(previews(page), []);
	assertEquals(page.handler.fileList, {});
	assertEquals(page.handler.metaDataList, {});
	assertEquals(page.uploader.calls, ['enable', 'enable', 'disable']);
});

Deno.test('removing the file in upload aborts its upload, others not', () =>
{
	using page = createPage();
	page.handler.addFile(LOBBY, null);
	page.handler.addFile({...LOBBY, name: 'hall.jpg'}, null);
	const [lobbyId, hallId] = previews(page).map(container => container.getAttribute('data-preview-id'));
	let aborts = 0;
	page.handler.setUploadHandler({abort: () => aborts++}, lobbyId);

	page.handler.removeFromPreview(hallId);
	assertEquals(aborts, 0);

	page.handler.removeFromPreview(lobbyId);
	assertEquals(aborts, 1);
});

Deno.test('without an upload nothing is aborted', () =>
{
	using page = createPage();
	page.handler.addFile(LOBBY, null);

	page.handler.removeFromPreview(previews(page)[0].getAttribute('data-preview-id'));

	assertEquals(previews(page), []);
});

Deno.test('every file gets its own id, also without https', () =>
{
	for (const protocol of ['https:', 'http:'])
	{
		using page = createPage(protocol);
		const ids  = new Set();

		for (let i = 0; i < 50; i++)
			ids.add(page.handler.generateUUID());

		assertEquals(ids.size, 50, protocol);
		for (const id of ids)
			assertMatch(id, UUID, protocol);
	}
});

Deno.test('https uses the UUID of the browser', () =>
{
	using page = createPage('https:');
	using _uuid = stub(crypto, 'randomUUID', () => '11111111-1111-4111-8111-111111111111');

	assertEquals(page.handler.generateUUID(), '11111111-1111-4111-8111-111111111111');
});
