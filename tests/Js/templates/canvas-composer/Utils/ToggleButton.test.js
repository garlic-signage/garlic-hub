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

import {assertEquals, assertInstanceOf} from "@std/assert";
import {assertSameElement, createDom}   from "@support/dom.js";
import {ToggleButton}                   from "@js/templates/canvas-composer/Utils/ToggleButton.js";
import {ToggleButtonFactory}            from "@js/templates/canvas-composer/Utils/ToggleButtonFactory.js";

// the button of the text alignment and of bold in templates/templates/canvas.mustache
const PAGE = `
	<button id="textAlign" class="svg_button">
		<i id="left" class="bi bi-justify-left"></i>
		<i id="center" class="bi bi-justify"></i>
		<i id="right" class="bi bi-justify-right"></i>
	</button>
	<button id="textBold" name="bold" class="bi bi-type-bold"></button>`;

function displays(element)
{
	return [...element.children].map(child => [child.id, child.style.display]);
}

Deno.test('the factory makes a toggle button of the element', () =>
{
	using dom     = createDom(PAGE);
	const element = dom.document.getElementById('textBold');

	const button = new ToggleButtonFactory().create(element);

	assertInstanceOf(button, ToggleButton);
	assertSameElement(button.getElement(), element);
});

Deno.test('display shows only the child with the value as id', () =>
{
	using dom     = createDom(PAGE);
	const element = dom.document.getElementById('textAlign');
	const button  = new ToggleButton(element);

	button.update('display', 'center');
	assertEquals(displays(element), [['left', 'none'], ['center', 'block'], ['right', 'none']]);

	button.update('display', 'right');
	assertEquals(displays(element), [['left', 'none'], ['center', 'none'], ['right', 'block']]);
});

Deno.test('active colors the button when the value is its id', () =>
{
	using dom     = createDom(PAGE);
	const element = dom.document.getElementById('textBold');
	element.id    = 'bold';
	const button  = new ToggleButton(element);

	button.update('active', 'bold');
	assertEquals(element.style.fill, '#269CC0');

	button.update('active', 'normal');
	assertEquals(element.style.fill, '#000');
});

Deno.test('another type changes nothing', () =>
{
	using dom     = createDom(PAGE);
	const element = dom.document.getElementById('textAlign');

	new ToggleButton(element).update('unknown', 'center');

	assertEquals(displays(element), [['left', ''], ['center', ''], ['right', '']]);
	assertEquals(element.style.fill, '');
});

Deno.test('the button is shown and hidden', () =>
{
	using dom     = createDom(PAGE);
	const element = dom.document.getElementById('textBold');
	const button  = new ToggleButton(element);

	button.hide();
	assertEquals(element.style.display, 'none');

	button.show();
	assertEquals(element.style.display, 'block');
});
