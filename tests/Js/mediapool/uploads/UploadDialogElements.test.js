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

// the uploader of templates/mediapool/overview.mustache
const PAGE = `
	<dialog id="uploaderDialog">
		<header><button id="closeUploadDialog"></button></header>
		<nav class="tab-header">
			<button class="tab-button active" data-tab="dragDropTab"></button>
			<button class="tab-button" data-tab="linkTab"></button>
		</nav>
		<section class="tab-content active" id="dragDropTab"></section>
		<section class="tab-content" id="linkTab"></section>
		<button id="closeDialog"></button>
	</dialog>
	<button id="openUploadDialog"></button>`;

// The button to open the uploader is read when the module is loaded: every test loads its own module for its page,
// the query makes it a new one. Fixed addresses, a computed one would need the right to read the files.

Deno.test('the elements of the uploader are read from the page', async () =>
{
	using dom      = createDom(PAGE);
	const document = dom.document;
	const UploadDialogElements = (await import("@js/mediapool/uploads/UploadDialogElements.js?test=1")).UploadDialogElements;

	const elements = new UploadDialogElements();

	assertSameElement(elements.uploaderDialog, document.getElementById('uploaderDialog'));
	assertSameElement(elements.closeDialog, document.getElementById('closeDialog'));
	assertSameElement(elements.closeUploadDialog, document.getElementById('closeUploadDialog'));
	assertEquals([...elements.tabButtons].map(button => button.dataset.tab), ['dragDropTab', 'linkTab']);
	assertEquals([...elements.tabContents].map(content => content.id), ['dragDropTab', 'linkTab']);
	assertSameElement(elements.getTargetTab('linkTab'), document.getElementById('linkTab'));
});

Deno.test('the button to open is the same for the class and every instance', async () =>
{
	using dom = createDom(PAGE);
	const UploadDialogElements = (await import("@js/mediapool/uploads/UploadDialogElements.js?test=2")).UploadDialogElements;
	const button = dom.document.getElementById('openUploadDialog');

	assertSameElement(UploadDialogElements.openUploadDialog, button);
	assertSameElement(new UploadDialogElements().openUploadDialog, button);
	assertSameElement(new UploadDialogElements().openUploadDialog, button);
});

Deno.test('the button to open stays the one of the page the module was loaded with', async () =>
{
	let UploadDialogElements, button;
	{
		using dom = createDom(PAGE);
		UploadDialogElements = (await import("@js/mediapool/uploads/UploadDialogElements.js?test=3")).UploadDialogElements;
		button = dom.document.getElementById('openUploadDialog');
	}
	using _dom = createDom(PAGE);

	assertSameElement(UploadDialogElements.openUploadDialog, button);
	assertSameElement(new UploadDialogElements().openUploadDialog, button);
});

Deno.test('a page without the uploader has no elements and no tabs', async () =>
{
	using _dom = createDom('');
	const UploadDialogElements = (await import("@js/mediapool/uploads/UploadDialogElements.js?test=4")).UploadDialogElements;

	const elements = new UploadDialogElements();

	assertEquals(UploadDialogElements.openUploadDialog, null);
	assertEquals([elements.uploaderDialog, elements.closeDialog, elements.closeUploadDialog], [null, null, null]);
	assertEquals([elements.tabButtons.length, elements.tabContents.length], [0, 0]);
	assertEquals(elements.getTargetTab('linkTab'), null);
});
