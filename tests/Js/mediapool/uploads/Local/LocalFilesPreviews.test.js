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

import {assertEquals}       from "@std/assert";
import {createDom}          from "@support/dom.js";
import {replaceGlobals}     from "@support/globals.js";
import {LocalFilesPreviews} from "@js/mediapool/uploads/Local/LocalFilesPreviews.js";

/**
 * Builds a preview for every file, like the PreviewFactory.
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
		const document = this.#document;
		return {
			createPreview: () => document.createElement('img'),
			extractMetadata: () => ({name: file.name, size: '1 KB', type: file.type})
		};
	}
}

class FakeUploader
{
	enabled = 0;

	enableUploadButton()
	{
		this.enabled++;
	}
}

function createPage()
{
	const dom      = createDom('<div id="dropzonePreview"></div>');
	// the ids come from crypto.randomUUID() on https
	const globals  = replaceGlobals({location: {protocol: 'https:'}});
	const area     = dom.document.getElementById('dropzonePreview');
	const previews = new LocalFilesPreviews(area, new FakePreviewFactory(dom.document));
	const uploader = new FakeUploader();
	previews.fileUploader = uploader;

	return {
		area, previews, uploader,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('every chosen file gets a preview and the upload can start', () =>
{
	using page  = createPage();
	const files = [new File(['a'], 'lobby.jpg', {type: 'image/jpeg'}), new File(['b'], 'hall.jpg', {type: 'image/jpeg'})];

	page.previews.handleFiles(files);

	assertEquals(Object.values(page.previews.fileList), files);
	assertEquals(page.area.querySelectorAll('.previewContainer').length, 2);
	assertEquals(page.uploader.enabled, 2);
});

Deno.test('a file from the disk is marked as file upload without a page', () =>
{
	using page = createPage();

	page.previews.handleFiles([new File(['a'], 'lobby.jpg')]);

	assertEquals(Object.values(page.previews.metaDataList), [{origin: 'File upload', page_url: ''}]);
});

Deno.test('the FileList of the browser is taken like an array', () =>
{
	using page = createPage();
	const file = new File(['a'], 'lobby.jpg');

	page.previews.handleFiles({0: file, length: 1});

	assertEquals(Object.values(page.previews.fileList), [file]);
});

Deno.test('the ids of the previews are the ones of the files', () =>
{
	using page = createPage();

	page.previews.handleFiles([new File(['a'], 'lobby.jpg'), new File(['b'], 'hall.jpg')]);

	const ids = [...page.area.querySelectorAll('.previewContainer')].map(container => container.getAttribute('data-preview-id'));
	assertEquals(ids, Object.keys(page.previews.fileList));
	assertEquals(Object.keys(page.previews.metaDataList), ids);
});
