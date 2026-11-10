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

import {assertEquals}                       from "@std/assert";
import {callNames, composerWrapper, FakeFabricObject} from "@support/ComposerServicesFakes.js";
import {SnapService}                        from "@js/templates/canvas-composer/Services/SnapService.js";
import {TemplateGuidelines}                 from "@js/templates/canvas-composer/Utils/TemplateGuidelines.js";

/**
 * The guidelines of fabric (AligningGuidelines) are a fake of the test: dispose() is recorded on its prototype.
 */
function createService()
{
	const base     = Object.getPrototypeOf(TemplateGuidelines).prototype;
	const original = Object.getOwnPropertyDescriptor(base, 'dispose');
	const disposed = [];
	base.dispose   = function () { disposed.push(this); };
	const canvas   = {id: 'canvas'};
	const wrapper  = composerWrapper({getWidth: () => 1920, getHeight: () => 1080, getZoom: () => 0.5}, {fabricCanvas: canvas});

	return {
		canvas, wrapper, disposed, service: new SnapService(wrapper),
		[Symbol.dispose]: () =>
		{
			if (original === undefined)
				delete base.dispose;
			else
				Object.defineProperty(base, 'dispose', original);
		}
	};
}

Deno.test('the grid is off at the start, the guidelines are on', () =>
{
	using page = createService();

	assertEquals(page.service.isGridEnabled, false);
	assertEquals(page.disposed, []);
});

Deno.test('the grid switches the guidelines off: both would pull the object', () =>
{
	using page = createService();

	page.service.enable(20);

	assertEquals(page.service.isGridEnabled, true);
	assertEquals(page.disposed.length, 1);
	assertEquals(page.disposed[0] instanceof TemplateGuidelines, true);
	assertEquals(page.disposed[0].canvas === page.canvas, true);
});

Deno.test('the grid again does not dispose the guidelines a second time', () =>
{
	using page = createService();

	page.service.enable();
	page.service.enable();

	assertEquals(page.disposed.length, 1);
});

Deno.test('without the grid the guidelines come back, new ones', () =>
{
	using page = createService();
	page.service.enable();

	page.service.disable();
	page.service.disable();
	page.service.enable();

	assertEquals(page.service.isGridEnabled, true);
	assertEquals(page.disposed.length, 2);
	assertEquals(page.disposed[0] === page.disposed[1], false);
});

Deno.test('an object snaps to the grid only while it is on', () =>
{
	using page = createService();
	const object = new FakeFabricObject({left: 14, top: 26});

	page.service.snapToGrid(object);
	assertEquals([object.left, object.top], [14, 26]);

	page.service.enable(10);
	page.service.snapToGrid(object);
	assertEquals([object.left, object.top], [10, 30]);

	page.service.enable(25);
	object.set({left: 38, top: 37});
	page.service.snapToGrid(object);
	assertEquals([object.left, object.top], [50, 25]);
});

Deno.test('the grid is drawn on the canvas in its size and zoom', () =>
{
	using page = createService();

	assertEquals(page.service.getCanvasvaluesForGrid(), {width: 1920, height: 1080, zoom: 0.5});
	page.service.renderAll();
	assertEquals(callNames(page.wrapper), ['getWidth', 'getHeight', 'getZoom', 'renderAll']);
});
