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

import {assertEquals}            from "@std/assert";
import {replaceGlobals}          from "@support/globals.js";
import {element, fire, recorder} from "@support/ComposerControllerFakes.js";
import {busOfTest}               from "@support/MultizoneEvents.js";
import {SaveController}          from "@js/templates/canvas-composer/Controller/SaveController.js";

/**
 * A template (itemId 0) or the template of an item of a playlist.
 */
function createController({itemId = 0, changed = false, confirmed = true, withReset = true, withFormat = true} = {})
{
	const browser   = element({location: {href: '/templates/compose/5'}});
	const questions = [];
	const globals   = replaceGlobals({window: browser, confirm: (text) => { questions.push(text); return confirmed; }});
	const bus       = busOfTest();
	const view      = recorder(['getExportFormatValue', 'getExportQualityValue', 'setSaveNotify', 'unsetSaveNotify',
		'hideExportQuality', 'showExportQuality'], {getExportFormatValue: () => view.format, getExportQualityValue: 85});
	view.format       = 'png';
	view.saveButton   = element();
	view.closeButton  = element();
	view.resetButton  = withReset ? element() : null;
	view.exportFormat = withFormat ? element() : null;
	const context  = {itemId, playlistId: 3, templateId: 5, getLangByKey: (key) => key === 'confirm_close' ? 'Leave without saving?' : key};
	const save     = recorder(['validateImageData', 'save', 'hasChanged'], {hasChanged: () => save.changed});
	save.changed   = changed;
	const load     = recorder(['resetFromTemplateDataBase']);
	new SaveController(view, context, save, load, {width: 1920, height: 1080});

	return {
		browser, questions, bus, view, save, load,
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); }
	};
}

Deno.test('a template is saved as image of the chosen format in the original size', () =>
{
	using page = createController();

	fire(page.view.saveButton, 'click');

	assertEquals(page.save.calls, [['validateImageData', 'png', 85], ['save', 1920, 1080, false, 5]]);
});

Deno.test('the template of a playlist item is saved for the item', () =>
{
	using page = createController({itemId: 12});

	fire(page.view.saveButton, 'click');

	assertEquals(page.save.calls.at(-1), ['save', 1920, 1080, true, 12]);
});

Deno.test('the save button shows unsaved changes', () =>
{
	using page = createController();

	page.bus.send('setChanged');
	page.bus.send('resetChanged');

	assertEquals(page.view.calls, [['setSaveNotify'], ['unsetSaveNotify']]);
});

Deno.test('the reset loads the template from the database again', () =>
{
	using page = createController();

	fire(page.view.resetButton, 'click');

	assertEquals(page.load.calls, [['resetFromTemplateDataBase', 5]]);
});

Deno.test('BMP has no quality, the other formats have one', () =>
{
	using page = createController();

	for (const format of ['bmp', 'jpg', 'png'])
	{
		page.view.format = format;
		fire(page.view.exportFormat, 'change');
	}

	assertEquals(page.view.calls.filter(call => call[0].endsWith('ExportQuality')).map(call => call[0]),
		['hideExportQuality', 'showExportQuality', 'showExportQuality']);
});

Deno.test('a page without reset and format still saves', () =>
{
	using page = createController({withReset: false, withFormat: false});

	fire(page.view.saveButton, 'click');

	assertEquals(page.save.calls.length, 2);
});

Deno.test('closing without changes goes back to the templates or the playlist', () =>
{
	{
		using page = createController();
		fire(page.view.closeButton, 'click');
		assertEquals([page.browser.location.href, page.questions], ['/templates/', []]);
	}
	{
		using page = createController({itemId: 12});
		fire(page.view.closeButton, 'click');
		assertEquals(page.browser.location.href, '/playlists/compose/3');
	}
});

Deno.test('closing with changes asks first and stays when the user says no', () =>
{
	{
		using page = createController({changed: true, confirmed: false});
		fire(page.view.closeButton, 'click');
		assertEquals([page.browser.location.href, page.questions], ['/templates/compose/5', ['Leave without saving?']]);
	}
	{
		using page = createController({changed: true, confirmed: true});
		fire(page.view.closeButton, 'click');
		assertEquals(page.browser.location.href, '/templates/');
	}
});

Deno.test('the browser asks before it leaves a template with changes', () =>
{
	using page = createController();

	assertEquals(fire(page.browser, 'beforeunload').defaultPrevented, false);

	page.save.changed = true;
	assertEquals(fire(page.browser, 'beforeunload').defaultPrevented, true);
});

Deno.test('after the confirmed close the browser does not ask a second time', () =>
{
	using page = createController({changed: true, confirmed: true});

	fire(page.view.closeButton, 'click');

	assertEquals(fire(page.browser, 'beforeunload').defaultPrevented, false);
});
