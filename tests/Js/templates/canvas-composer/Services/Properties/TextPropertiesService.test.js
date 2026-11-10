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

import {assertEquals}                from "@std/assert";
import {FakeFabricWrapper, FakeText} from "@support/ComposerPropertiesFakes.js";
import {TextPropertiesService}       from "@js/templates/canvas-composer/Services/Properties/TextPropertiesService.js";

function createService(options = {})
{
	const wrapper  = new FakeFabricWrapper();
	const text     = new FakeText({fontFamily: 'Arial', fontSize: 40, fontWeight: 'normal', fontStyle: 'normal',
		textAlign: 'left', underline: false, ...options});
	wrapper.active = text;
	return {wrapper, text, service: new TextPropertiesService(wrapper)};
}

Deno.test('the font and its size come from the first character or from the text', () =>
{
	const {text, service} = createService();
	assertEquals([service.getTextFontFamily(), service.getTextFontSize()], ['Arial', 40]);

	text.charStyles[0].fontFamily = 'Lato';
	text.charStyles[0].fontSize   = 20;
	assertEquals([service.getTextFontFamily(), service.getTextFontSize()], ['Lato', 20]);
});

Deno.test('in editing the font comes from the selected characters', () =>
{
	const {text, service} = createService({isEditing: true, selectionStart: 3, selectionEnd: 5});
	text.charStyles[3].fontFamily = 'Lato';

	assertEquals(service.getTextFontFamily(), 'Lato');
});

Deno.test('a font is set for the whole text or the selected characters', () =>
{
	const whole = createService();
	whole.service.setTextFontFamily('Lato');
	assertEquals(whole.text.charStyles.map(style => style.fontFamily), ['Lato', 'Lato', 'Lato', 'Lato', 'Lato']);
	assertEquals(whole.wrapper.renders, 1);

	const part = createService({selectionStart: 1, selectionEnd: 2});
	part.service.setTextFontFamily('Lato');
	assertEquals(part.text.charStyles.map(style => style.fontFamily ?? null), [null, 'Lato', null, null, null]);
});

Deno.test('the size without a selection is set on the text itself', () =>
{
	const {text, service} = createService();

	service.setTextFontSize(60);

	assertEquals(text.fontSize, 60);
	assertEquals(text.calls.at(-1), ['removeStyle', 'fontSize']);
});

Deno.test('the alignment goes round from left to center to right', () =>
{
	const {text, service} = createService();

	const aligns = [];
	for (let i = 0; i < 4; i++)
	{
		service.setTextAlign();
		aligns.push(service.getTextAlign());
	}

	assertEquals(aligns, ['center', 'right', 'left', 'center']);
	assertEquals(text.textAlign, 'center');
});

Deno.test('another alignment, e.g. justify, starts again on the left', () =>
{
	const {text, service} = createService({textAlign: 'justify'});

	service.setTextAlign();

	assertEquals(text.textAlign, 'left');
});

Deno.test('bold and italic are switched on and off for the whole text', () =>
{
	const {text, service} = createService();

	service.setTextBold();
	service.setTextItalic();
	assertEquals([service.getTextBold(), service.getTextItalic()], ['bold', 'italic']);
	assertEquals(text.charStyles.every(style => style.fontWeight === 'bold' && style.fontStyle === 'italic'), true);

	service.setTextBold();
	service.setTextItalic();
	assertEquals([service.getTextBold(), service.getTextItalic()], ['normal', 'normal']);
});

Deno.test('bold of the selected characters is switched by the first of them', () =>
{
	const {text, service} = createService({isEditing: true, selectionStart: 1, selectionEnd: 3});
	text.charStyles[1].fontWeight = 'bold';

	service.setTextBold();

	assertEquals(text.charStyles.map(style => style.fontWeight ?? null), [null, 'normal', 'normal', null, null]);
});

Deno.test('the underline is switched for the whole text', () =>
{
	const {wrapper, text, service} = createService();

	service.setTextUnderline();
	assertEquals(text.underline, true);
	service.setTextUnderline();
	assertEquals(text.underline, false);
	assertEquals(wrapper.renders, 2);
});

Deno.test('the canvas can be drawn again for the text', () =>
{
	const {wrapper, text, service} = createService();

	service.justUpdateCanvas();

	assertEquals(wrapper.modified.length, 1);
	assertEquals(wrapper.modified[0] === text, true);
	assertEquals(wrapper.renders, 1);
});
