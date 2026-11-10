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
import {assertSameElement, createDom} from "@support/dom.js";
import {LocalFilesElements}           from "@js/mediapool/uploads/Local/LocalFilesElements.js";

// the tab for local files of templates/mediapool/overview.mustache
const PAGE = `
	<section class="tab-content active" id="dragDropTab">
		<p id="dropzone"><input type="file" id="fileInput" style="display: none;" multiple></p>
		<div id="dropzonePreview" class="preview-media"></div>
		<button id="startLocalFilesUpload" class="upload-button primary"></button>
	</section>`;

Deno.test('the elements of the tab for local files are read from the page', () =>
{
	using dom      = createDom(PAGE);
	const document = dom.document;

	const elements = new LocalFilesElements();

	assertSameElement(elements.startFileUpload, document.getElementById('startLocalFilesUpload'));
	assertSameElement(elements.dropzone, document.getElementById('dropzone'));
	assertSameElement(elements.dropzonePreview, document.getElementById('dropzonePreview'));
	assertSameElement(elements.fileInput, document.getElementById('fileInput'));
});

Deno.test('a page without the tab has no elements', () =>
{
	using _dom = createDom('');

	const elements = new LocalFilesElements();

	assertEquals([elements.startFileUpload, elements.dropzone, elements.dropzonePreview, elements.fileInput],
		[null, null, null, null]);
});
