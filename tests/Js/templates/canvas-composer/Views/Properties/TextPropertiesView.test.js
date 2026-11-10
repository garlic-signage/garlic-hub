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
import {assertSameElement, createDom}             from "@support/dom.js";
import {FakeToggleButtonFactory, PROPERTIES_PAGE} from "@support/ComposerPropertiesFakes.js";
import {TextPropertiesView}                       from "@js/templates/canvas-composer/Views/Properties/TextPropertiesView.js";

function createView()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const factory = new FakeToggleButtonFactory();
	const view    = new TextPropertiesView(factory);
	return {document: dom.document, view, buttons: factory.buttons, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

Deno.test('the buttons of the text are made from the page', () =>
{
	using page = createView();

	for (const id of ['textAlign', 'textBold', 'textItalic', 'textUnderline'])
		assertSameElement(page.view[id], page.document.getElementById(id), id);
	assertSameElement(page.view.fontsDropdown, page.document.getElementById('fontsDropdown'));
	assertSameElement(page.view.fontSize, page.document.getElementById('fontSize'));
});

Deno.test('the panel is shown and hidden', () =>
{
	using page = createView();
	const panel = page.document.getElementById('textProperties');

	page.view.show();
	assertEquals(panel.style.display, 'flex');
	page.view.hide();
	assertEquals(panel.style.display, 'none');
});

Deno.test('the font is shown as name, its size in the field', () =>
{
	using page = createView();

	page.view.setfontFamilyValue('Lato');
	page.view.setFontSizeValue(48);

	assertEquals(page.document.getElementById('fontFamily').textContent, 'Lato');
	assertEquals(page.view.getFontSizeValue(), '48');
});

Deno.test('the alignment shows its icon, bold, italic and underline their state', () =>
{
	using page = createView();

	page.view.setTextAlignValue('center');
	page.view.setTextBoldValue('bold');
	page.view.setTextItalicValue('normal');
	page.view.setTextUnderlineValue(true);

	assertEquals(page.buttons.get('textAlign').updates, [['display', 'center']]);
	assertEquals(page.buttons.get('textBold').updates, [['active', 'bold']]);
	assertEquals(page.buttons.get('textItalic').updates, [['active', 'normal']]);
	assertEquals(page.buttons.get('textUnderline').updates, [['active', true]]);
});

Deno.test('emptying the list of fonts keeps its first element', () =>
{
	using page = createView();
	const list = page.view.fontsDropdown;
	for (const name of ['upload', 'Arial', 'Lato'])
	{
		const div = page.document.createElement('div');
		div.textContent = name;
		list.appendChild(div);
	}

	page.view.clearFontDropdown();

	assertEquals([...list.children].map(child => child.textContent), ['upload']);
});
