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

import {assertEquals}                                       from "@std/assert";
import {callNames, composerWaitOverlay, composerWrapper, DenoCustomEvent, FLASH_TEMPLATE, flashMessages} from "@support/ComposerServicesFakes.js";
import {createDom}                                          from "@support/dom.js";
import {busOfTest}                                          from "@support/MultizoneEvents.js";
import {replaceGlobals}                                     from "@support/globals.js";
import {SaveService}                                        from "@js/templates/canvas-composer/Services/SaveService.js";

class FakeTemplatesService
{
	calls  = [];
	answer = {success: true};

	async saveTemplateContent(id, content, image)
	{
		this.calls.push(['saveTemplateContent', id, JSON.parse(content), image]);
		return this.answer;
	}

	async savePlaylistItemContent(id, content, image)
	{
		this.calls.push(['savePlaylistItemContent', id, JSON.parse(content), image]);
		return this.answer;
	}
}

/**
 * The BmpDitherFactory: the converter gets the JPEG of the canvas, or fails with an Error.
 */
function bmpFactory(result = 'data:image/bmp;base64,Qk0=')
{
	const converted = [];
	return {
		converted,
		create: () => ({
			async convert(dataUrl)
			{
				converted.push(dataUrl);
				if (result instanceof Error)
					throw result;
				return result;
			}
		})
	};
}

function createService({hasChanged = true, bmp = bmpFactory()} = {})
{
	const dom       = createDom(FLASH_TEMPLATE);
	const globals   = replaceGlobals({CustomEvent: DenoCustomEvent});
	const bus       = busOfTest('canvasUpdated');
	const wrapper   = composerWrapper({
		toTemplateJSON: () => ({version: '6.0', objects: [{type: 'rect'}]}),
		toBase64Image: (format) => `data:image/${format};base64,AAAA`
	}, {hasChanged});
	const templates = new FakeTemplatesService();
	const overlay   = composerWaitOverlay();

	return {
		document: dom.document, wrapper, templates, overlay, bus, bmp,
		service: new SaveService(wrapper, templates, bmp, overlay),
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function imageCall(page)
{
	return page.wrapper.calls.find(call => call[0] === 'toBase64Image').slice(1);
}

Deno.test('the changes are the ones of the canvas', () =>
{
	using changed   = createService({hasChanged: true});
	using unchanged = createService({hasChanged: false});

	assertEquals([changed.service.hasChanged(), unchanged.service.hasChanged()], [true, false]);
});

Deno.test('a template is saved in its full size with the image of the canvas', async () =>
{
	using page = createService();

	await page.service.save(1920, 1080, false, 7);

	assertEquals(callNames(page.wrapper), ['setZoom', 'setWidth', 'setHeight', 'toTemplateJSON', 'toBase64Image', 'resetChange']);
	assertEquals(page.wrapper.calls.slice(0, 3), [['setZoom', 1], ['setWidth', 1920], ['setHeight', 1080]]);
	assertEquals(page.templates.calls, [['saveTemplateContent', 7,
		{version: '6.0', objects: [{type: 'rect'}], viewport: {width: 1920, height: 1080, scale: 100}}, 'data:image/jpeg;base64,AAAA']]);
	assertEquals(page.bus.events.map(event => event[0]), ['canvasUpdated']);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('the item of a playlist saves its own content', async () =>
{
	using page = createService();

	await page.service.save(1080, 1920, true, 12);

	assertEquals(page.templates.calls.map(call => [call[0], call[1]]), [['savePlaylistItemContent', 12]]);
});

Deno.test('the image is a JPEG of quality 90 on white by default', async () =>
{
	using page = createService();

	await page.service.save(1920, 1080, false, 7);

	assertEquals(imageCall(page), ['jpeg', 0.9, '#ffffff']);
});

Deno.test('the chosen format and quality are taken', async () =>
{
	using page = createService();
	page.service.validateImageData('webp', 75);

	await page.service.save(1920, 1080, false, 7);

	assertEquals(imageCall(page), ['webp', 0.75, '#ffffff']);
});

Deno.test('an unknown format is a JPEG, a quality out of range 90', async () =>
{
	for (const [format, quality, expected] of [['gif', 50, ['jpeg', 0.5]], ['jpg', 0, ['jpeg', 0.9]], ['png', 101, ['png', 0.9]], ['png', 100, ['png', 1]]])
	{
		using page = createService();
		page.service.validateImageData(format, quality);

		await page.service.save(1920, 1080, false, 7);

		assertEquals(imageCall(page).slice(0, 2), expected, `${format} ${quality}`);
	}
});

Deno.test('a BMP is converted from a JPEG of the best quality', async () =>
{
	using page = createService();
	page.service.validateImageData('bmp', 50);

	await page.service.save(1920, 1080, false, 7);

	assertEquals(imageCall(page), ['jpeg', 100, '#ffffff']);
	assertEquals(page.bmp.converted, ['data:image/jpeg;base64,AAAA']);
	assertEquals(page.templates.calls[0][3], 'data:image/bmp;base64,Qk0=');
});

Deno.test('a refused save shows the error and keeps the changes as unsaved', async () =>
{
	using page = createService();
	page.templates.answer = {success: false, error_message: 'No access'};

	await page.service.save(1920, 1080, false, 7);

	assertEquals(flashMessages(page.document), [['error', 'No access']]);
	assertEquals(callNames(page.wrapper).includes('resetChange'), false);
	assertEquals(page.bus.events, []);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('a failed conversion shows the error and saves nothing', async () =>
{
	using page = createService({bmp: bmpFactory(new Error('Out of memory'))});
	page.service.validateImageData('bmp', 90);

	await page.service.save(1920, 1080, false, 7);

	assertEquals(flashMessages(page.document), [['error', 'Out of memory']]);
	assertEquals(page.templates.calls, []);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});
