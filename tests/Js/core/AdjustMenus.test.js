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
import {replaceGlobals} from "@support/globals.js";
import {AdjustMenus}    from "@js/core/AdjustMenus.js";

/**
 * A dropdown with the given box in a window of 800 x 600.
 */
function dropdownAt(document, rect, top = '')
{
	const dropdown = document.getElementById('dropdown');
	dropdown.style.top = top;
	dropdown.getBoundingClientRect = () => rect;
	return dropdown;
}

function adjust(rect, top = '')
{
	using dom      = createDom('<ul id="dropdown"></ul>');
	using _globals = replaceGlobals({window: {innerWidth: 800, innerHeight: 600}});
	const dropdown = dropdownAt(dom.document, rect, top);

	AdjustMenus.adjustDropdownPosition(dropdown);

	return [dropdown.style.left, dropdown.style.right, dropdown.style.top];
}

Deno.test('a dropdown inside the window stays where it is', () =>
{
	assertEquals(adjust({left: 10, right: 200, bottom: 300}, '40px'), ['', '', '40px']);
});

Deno.test('a dropdown over the right edge is aligned to the right', () =>
{
	assertEquals(adjust({left: 700, right: 900, bottom: 300}), ['auto', '0', '']);
});

Deno.test('a dropdown over the left edge is aligned to the left', () =>
{
	assertEquals(adjust({left: -20, right: 180, bottom: 300}), ['0', 'auto', '']);
});

Deno.test('a dropdown over the lower edge moves up by its overlap', () =>
{
	assertEquals(adjust({left: 10, right: 200, bottom: 650}, '500px'), ['', '', '450px']);
});

Deno.test('without the size of the window the one of the document counts', () =>
{
	using dom      = createDom('<ul id="dropdown"></ul>');
	using _globals = replaceGlobals({window: {innerWidth: 0, innerHeight: 0}});
	Object.defineProperty(dom.document.documentElement, 'clientWidth', {value: 300});
	Object.defineProperty(dom.document.documentElement, 'clientHeight', {value: 400});
	const dropdown = dropdownAt(dom.document, {left: 10, right: 350, bottom: 420}, '100px');

	AdjustMenus.adjustDropdownPosition(dropdown);

	assertEquals([dropdown.style.left, dropdown.style.right, dropdown.style.top], ['auto', '0', '80px']);
});
