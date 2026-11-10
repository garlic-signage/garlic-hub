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

import {assertEquals}                             from "@std/assert";
import {createDom}                                from "@support/dom.js";
import {FakeToggleButtonFactory, PROPERTIES_PAGE} from "@support/ComposerPropertiesFakes.js";
import {TextPropertiesView}                       from "@js/templates/canvas-composer/Views/Properties/TextPropertiesView.js";
import {TextPropertiesController}                 from "@js/templates/canvas-composer/Controller/Properties/TextPropertiesController.js";

class FakeTextPropertiesService
{
	calls     = [];
	align     = 'left';
	bold      = 'normal';
	italic    = 'normal';
	underline = 'normal';

	getTextFontFamily()        { return 'Arial'; }
	getTextFontSize()          { return 40; }
	getTextAlign()             { return this.align; }
	getTextBold()              { return this.bold; }
	getTextItalic()            { return this.italic; }
	getTextUnderline()         { return this.underline; }
	setTextAlign()             { this.calls.push(['setTextAlign']); this.align = 'center'; }
	setTextBold()              { this.calls.push(['setTextBold']); this.bold = 'bold'; }
	setTextItalic()            { this.calls.push(['setTextItalic']); this.italic = 'italic'; }
	setTextUnderline()         { this.calls.push(['setTextUnderline']); this.underline = 'underline'; }
	setTextFontSize(size)      { this.calls.push(['setTextFontSize', size]); }
	setTextFontFamily(name)    { this.calls.push(['setTextFontFamily', name]); }
	justUpdateCanvas()         { this.calls.push(['justUpdateCanvas']); }
}

/**
 * The fonts of the server with their preview; a font is loaded when it is chosen, the test lets it finish.
 */
class FakeFontLoader
{
	fontsList = [{name: 'Arial', preview: '<span>Arial</span>'}, {name: 'Lato', preview: '<span>Lato</span>'}];
	loads     = [];

	loadFontFace(fontId, callback)
	{
		this.loads.push([fontId, callback]);
	}
}

function createController(fontLoader = new FakeFontLoader())
{
	const dom     = createDom(PROPERTIES_PAGE);
	const factory = new FakeToggleButtonFactory();
	const service = new FakeTextPropertiesService();
	const view    = new TextPropertiesView(factory);
	const controller = new TextPropertiesController(view, service, fontLoader);
	return {document: dom.document, view, service, fontLoader, controller, buttons: factory.buttons, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function click(page, id)
{
	page.document.getElementById(id).dispatchEvent(new Event('click'));
}

function fonts(page)
{
	return [...page.document.querySelectorAll('#fontsDropdown .template_edit_dropdown_content_font')].map(font => font.innerHTML);
}

Deno.test('the panel shows the font, size, alignment and styles of the text', () =>
{
	using page = createController();

	page.controller.activate();

	assertEquals(page.document.getElementById('fontFamily').textContent, 'Arial');
	assertEquals(page.view.getFontSizeValue(), '40');
	assertEquals(page.buttons.get('textAlign').updates, [['display', 'left']]);
	assertEquals(['textBold', 'textItalic', 'textUnderline'].map(id => page.buttons.get(id).updates), [[['active', 'normal']], [['active', 'normal']], [['active', 'normal']]]);
	assertEquals(page.document.getElementById('textProperties').style.display, 'flex');
});

Deno.test('the panel is hidden', () =>
{
	using page = createController();
	page.controller.activate();

	page.controller.deactivate();

	assertEquals(page.document.getElementById('textProperties').style.display, 'none');
});

Deno.test('the buttons switch alignment and styles and show the new state', () =>
{
	using page = createController();

	for (const id of ['textAlign', 'textBold', 'textItalic', 'textUnderline'])
		click(page, id);

	assertEquals(page.service.calls, [['setTextAlign'], ['setTextBold'], ['setTextItalic'], ['setTextUnderline']]);
	assertEquals(page.buttons.get('textAlign').updates, [['display', 'center']]);
	assertEquals(page.buttons.get('textBold').updates, [['active', 'bold']]);
	assertEquals(page.buttons.get('textItalic').updates, [['active', 'italic']]);
	assertEquals(page.buttons.get('textUnderline').updates, [['active', 'underline']]);
});

Deno.test('a changed size is set as whole number', () =>
{
	using page = createController();
	const field = page.document.getElementById('fontSize');

	field.value = '36';
	field.dispatchEvent(new Event('input'));

	assertEquals(page.service.calls, [['setTextFontSize', 36]]);
});

Deno.test('the fonts of the server are listed with their preview', () =>
{
	using page = createController();

	assertEquals(fonts(page), ['<span>Arial</span>', '<span>Lato</span>']);
});

Deno.test('without a list of fonts the list stays empty', () =>
{
	const loader = new FakeFontLoader();
	loader.fontsList = {};
	using page = createController(loader);

	assertEquals(fonts(page), []);
});

Deno.test('a chosen font is set when it is loaded', () =>
{
	using page = createController();

	page.document.querySelectorAll('#fontsDropdown .template_edit_dropdown_content_font')[1].dispatchEvent(new Event('click'));
	assertEquals(page.fontLoader.loads.map(load => load[0]), [1]);
	assertEquals(page.service.calls, []);

	page.fontLoader.loads[0][1]();

	assertEquals(page.service.calls, [['setTextFontFamily', 'Lato'], ['justUpdateCanvas']]);
	assertEquals(page.document.getElementById('fontFamily').textContent, 'Lato');
});
