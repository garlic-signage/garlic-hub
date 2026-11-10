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

import {assertEquals}                                       from "@std/assert";
import {composerFabric, composerWrapper, callNames, FakeFabricObject} from "@support/ComposerServicesFakes.js";
import {replaceGlobals}                                     from "@support/globals.js";
import {CropService}                                        from "@js/templates/canvas-composer/Services/CropService.js";

/**
 * A service with the fakes of fabric and of the extensions; image() builds an image of 400 x 300 in a picture of
 * 1000 x 800, scaled to the half, locked like a placeholder of a template.
 */
function createService()
{
	const fabric  = composerFabric();
	const globals = replaceGlobals({fabric, fabricExtensions: {...globalThis.fabricExtensions, createImageCroppingControls: () => 'crop-controls'}});
	const wrapper = composerWrapper();

	return {
		fabric, wrapper, service: new CropService(wrapper),
		image: (options = {}) => new fabric.FabricImage({
			left: 10, top: 20, width: 400, height: 300, cropX: 50, cropY: 40, scaleX: 0.5, scaleY: 0.5, angle: 0,
			controls: 'normal-controls', padding: 4, lockMovementX: true, lockMovementY: true, borderColor: 'blue',
			borderScaleFactor: 2, element: {width: 1000, height: 800}, getTotalAngle() { return this.angle; }, ...options
		}),
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

Deno.test('only an image can be cropped', () =>
{
	using page = createService();

	assertEquals(CropService.isCroppable(page.image()), true);
	assertEquals(CropService.isCroppable(new FakeFabricObject()), false);

	page.service.enter(new FakeFabricObject());
	assertEquals([page.service.isCropping(), page.wrapper.calls], [false, []]);
});

Deno.test('the crop mode gives the image its controls, frees it and pauses the history', () =>
{
	using page  = createService();
	const image = page.image();

	page.service.enter(image);

	assertEquals(page.service.isCropping(), true);
	assertEquals(page.service.image === image, true);
	assertEquals([image.isCropping, image.controls, image.padding, image.lockMovementX, image.lockMovementY],
		[true, 'crop-controls', 0, false, false]);
	assertEquals(Object.keys(image.handlers), ['moving', 'before:render']);
	assertEquals(callNames(page.wrapper), ['pauseHistory', 'setActiveObject', 'renderAll']);
	assertEquals(page.wrapper.calls[1][1] === image, true);
});

Deno.test('the image in the crop mode again changes nothing', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);

	page.service.enter(image);

	assertEquals(callNames(page.wrapper), ['pauseHistory', 'setActiveObject', 'renderAll']);
});

Deno.test('another image keeps the cut of the first one', () =>
{
	using page  = createService();
	const first = page.image();
	page.service.enter(first);

	page.service.enter(page.image());

	assertEquals(first.isCropping, false);
	assertEquals(callNames(page.wrapper).slice(3, 5), ['resumeHistory', 'renderAll']);
	assertEquals(page.service.image === first, false);
});

Deno.test('apply without a change ends the crop mode without a step of the history', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);

	page.service.apply();

	assertEquals(page.service.isCropping(), false);
	assertEquals([image.isCropping, image.controls, image.padding, image.lockMovementX, image.lockMovementY],
		[false, 'normal-controls', 4, true, true]);
	assertEquals(image.handlers, {});
	assertEquals(callNames(page.wrapper).slice(3), ['resumeHistory', 'renderAll']);
});

Deno.test('a changed cut is one step of the history', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);
	image.cropX = 80;

	page.service.apply();

	assertEquals(callNames(page.wrapper).slice(3), ['resumeHistory', 'fireObjectModified', 'renderAll']);
	assertEquals(image.cropX, 80);
});

Deno.test('cancel brings the image back as it was', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);
	image.set({left: 99, top: 99, width: 100, height: 100, cropX: 0, cropY: 0, scaleX: 2, scaleY: 2});

	page.service.cancel();

	assertEquals([image.left, image.top, image.width, image.height, image.cropX, image.cropY, image.scaleX, image.scaleY],
		[10, 20, 400, 300, 50, 40, 0.5, 0.5]);
	assertEquals(callNames(page.wrapper).slice(3), ['resumeHistory', 'renderAll']);
	assertEquals(page.service.isCropping(), false);
});

Deno.test('a round clip path keeps its size on the template while the picture is zoomed', () =>
{
	using page  = createService();
	const image = page.image({clipPath: new FakeFabricObject({scaleX: 1, scaleY: 1})});
	page.service.enter(image);
	image.set({scaleX: 1, scaleY: 1});

	page.service.apply();

	// the picture is twice as large: the clip path half as large in it
	assertEquals([image.clipPath.scaleX, image.clipPath.scaleY], [0.5, 0.5]);
});

Deno.test('cancel brings the clip path back, too', () =>
{
	using page  = createService();
	const image = page.image({clipPath: new FakeFabricObject({scaleX: 1, scaleY: 1})});
	page.service.enter(image);
	image.clipPath.set({scaleX: 3, scaleY: 3});

	page.service.cancel();

	assertEquals([image.clipPath.scaleX, image.clipPath.scaleY], [1, 1]);
});

Deno.test('dragging moves the picture in its frame, the frame stays', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);
	const original = {left: 10, top: 20, cropX: 50, cropY: 40};

	// 10 pixels to the right on the template are 20 pixels of the picture at the scale 0.5
	image.set({left: 20, top: 25});
	image.handlers.moving({transform: {target: image, original}});

	assertEquals([image.left, image.top, image.cropX, image.cropY], [10, 20, 30, 30]);
});

Deno.test('the picture is not dragged out of its frame', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);
	const original = {left: 10, top: 20, cropX: 50, cropY: 40};

	image.set({left: 1000, top: 1000});
	image.handlers.moving({transform: {target: image, original}});
	assertEquals([image.cropX, image.cropY], [0, 0]);

	image.set({left: -1000, top: -1000});
	image.handlers.moving({transform: {target: image, original}});
	// the picture of 1000 x 800 minus the frame of 400 x 300
	assertEquals([image.cropX, image.cropY], [600, 500]);
});

Deno.test('a flipped picture is dragged the other way', () =>
{
	using page  = createService();
	const image = page.image({flipX: true});
	page.service.enter(image);

	image.set({left: 20, top: 20});
	image.handlers.moving({transform: {target: image, original: {left: 10, top: 20, cropX: 50, cropY: 40}}});

	assertEquals(image.cropX, 70);
});

Deno.test('the whole picture is drawn half transparent around the frame', () =>
{
	using page  = createService();
	const image = page.image();
	page.service.enter(image);
	const drawn = [];
	const ctx   = {
		globalAlpha: 0.8,
		drawImage: (element, x, y) => drawn.push(['drawImage', element.width, x, y, ctx.globalAlpha]),
		strokeRect: (x, y, w, h) => drawn.push(['strokeRect', x, y, w, h, ctx.strokeStyle, ctx.lineWidth])
	};

	image.handlers['before:render']({ctx});

	// the frame of 400 x 300 is centered, the picture starts at the cut before it
	assertEquals(drawn, [['drawImage', 1000, -250, -190, 0.4], ['strokeRect', -250, -190, 1000, 800, 'blue', 4]]);
	assertEquals(ctx.globalAlpha, 0.8);
});

Deno.test('apply and cancel without the crop mode do nothing', () =>
{
	using page = createService();

	page.service.apply();
	page.service.cancel();

	assertEquals(page.wrapper.calls, []);
});
