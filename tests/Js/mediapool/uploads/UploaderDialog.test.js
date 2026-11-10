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

import {assertEquals}   from "@std/assert";
import {createDom}      from "@support/dom.js";
import {UploaderDialog} from "@js/mediapool/uploads/UploaderDialog.js";

// the uploader of templates/mediapool/overview.mustache
const PAGE = `
	<dialog id="uploaderDialog">
		<header><button id="closeUploadDialog" class="close-button bi bi-x" aria-label="Close"></button></header>
		<nav class="tab-header">
			<button class="tab-button active" data-tab="dragDropTab">Local files</button>
			<button class="tab-button" data-tab="linkTab">External files</button>
			<button class="tab-button" data-tab="webcamTab">Camera</button>
		</nav>
		<section class="tab-content active" id="dragDropTab"></section>
		<section class="tab-content" id="linkTab"></section>
		<section class="tab-content" id="webcamTab"></section>
		<button id="closeDialog">Close</button>
	</dialog>
	<button id="openUploadDialog" class="primary"></button>`;

class FakeDirectoryView
{
	reloads = 0;

	reloadCurrentNode()
	{
		this.reloads++;
	}
}

/**
 * The parts of the UploadDialogElements: they read the document when the module is loaded, here the page is new.
 */
function dialogElements(document)
{
	return {
		uploaderDialog:    document.getElementById('uploaderDialog'),
		openUploadDialog:  document.getElementById('openUploadDialog'),
		closeDialog:       document.getElementById('closeDialog'),
		closeUploadDialog: document.getElementById('closeUploadDialog'),
		tabButtons:        document.querySelectorAll('.tab-button'),
		tabContents:       document.querySelectorAll('.tab-content'),
		getTargetTab:      (tab) => document.getElementById(tab)
	};
}

function createPage()
{
	const dom           = createDom(PAGE);
	const document      = dom.document;
	const directoryView = new FakeDirectoryView();
	const elements      = dialogElements(document);

	return {
		document, directoryView, elements,
		dialog: new UploaderDialog(elements, directoryView),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function click(page, id)
{
	page.document.getElementById(id).dispatchEvent(new Event('click'));
}

function isOpen(page)
{
	return page.elements.uploaderDialog.hasAttribute('open');
}

function pressEscape(page)
{
	const event = new Event('keydown', {cancelable: true});
	event.key   = 'Escape';
	page.document.dispatchEvent(event);
	return event;
}

function activeTabs(page)
{
	return [...page.document.querySelectorAll('.tab-button.active, .tab-content.active')]
		.map(element => element.dataset.tab ?? element.id);
}

Deno.test('the uploader opens only after a directory with the right to create is chosen', () =>
{
	using page = createPage();

	assertEquals(page.elements.openUploadDialog.disabled, true);
});

Deno.test('the button opens the uploader, both close buttons close it', () =>
{
	for (const closeButton of ['closeDialog', 'closeUploadDialog'])
	{
		using page = createPage();

		click(page, 'openUploadDialog');
		assertEquals(isOpen(page), true, closeButton);

		click(page, closeButton);
		assertEquals(isOpen(page), false, closeButton);
	}
});

Deno.test('the closed uploader shows the new media of the directory', () =>
{
	using page = createPage();
	click(page, 'openUploadDialog');

	page.elements.uploaderDialog.close();

	assertEquals(page.directoryView.reloads, 1);
});

Deno.test('during an upload the uploader cannot be closed', () =>
{
	using page = createPage();

	page.dialog.disableActions();

	assertEquals(page.elements.closeDialog.disabled, true);
	assertEquals(page.elements.closeUploadDialog.disabled, true);
	assertEquals(pressEscape(page).defaultPrevented, true);
});

Deno.test('after the upload the uploader can be closed again', () =>
{
	using page = createPage();
	page.dialog.disableActions();

	page.dialog.enableActions();

	assertEquals(page.elements.closeDialog.disabled, false);
	assertEquals(page.elements.closeUploadDialog.disabled, false);
	assertEquals(pressEscape(page).defaultPrevented, false);
});

Deno.test('other keys are not stopped during an upload', () =>
{
	using page = createPage();
	page.dialog.disableActions();

	const event = new Event('keydown', {cancelable: true});
	event.key   = 'Enter';
	page.document.dispatchEvent(event);

	assertEquals(event.defaultPrevented, false);
});

Deno.test('a tab shows its content and hides the others', () =>
{
	using page = createPage();
	const [, linkButton, webcamButton] = page.elements.tabButtons;

	linkButton.dispatchEvent(new Event('click'));
	assertEquals(activeTabs(page), ['linkTab', 'linkTab']);

	webcamButton.dispatchEvent(new Event('click'));
	assertEquals(activeTabs(page), ['webcamTab', 'webcamTab']);
});
