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

import {assertEquals, assertRejects}                        from "@std/assert";
import {composerFabric, composerWrapper, callNames, FakeFabricObject} from "@support/ComposerServicesFakes.js";
import {replaceGlobals}                                     from "@support/globals.js";
import {InsertService}                                      from "@js/templates/canvas-composer/Services/InsertService.js";

/**
 * Builds the shapes like the FabricShapeFactory, the image is 800 x 600.
 */
class FakeShapeFactory
{
	calls = [];

	async createImage(mediaId, url, fileName, count)
	{
		this.calls.push(['createImage', mediaId, url, fileName, count]);
		const image = new FakeFabricObject({kind: 'image', width: 800, height: 600});
		image.scale = (factor) => { image.scaled = factor; };
		return image;
	}

	createText()      { return new FakeFabricObject({kind: 'text'}); }
	createCircle()    { return new FakeFabricObject({kind: 'circle'}); }
	createTriangle()  { return new FakeFabricObject({kind: 'triangle'}); }
	createRectangle() { return new FakeFabricObject({kind: 'rect'}); }

	createPolygon(points)
	{
		return new FakeFabricObject({kind: 'polygon', points});
	}
}

/**
 * active builds the selected object with the fabric of the test.
 */
function createService({active = () => null, loadedImage = null} = {})
{
	const fabric   = composerFabric({loadedImage});
	const globals  = replaceGlobals({fabric});
	const selected = active(fabric);
	const wrapper  = composerWrapper({getActiveObject: () => selected});
	const factory  = new FakeShapeFactory();
	const viewport = {
		calls: [],
		calculateImageScaleByCanvasInPerCent(w, h) { this.calls.push([w, h]); return 120; }
	};

	return {
		fabric, wrapper, factory, viewport, selected, service: new InsertService(wrapper, factory, viewport),
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

/**
 * The placeholder of a template: 200 x 100 at the scale 2.
 */
function oldImage(fabric, options = {})
{
	return new fabric.FabricImage({width: 200, height: 100, scaleX: 2, scaleY: 2, mediaId: 'old', fileName: 'old.jpg', placeholder: true, ...options});
}

function addedKinds(page)
{
	return page.wrapper.calls.filter(call => call[0] === 'add').map(call => call[1].kind);
}

Deno.test('an image is scaled to two thirds of the size that fits the template', async () =>
{
	using page = createService();

	await page.service.insertImage('abc', '/media/abc.jpg', 'abc.jpg', 3);

	assertEquals(page.factory.calls, [['createImage', 'abc', '/media/abc.jpg', 'abc.jpg', 3]]);
	assertEquals(page.viewport.calls, [[800, 600]]);
	const image = page.wrapper.calls.find(call => call[0] === 'add')[1];
	assertEquals(image.scaled, 0.8);
	assertEquals(callNames(page.wrapper), ['add', 'historySaveAction', 'renderAll']);
});

Deno.test('text and shapes are added as one step of the history each', () =>
{
	using page = createService();

	page.service.insertText();
	page.service.insertCircle();
	page.service.insertTriangle();
	page.service.insertRectangle();

	assertEquals(addedKinds(page), ['text', 'circle', 'triangle', 'rect']);
	assertEquals(callNames(page.wrapper).filter(name => name === 'historySaveAction').length, 4);
});

Deno.test('a regular polygon has its corners on a circle of 200 around 200 / 200', () =>
{
	using page = createService();

	page.service.insertRegularPolygon(4);

	const polygon = page.wrapper.calls.find(call => call[0] === 'add')[1];
	assertEquals(polygon.points.map(point => [Math.round(point.x), Math.round(point.y)]), [[400, 200], [200, 400], [0, 200], [200, 0]]);
});

Deno.test('the cover cut of a wide picture for a square is taken from the middle', () =>
{
	const crop = InsertService.coverCrop({naturalWidth: 1000, naturalHeight: 500}, 400, 400);

	assertEquals(crop, {width: 500, height: 500, cropX: 250, cropY: 0, scaleX: 0.8, scaleY: 0.8});
});

Deno.test('without a natural size the size of the element counts', () =>
{
	const crop = InsertService.coverCrop({width: 400, height: 800}, 400, 400);

	assertEquals(crop, {width: 400, height: 400, cropX: 0, cropY: 200, scaleX: 1, scaleY: 1});
});

Deno.test('a replaced image fills the area of the old one and is no placeholder any more', async () =>
{
	const element = {naturalWidth: 800, naturalHeight: 800};
	using page    = createService({loadedImage: element, active: oldImage});
	const old     = page.selected;

	await page.service.replaceImage('new', '/signed/new.jpg?token=1', 'new.jpg');

	assertEquals(page.fabric.loaded, [['/signed/new.jpg?token=1', {crossOrigin: 'anonymous'}]]);
	assertEquals(old.getElement() === element, true);
	// the area of 400 x 200 from a picture of 800 x 800: the middle half at the scale 0.5
	assertEquals([old.width, old.height, old.cropX, old.cropY, old.scaleX, old.scaleY], [800, 400, 0, 200, 0.5, 0.5]);
	assertEquals([old.mediaId, old.fileName, old.placeholder], ['new', 'new.jpg', false]);
	assertEquals(old.calls, [['setCoords']]);
	assertEquals(callNames(page.wrapper), ['getActiveObject', 'fireObjectModified', 'renderAll']);
	assertEquals(page.wrapper.calls[1][1] === old, true);
});

Deno.test('a round clip path keeps its size on the template', async () =>
{
	using page = createService({loadedImage: {naturalWidth: 800, naturalHeight: 800},
		active: (fabric) => oldImage(fabric, {clipPath: new FakeFabricObject({scaleX: 1, scaleY: 1})})});

	await page.service.replaceImage('new', '/new.jpg', 'new.jpg');

	// the old picture at the scale 2, the new one at 0.5: the clip path four times larger in it
	assertEquals([page.selected.clipPath.scaleX, page.selected.clipPath.scaleY], [4, 4]);
});

Deno.test('a picture which can not be loaded leaves the image as it is', async () =>
{
	for (const loadedImage of [new Error('404'), null, {naturalWidth: 0, width: 0}])
	{
		using page = createService({loadedImage, active: oldImage});

		await assertRejects(() => page.service.replaceImage('new', '/new.jpg', 'new.jpg'), Error, 'The image could not be loaded: new.jpg');

		assertEquals([page.selected.mediaId, page.selected.placeholder, page.selected.width], ['old', true, 200], String(loadedImage));
		assertEquals(callNames(page.wrapper), ['getActiveObject']);
	}
});
