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

import {assertEquals}                  from "@std/assert";
import {createDom}                     from "@support/dom.js";
import {replaceGlobals}                from "@support/globals.js";
import {element, fire, recorder, tick} from "@support/ComposerControllerFakes.js";
import {busOfTest}                     from "@support/MultizoneEvents.js";
import {MediaDialogController}         from "@js/templates/canvas-composer/Controller/MediaDialogController.js";

const FLASH = '<template id="flashMessageTemplate"><div class="message-bar"><span></span><button class="close-button"></button></div></template>';

// the texts of the page, the controller reads the global lang
const LANG = {add_image: 'Add image', replace_image: 'Replace image'};

const MEDIA = [
	{id: 'm1', src: '/original/m1', fileName: 'f00d.jpg'},
	{id: 'm2', src: '/original/m2', fileName: 'beef.png'},
];

class FakeInsertService
{
	calls = [];
	error = null;

	async insertImage(...args)
	{
		this.calls.push(['insertImage', ...args]);
	}

	async replaceImage(...args)
	{
		this.calls.push(['replaceImage', ...args]);
		if (this.error !== null)
			throw this.error;
	}
}

function createController()
{
	const dom      = createDom(FLASH);
	const globals  = replaceGlobals({lang: LANG});
	const bus      = busOfTest();
	const view     = {
		addMedia: element({style: {}}), applyMedia: element({style: {}}), dialogName: {innerText: ''},
		closeEditMediaDialog: element(), closeDialogButton: element(), mediaSelectorElement: {id: 'selector'},
		mediaSelectorDialog: recorder(['showModal', 'close'])
	};
	const selector = recorder(['enableMultiSelect', 'disableMultiSelect', 'showSelector', 'getSelectedMedia'],
		{getSelectedMedia: () => selector.selected});
	selector.selected = MEDIA;
	const insert     = new FakeInsertService();
	const controller = new MediaDialogController(view, selector, insert);

	return {
		document: dom.document, bus, view, selector, insert, controller,
		errors: () => [...dom.document.querySelectorAll('.message-bar span')].map(span => span.textContent),
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('the dialog to insert chooses several images', async () =>
{
	using page = createController();

	page.bus.send('openMediaDialogForInsert');
	await tick();

	assertEquals([page.view.addMedia.style.display, page.view.applyMedia.style.display], ['inline', 'none']);
	assertEquals(page.view.dialogName.innerText, 'Add image');
	assertEquals(page.selector.calls, [['enableMultiSelect'], ['showSelector', page.view.mediaSelectorElement]]);
	assertEquals(page.view.mediaSelectorDialog.calls, [['showModal']]);
	assertEquals(page.controller.isOpen, true);
});

Deno.test('the dialog to replace chooses one image', async () =>
{
	using page = createController();

	page.bus.send('openMediaDialogForReplace');
	await tick();

	assertEquals([page.view.addMedia.style.display, page.view.applyMedia.style.display], ['none', 'inline']);
	assertEquals(page.view.dialogName.innerText, 'Replace image');
	assertEquals(page.selector.calls[0], ['disableMultiSelect']);
	assertEquals(page.controller.isOpen, true);
});

Deno.test('the chosen images are inserted one below the other and the dialog closes', async () =>
{
	using page = createController();
	await page.controller.displayMediaSelector();

	fire(page.view.addMedia, 'click');
	await tick();

	assertEquals(page.insert.calls, [['insertImage', 'm1', '/original/m1', 'f00d.jpg', 0], ['insertImage', 'm2', '/original/m2', 'beef.png', 1]]);
	assertEquals(page.view.mediaSelectorDialog.calls, [['showModal'], ['close']]);
	assertEquals(page.controller.isOpen, false);
});

Deno.test('the first chosen image replaces the active one and the dialog closes', async () =>
{
	using page = createController();

	fire(page.view.applyMedia, 'click');
	await tick();

	assertEquals(page.insert.calls, [['replaceImage', 'm1', '/original/m1', 'f00d.jpg']]);
	assertEquals(page.view.mediaSelectorDialog.calls, [['close']]);
});

Deno.test('without a chosen image nothing is replaced and the dialog stays', async () =>
{
	using page = createController();
	page.selector.selected = [];

	fire(page.view.applyMedia, 'click');
	await tick();

	assertEquals(page.insert.calls, []);
	assertEquals(page.view.mediaSelectorDialog.calls, []);
});

Deno.test('an image which could not replace the active one shows the error, the dialog closes', async () =>
{
	using page = createController();
	page.insert.error = new Error('Not an image');

	fire(page.view.applyMedia, 'click');
	await tick();

	assertEquals(page.errors(), ['Not an image']);
	assertEquals(page.view.mediaSelectorDialog.calls, [['close']]);
});

Deno.test('both close buttons close the dialog', async () =>
{
	using page = createController();

	for (const button of [page.view.closeEditMediaDialog, page.view.closeDialogButton])
	{
		await page.controller.displayMediaSelector();
		fire(button, 'click');
		assertEquals(page.controller.isOpen, false);
	}

	assertEquals(page.view.mediaSelectorDialog.calls, [['showModal'], ['close'], ['showModal'], ['close']]);
});
