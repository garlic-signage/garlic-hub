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

import {assertEquals}                            from "@std/assert";
import {FakeFabricWrapper, FakeObject, FakeText} from "@support/ComposerPropertiesFakes.js";
import {SelectivePropertiesService}              from "@js/templates/canvas-composer/Services/Properties/SelectivePropertiesService.js";

function createService(object)
{
	const wrapper  = new FakeFabricWrapper();
	wrapper.active = object;
	return {wrapper, service: new SelectivePropertiesService(wrapper)};
}

Deno.test('a shape shows and takes its fill colour', () =>
{
	const object = new FakeObject({type: 'rect', fill: '#123456'});
	const {wrapper, service} = createService(object);

	assertEquals(service.getFillColor(), '#123456');

	service.setFillColor('#abcdef');
	assertEquals(object.fill, '#abcdef');
	assertEquals(wrapper.renders, 1);
});

Deno.test('a text shows the colour of its first character when it is not edited', () =>
{
	for (const type of ['i-text', 'text', 'textbox'])
	{
		const text = new FakeText({type, fill: '#000000'});
		text.charStyles[0].fill = '#ff0000';
		const {service} = createService(text);

		assertEquals(service.getFillColor(), '#ff0000', type);
	}
});

Deno.test('a text in editing shows the colour of the selected characters', () =>
{
	const text = new FakeText({fill: '#000000', isEditing: true, selectionStart: 2, selectionEnd: 4});
	text.charStyles[2].fill = '#00ff00';
	const {service} = createService(text);

	assertEquals(service.getFillColor(), '#00ff00');
});

Deno.test('a text without styles of its characters shows its own colour', () =>
{
	const text = new FakeText({text: '', fill: '#000000'});
	const {service} = createService(text);

	assertEquals(service.getFillColor(), '#000000');
});

Deno.test('the colour of a text is set for the whole text or only for the selected characters', () =>
{
	const whole = new FakeText({fill: '#000000'});
	createService(whole).service.setFillColor('#ff0000');
	assertEquals(whole.fill, '#ff0000');

	const part = new FakeText({fill: '#000000', selectionStart: 0, selectionEnd: 2});
	createService(part).service.setFillColor('#ff0000');
	assertEquals(part.fill, '#000000');
	assertEquals(part.charStyles.map(style => style.fill ?? null), ['#ff0000', '#ff0000', null, null, null]);
});
