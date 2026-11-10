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
import {DragDropManager} from "@js/mediapool/uploads/Local/DragDropManager.js";

// the dropzone of templates/mediapool/overview.mustache
const PAGE = `
	<p id="dropzone">Insert files here<input type="file" id="fileInput" style="display: none;" multiple></p>`;

class FakeFilePreviews
{
	handled = [];

	handleFiles(files)
	{
		this.handled.push(files);
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const previews = new FakeFilePreviews();
	const elements = {dropzone: document.getElementById('dropzone'), fileInput: document.getElementById('fileInput')};
	new DragDropManager(elements, previews).init();

	return {document, previews, ...elements, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function fire(element, type, properties = {})
{
	const event = new Event(type, {cancelable: true});
	Object.assign(event, properties);
	element.dispatchEvent(event);
	return event;
}

Deno.test('dragging over the dropzone marks it and allows the drop', () =>
{
	using page = createPage();

	const event = fire(page.dropzone, 'dragover');

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.dropzone.style.borderColor, '#007bff');
});

Deno.test('leaving the dropzone removes the mark', () =>
{
	using page = createPage();
	fire(page.dropzone, 'dragover');

	fire(page.dropzone, 'dragleave');

	assertEquals(page.dropzone.style.borderColor, '#ccc');
});

Deno.test('dropped files are shown in the previews, the browser does not open them', () =>
{
	using page = createPage();
	const files = [new File(['a'], 'lobby.jpg'), new File(['b'], 'hall.jpg')];
	fire(page.dropzone, 'dragover');

	// the FileList of the browser is no array
	const event = fire(page.dropzone, 'drop', {dataTransfer: {files: {0: files[0], 1: files[1], length: 2}}});

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.dropzone.style.borderColor, '#ccc');
	assertEquals(page.previews.handled, [files]);
});

Deno.test('a click on the dropzone opens the file chooser', () =>
{
	using page = createPage();
	let opened = 0;
	page.fileInput.click = () => opened++;

	fire(page.dropzone, 'click');

	assertEquals(opened, 1);
});

Deno.test('the chosen files are shown in the previews', () =>
{
	using page  = createPage();
	const files = [new File(['a'], 'lobby.jpg')];
	Object.defineProperty(page.fileInput, 'files', {value: files});

	page.fileInput.dispatchEvent(new Event('change'));

	assertEquals(page.previews.handled, [files]);
});
