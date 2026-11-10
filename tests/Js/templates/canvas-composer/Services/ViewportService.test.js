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

import {assertEquals}                    from "@std/assert";
import {composerFabric, composerWrapper} from "@support/ComposerServicesFakes.js";
import {replaceGlobals}                  from "@support/globals.js";
import {ViewportService}                 from "@js/templates/canvas-composer/Services/ViewportService.js";

/**
 * A template of 1920 x 1080 with a red background.
 */
function createService({background = 'rgb(255, 0, 0)', transparent = false} = {})
{
	const globals = replaceGlobals({fabric: composerFabric()});
	const wrapper = composerWrapper({
		getWidth: () => 1920, getHeight: () => 1080,
		getBackgroundColor: () => background, isTransparentBackground: () => transparent
	});
	const service = new ViewportService(wrapper);
	service.initializeFromCanvas();
	wrapper.calls.length = 0;

	return {wrapper, service, [Symbol.dispose]: () => globals[Symbol.dispose]()};
}

Deno.test('the size of the template is taken from the canvas', () =>
{
	using page = createService();

	assertEquals([page.service.width, page.service.height], [1920, 1080]);
});

Deno.test('a new size is set on the canvas', () =>
{
	using page = createService();

	page.service.setCanvasDimensions(1080, 1920);

	assertEquals([page.service.width, page.service.height], [1080, 1920]);
	assertEquals(page.wrapper.calls, [['setWidth', 1080], ['setHeight', 1920]]);
});

Deno.test('the zoom scales the canvas element, the template keeps its size', () =>
{
	using page = createService();

	page.service.scaleCanvas(25);

	assertEquals(page.wrapper.calls, [['setZoom', 0.25], ['setWidth', 480], ['setHeight', 270]]);
	assertEquals([page.service.width, page.service.height], [1920, 1080]);
});

Deno.test('the size of the canvas element is in whole pixels', () =>
{
	using page = createService();

	page.service.scaleCanvas(33);

	assertEquals(page.wrapper.calls.slice(1), [['setWidth', 633], ['setHeight', 356]]);
});

Deno.test('the zoom fits the template into the window by the tighter side', () =>
{
	using page = createService();

	// 1000 / 1920 = 52 %, 800 / 1080 = 74 %
	assertEquals(page.service.calculateZoomByBrowserViewPort(1000, 800), 52);
	// 2000 / 1920 = 104 %, 500 / 1080 = 46 %
	assertEquals(page.service.calculateZoomByBrowserViewPort(2000, 500), 46);
});

Deno.test('an image is scaled in percent to fit into the template', () =>
{
	using page = createService();

	// 1920 / 3840 = 50 %, 1080 / 1080 = 100 %
	assertEquals(page.service.calculateImageScaleByCanvasInPerCent(3840, 1080), 50);
	// a small image is enlarged up to the tighter side
	assertEquals(page.service.calculateImageScaleByCanvasInPerCent(192, 216), 500);
});

Deno.test('the background color is a hex color for the color input', () =>
{
	{
		using page = createService({background: 'rgb(255, 0, 0)'});
		assertEquals(page.service.getBackgroundColor(), '#ff0000');
	}
	{
		using page = createService({background: '#00AAFF'});
		assertEquals(page.service.getBackgroundColor(), '#00aaff');
	}
});

Deno.test('the background color is shown while it is chosen and saved when it is taken', () =>
{
	using page = createService();

	page.service.setBackgroundColor('#123456');
	page.service.commitBackgroundColor();

	assertEquals(page.wrapper.calls, [['setBackgroundColor', '#123456'], ['commitBackgroundColor']]);
});

Deno.test('a transparent background is switched on and off with the color of the input', () =>
{
	using page = createService({transparent: true});

	assertEquals(page.service.isTransparentBackground(), true);
	page.service.setTransparentBackground(true, '#123456');
	page.service.setTransparentBackground(false, '#123456');

	assertEquals(page.wrapper.calls.slice(1), [
		['setBackgroundColor', 'transparent'], ['commitBackgroundColor'],
		['setBackgroundColor', '#123456'], ['commitBackgroundColor']
	]);
});

Deno.test('undo and redo tell that the history was restored', () =>
{
	using page = createService();
	const handler = () => {};

	page.service.onHistoryRestored(handler);

	assertEquals(page.wrapper.calls.map(call => [call[0], call[1], call[2] === handler]),
		[['on', 'history:undo', true], ['on', 'history:redo', true]]);
});
