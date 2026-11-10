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
import {ViewportView}                 from "@js/templates/canvas-composer/Views/ViewportView.js";

// the size, zoom and background of templates/templates/canvas.mustache
const PAGE = `
	<input type="color" id="backgroundColor" class="color_button" value="#ffffff">
	<input type="checkbox" id="transparentBackground">
	<input type="number" id="imageWidth" value="1920" step="1" min="0">
	<input type="number" id="imageHeight" value="1080" step="1" min="0">
	<input type="range" min="20" max="500" value="100" step="5" id="zoomSlider">
	<span id="zoomPercent"></span>
	<div id="composerWorkspace"><aside id="objectPanel"></aside><div id="canvasWrap"><canvas id="canvas"></canvas></div></div>`;

function createView(body = PAGE)
{
	const dom = createDom(body);
	return {document: dom.document, view: new ViewportView(), byId: (id) => dom.document.getElementById(id),
		[Symbol.dispose]: () => dom[Symbol.dispose]()};
}

Deno.test('the fields are read from the page', () =>
{
	using page = createView();
	const getters = {
		zoomPercent: 'zoomPercent', zoomSlider: 'zoomSlider', resolutionWidth: 'imageWidth', resolutionHeight: 'imageHeight',
		backgroundColor: 'backgroundColor', transparentBackground: 'transparentBackground'
	};

	for (const [getter, id] of Object.entries(getters))
		assertSameElement(page.view[getter], page.byId(id), getter);
});

Deno.test('the zoom is shown as percent and on the slider', () =>
{
	using page = createView();

	page.view.setZoomPercentHtml('150 %');
	page.view.setZoomSliderValue(150);

	assertEquals(page.byId('zoomPercent').textContent, '150 %');
	assertEquals(String(page.view.getZoomSliderValue()), '150');
});

Deno.test('the resolution is set and read', () =>
{
	using page = createView();
	assertEquals([page.view.getResolutionWidthValue(), page.view.getResolutionHeightValue()], ['1920', '1080']);

	page.view.setResolutionWidth(1280);
	page.view.setResolutionHeight(720);

	assertEquals([String(page.view.getResolutionWidthValue()), String(page.view.getResolutionHeightValue())], ['1280', '720']);
});

Deno.test('the background color is set and read', () =>
{
	using page = createView();

	page.view.setBackgroundColor('#123456');

	assertEquals(page.view.getBackgroundColorValue(), '#123456');
});

Deno.test('a transparent template has no color to choose and a checkerboard behind the objects', () =>
{
	using page = createView();

	page.view.setTransparentBackground(true);
	assertEquals(page.view.isTransparentBackgroundChecked(), true);
	assertEquals(page.byId('backgroundColor').disabled, true);
	assertEquals(page.byId('canvas').classList.contains('transparent-background'), true);

	page.view.setTransparentBackground(false);
	assertEquals(page.view.isTransparentBackgroundChecked(), false);
	assertEquals(page.byId('backgroundColor').disabled, false);
	assertEquals(page.byId('canvas').classList.contains('transparent-background'), false);
});

Deno.test('the width of the panel of the objects is measured, without the panel it is 0', () =>
{
	{
		using page = createView();
		Object.defineProperty(page.byId('objectPanel'), 'offsetWidth', {value: 240});
		assertEquals(page.view.getObjectPanelWidth(), 240);
	}
	{
		using page = createView(PAGE.replace('<aside id="objectPanel"></aside>', ''));
		assertEquals(page.view.getObjectPanelWidth(), 0);
	}
});
