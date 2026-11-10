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

import {assertEquals}               from "@std/assert";
import {busOfTest}                  from "@support/MultizoneEvents.js";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {LayoutSettings}             from "@js/playlists/compose/multizone/Utils/LayoutSettings.js";
import {ViewportController}         from "@js/playlists/compose/multizone/Controller/ViewportController.js";

/**
 * A field of the view: an EventTarget with a value.
 */
function field(value = '')
{
	return Object.assign(new EventTarget(), {value});
}

class FakeViewportView
{
	screenWidth  = field('1920');
	screenHeight = field('1080');
	exportUnit   = field('pixel');
	slider       = field('70');
	calls        = [];

	show(width, height, exportUnit, zoom) { this.calls.push(['show', width, height, exportUnit, zoom]); }
	showZoomPercent()                     { this.calls.push(['showZoomPercent']); }
}

class FakeViewportService
{
	width  = 1920;
	height = 1080;
	calls  = [];

	setDimensions(width, height) { this.calls.push(['setDimensions', width, height]); }
	scaleCanvas(zoom)            { this.calls.push(['scaleCanvas', zoom]); }
}

function createController()
{
	const bus        = busOfTest();
	const view       = new FakeViewportView();
	const service    = new FakeViewportService();
	const settings   = new LayoutSettings();
	const canvas     = new MultizoneFakeFabricWrapper();
	const controller = new ViewportController(view, service, settings, canvas);

	return {bus, view, service, settings, canvas, controller, [Symbol.dispose]: () => bus[Symbol.dispose]()};
}

Deno.test('after the load the values of the layout are shown', () =>
{
	using page = createController();
	page.settings.exportUnit = 'percent';
	page.settings.zoom       = 35;

	page.controller.show();

	assertEquals(page.view.calls, [['show', 1920, 1080, 'percent', 35]]);
});

Deno.test('a new resolution resizes the canvas with the zoom of the slider', () =>
{
	for (const fieldName of ['screenWidth', 'screenHeight'])
	{
		using page = createController();
		page.view.screenWidth.value  = '1080';
		page.view.screenHeight.value = '1920';

		page.view[fieldName].dispatchEvent(new Event('blur'));

		assertEquals(page.service.calls, [['setDimensions', '1080', '1920'], ['scaleCanvas', '70']], fieldName);
	}
});

Deno.test('the chosen unit is the unit of the layout', () =>
{
	using page = createController();

	page.view.exportUnit.value = 'percent';
	page.view.exportUnit.dispatchEvent(new Event('change'));

	assertEquals(page.settings.exportUnit, 'percent');
});

Deno.test('the slider zooms the canvas and is the zoom of the layout', () =>
{
	using page = createController();

	page.view.slider.value = '120';
	page.view.slider.dispatchEvent(new Event('input'));

	assertEquals(page.view.calls, [['showZoomPercent']]);
	assertEquals(page.settings.zoom, '120');
	assertEquals(page.service.calls, [['scaleCanvas', '120']]);
});

Deno.test('a moved zone snaps to a grid of 10 pixel', () =>
{
	using page = createController();
	const zone = {left: 104, top: 96, set(values) { Object.assign(this, values); }};

	page.bus.send('snapToGrid', {target: zone});

	assertEquals([zone.left, zone.top], [100, 100]);
	assertEquals(page.canvas.renders, 1);
	assertEquals(ViewportController.GRID, 10);
});
