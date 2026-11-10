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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {ViewportView}                 from "@js/playlists/compose/multizone/Views/ViewportView.js";

// the fields of templates/playlists/multizone.mustache
const PAGE = `
	<select id="export_unit"><option value="pixel">Pixel</option><option value="percent">Percent</option></select>
	<input type="number" id="screen_width" value=""> x <input type="number" id="screen_height" value="">
	<input type="range" min="5" max="200" value="100" step="5" id="slider">
	<span id="percent">100%</span>`;

Deno.test('the fields of the viewport are read from the page', () =>
{
	using dom  = createDom(PAGE);
	const view = new ViewportView();

	assertSameElement(view.screenWidth, dom.document.getElementById('screen_width'));
	assertSameElement(view.screenHeight, dom.document.getElementById('screen_height'));
	assertSameElement(view.exportUnit, dom.document.getElementById('export_unit'));
	assertSameElement(view.slider, dom.document.getElementById('slider'));
});

Deno.test('the values of the layout are shown with the zoom in percent', () =>
{
	using dom  = createDom(PAGE);
	const view = new ViewportView();

	view.show(1080, 1920, 'percent', 35);

	assertEquals([view.screenWidth.value, view.screenHeight.value, view.exportUnit.value, view.slider.value], ['1080', '1920', 'percent', '35']);
	assertEquals(dom.document.getElementById('percent').textContent, '35 %');
});

Deno.test('the zoom in percent follows the slider', () =>
{
	using dom  = createDom(PAGE);
	const view = new ViewportView();

	view.slider.value = '150';
	view.showZoomPercent();

	assertEquals(dom.document.getElementById('percent').textContent, '150 %');
});
