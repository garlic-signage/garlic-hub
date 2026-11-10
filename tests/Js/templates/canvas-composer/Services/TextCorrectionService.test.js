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
import {callNames, composerWaitOverlay, composerWrapper, FakeFabricObject, FLASH_TEMPLATE, flashMessages} from "@support/ComposerServicesFakes.js";
import {createDom}                                          from "@support/dom.js";
import {TextCorrectionService}                              from "@js/templates/canvas-composer/Services/TextCorrectionService.js";

/**
 * The model of the user: answers with the text set by the test, or fails with an Error.
 */
class FakeTemplatesService
{
	calls  = [];
	answer = {success: true, text: 'Welcome to the lobby'};

	correctText(text)                { return this.#answer(['correctText', text]); }
	shortenText(text, maxLength)     { return this.#answer(['shortenText', text, maxLength]); }
	translateText(text, locale)      { return this.#answer(['translateText', text, locale]); }

	async #answer(call)
	{
		this.calls.push(call);
		if (this.answer instanceof Error)
			throw this.answer;
		return this.answer;
	}
}

/**
 * A textbox of 560 x 58 at the top 100, the font 50 pixels high, on a template of 1080 pixels height.
 */
function textbox(options = {})
{
	const object = new FakeFabricObject({type: 'textbox', text: 'Welcom to the loby', fontSize: 50, scaleX: 1, scaleY: 1,
		width: 560, styles: {0: {0: {fill: 'red'}}}, isEditing: false, ...options});
	object.exitEditing = () => { object.isEditing = false; object.calls.push(['exitEditing']); };
	object.getBoundingRect = () => ({top: object.top ?? 100});
	return object;
}

function createService(active = textbox())
{
	const dom       = createDom(FLASH_TEMPLATE);
	const wrapper   = composerWrapper({getActiveObject: () => active});
	const templates = new FakeTemplatesService();
	const overlay   = composerWaitOverlay();
	const context   = {getLangByKey: (key) => 'lang ' + key};

	return {
		document: dom.document, wrapper, templates, overlay, active,
		service: new TextCorrectionService(wrapper, templates, overlay, context, {height: 1080}),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('the corrected text replaces the old one as one step of the history', async () =>
{
	using page = createService();

	await page.service.correctActiveText();

	assertEquals(page.templates.calls, [['correctText', 'Welcom to the loby']]);
	assertEquals(page.active.text, 'Welcome to the lobby');
	// the styles of single characters belong to the old text
	assertEquals(page.active.styles, {});
	assertEquals(callNames(page.wrapper), ['getActiveObject', 'fireObjectModified', 'renderAll']);
	assertEquals(flashMessages(page.document), [['success', 'lang text_corrected']]);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('a text in the editing leaves it before the new text', async () =>
{
	using page = createService(textbox({isEditing: true}));

	await page.service.correctActiveText();

	assertEquals(page.active.calls, [['exitEditing']]);
	assertEquals(page.active.text, 'Welcome to the lobby');
});

Deno.test('an unchanged text is no step of the history and no message', async () =>
{
	using page = createService(textbox({text: 'Welcome to the lobby'}));

	await page.service.correctActiveText();

	assertEquals(callNames(page.wrapper), ['getActiveObject']);
	assertEquals(flashMessages(page.document), []);
	assertEquals(page.overlay.calls, ['start', 'stop']);
});

Deno.test('the text is translated into the chosen language', async () =>
{
	using page = createService();
	page.templates.answer = {success: true, text: 'Willkommen in der Lobby'};

	await page.service.translateActiveText('de');

	assertEquals(page.templates.calls, [['translateText', 'Welcom to the loby', 'de']]);
	assertEquals(page.active.text, 'Willkommen in der Lobby');
	assertEquals(flashMessages(page.document), [['success', 'lang text_translated']]);
});

Deno.test('the shortened text fits from the top of the box to the bottom of the template', async () =>
{
	using page = createService();

	await page.service.shortenActiveText();

	// 20 characters of 27.5 pixels in 560, 16 lines of 58 pixels in the 980 below the top
	assertEquals(page.templates.calls, [['shortenText', 'Welcom to the loby', 320]]);
	assertEquals(flashMessages(page.document), [['success', 'lang text_shortened']]);
});

Deno.test('the scale of the box counts for the capacity', async () =>
{
	using page = createService(textbox({scaleX: 2, scaleY: 2, fontSize: 25, lineHeight: 1}));

	await page.service.shortenActiveText();

	// 40 characters in 1120 pixels, 19 lines of 50 pixels in 980
	assertEquals(page.templates.calls[0][2], 760);
});

Deno.test('a text at the bottom edge gets one line at least', async () =>
{
	using page = createService(textbox({top: 1070}));

	await page.service.shortenActiveText();

	assertEquals(page.templates.calls[0][2], 20);
});

Deno.test('a refused or failed request shows the error and keeps the text', async () =>
{
	for (const answer of [{success: false, error_message: 'No model for texts'}, new Error('Timeout')])
	{
		using page = createService();
		page.templates.answer = answer;

		await page.service.correctActiveText();

		assertEquals(page.active.text, 'Welcom to the loby');
		assertEquals(flashMessages(page.document), [['error', answer.message ?? answer.error_message]]);
		assertEquals(page.overlay.calls, ['start', 'stop']);
	}
});

Deno.test('without a text with content nothing is asked', async () =>
{
	for (const active of [null, new FakeFabricObject({type: 'image'}), textbox({text: '   '})])
	{
		using page = createService(active);

		await page.service.correctActiveText();
		await page.service.shortenActiveText();
		await page.service.translateActiveText('de');

		assertEquals(page.templates.calls, []);
		assertEquals(page.overlay.calls, []);
	}
});

Deno.test('text, i-text and textbox are texts', () =>
{
	using page = createService();

	assertEquals(['text', 'i-text', 'textbox', 'image'].map(type => page.service.isText({type})), [true, true, true, false]);
});
