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

import {assertEquals}            from "@std/assert";
import {createDom}               from "@support/dom.js";
import {replaceGlobals}          from "@support/globals.js";
import {element, fire, recorder} from "@support/ComposerControllerFakes.js";
import {busOfTest}               from "@support/MultizoneEvents.js";
import {ViewportController}      from "@js/templates/canvas-composer/Controller/ViewportController.js";

/**
 * The browser window is 1400 x 900, the panel of the objects 220 wide: the canvas has 1000 x 720.
 */
function createController({transparent = false} = {})
{
	const dom     = createDom();
	Object.defineProperty(dom.document.documentElement, 'clientWidth', {value: 1400});
	Object.defineProperty(dom.document.documentElement, 'clientHeight', {value: 900});
	const browser = element();
	const globals = replaceGlobals({window: browser});
	const bus     = busOfTest();

	const view = recorder(['getResolutionWidthValue', 'getResolutionHeightValue', 'getZoomSliderValue', 'getBackgroundColorValue',
		'isTransparentBackgroundChecked', 'getObjectPanelWidth', 'setResolutionWidth', 'setResolutionHeight', 'setZoomSliderValue',
		'setZoomPercentHtml', 'setTransparentBackground', 'setBackgroundColor'], {
		getResolutionWidthValue: () => view.width, getResolutionHeightValue: () => view.height, getZoomSliderValue: () => view.zoom,
		getBackgroundColorValue: () => view.color, isTransparentBackgroundChecked: () => view.transparent, getObjectPanelWidth: 220,
		setZoomSliderValue: (value) => view.zoom = value
	});
	Object.assign(view, {width: 1920, height: 1080, zoom: 50, color: '#ff0000', transparent: false});
	for (const name of ['resolutionWidth', 'resolutionHeight', 'backgroundColor', 'transparentBackground', 'zoomSlider'])
		view[name] = element();

	const service = recorder(['setCanvasDimensions', 'scaleCanvas', 'setBackgroundColor', 'commitBackgroundColor',
		'setTransparentBackground', 'onHistoryRestored', 'isTransparentBackground', 'getBackgroundColor', 'initializeFromCanvas',
		'calculateZoomByBrowserViewPort'], {
		onHistoryRestored: (callback) => service.restored = callback, isTransparentBackground: () => service.transparent,
		getBackgroundColor: '#00ff00', calculateZoomByBrowserViewPort: 40
	});
	Object.assign(service, {transparent, width: 800, height: 600});
	const global = recorder(['setCanvasDimension']);
	const controller = new ViewportController(view, service, global);
	service.calls.length = 0; // the listener for undo and redo

	return {
		browser, bus, view, service, global, controller,
		viewCalls: (...names) => view.calls.filter(call => names.includes(call[0])),
		[Symbol.dispose]: () => { bus[Symbol.dispose](); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('a new resolution changes the canvas and keeps the zoom', () =>
{
	for (const field of ['resolutionWidth', 'resolutionHeight'])
	{
		using page = createController();
		page.view.width = 1080;
		page.view.height = 1920;

		fire(page.view[field], 'change');

		assertEquals(page.service.calls, [['setCanvasDimensions', 1080, 1920], ['scaleCanvas', 50]], field);
		assertEquals(page.global.calls, [['setCanvasDimension', 1080, 1920]], field);
	}
});

Deno.test('the color is shown while it is chosen and becomes one step of the history when it is taken', () =>
{
	using page = createController();

	fire(page.view.backgroundColor, 'input');
	fire(page.view.backgroundColor, 'change');

	assertEquals(page.service.calls, [['setBackgroundColor', '#ff0000'], ['setBackgroundColor', '#ff0000'], ['commitBackgroundColor']]);
});

Deno.test('a transparent background keeps the color of the field', () =>
{
	using page = createController({transparent: true});
	page.view.transparent = true;

	fire(page.view.transparentBackground, 'change');

	assertEquals(page.service.calls.find(call => call[0] === 'setTransparentBackground'), ['setTransparentBackground', true, '#ff0000']);
	assertEquals(page.viewCalls('setTransparentBackground', 'setBackgroundColor'), [['setTransparentBackground', true]]);
});

Deno.test('without transparency the color of the canvas is shown again, also after undo or redo', () =>
{
	using page = createController();

	fire(page.view.transparentBackground, 'change');
	page.service.restored();

	assertEquals(page.viewCalls('setTransparentBackground', 'setBackgroundColor'), [
		['setTransparentBackground', false], ['setBackgroundColor', '#00ff00'],
		['setTransparentBackground', false], ['setBackgroundColor', '#00ff00'],
	]);
});

Deno.test('the zoom slider scales the canvas and shows the percent', () =>
{
	using page = createController();
	page.view.zoom = 75;

	fire(page.view.zoomSlider, 'input');

	assertEquals(page.viewCalls('setZoomPercentHtml'), [['setZoomPercentHtml', '75 %']]);
	assertEquals(page.service.calls, [['scaleCanvas', 75]]);
});

Deno.test('the canvas fits the window: the browser minus the margins and the panel of the objects', () =>
{
	using page = createController();

	page.controller.zoomToViewPort();

	assertEquals(page.service.calls, [['calculateZoomByBrowserViewPort', 1000, 720], ['scaleCanvas', 40]]);
	assertEquals(page.viewCalls('setZoomSliderValue', 'setZoomPercentHtml'), [['setZoomSliderValue', 40], ['setZoomPercentHtml', '40 %']]);
});

Deno.test('a resized window or panel fits the canvas again until the zoom is chosen by hand', () =>
{
	using page = createController();

	fire(page.browser, 'resize');
	page.bus.send('workspaceResized');
	assertEquals(page.service.calls.filter(call => call[0] === 'calculateZoomByBrowserViewPort').length, 2);

	fire(page.view.zoomSlider, 'input');
	page.service.calls.length = 0;
	fire(page.browser, 'resize');
	page.bus.send('workspaceResized');
	assertEquals(page.service.calls, []);
});

Deno.test('a loaded canvas takes its size and background into the toolbar and fits the window', () =>
{
	using page = createController();

	page.bus.send('canvasUpdated');

	assertEquals(page.global.calls, [['setCanvasDimension', 1920, 1080]]);
	assertEquals(page.service.calls.map(call => call[0]),
		['initializeFromCanvas', 'isTransparentBackground', 'getBackgroundColor', 'calculateZoomByBrowserViewPort', 'scaleCanvas']);
	assertEquals(page.viewCalls('setResolutionWidth', 'setResolutionHeight'), [['setResolutionWidth', 800], ['setResolutionHeight', 600]]);
});
