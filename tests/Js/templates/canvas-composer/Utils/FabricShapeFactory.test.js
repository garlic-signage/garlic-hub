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

import {assertEquals}       from "@std/assert";
import {replaceGlobals}     from "@support/globals.js";
import {FabricShapeFactory} from "@js/templates/canvas-composer/Utils/FabricShapeFactory.js";

/**
 * The shapes of fabric.js which the factory creates: they keep what they were created with.
 */
class FakeShape
{
	constructor(options)
	{
		this.created = options;
	}
}

class FakePolygon
{
	constructor(points, options)
	{
		this.points  = points;
		this.created = options;
	}
}

class FakeTextbox
{
	constructor(text, options)
	{
		this.text    = text;
		this.created = options;
	}
}

class FakeImage
{
	static loaded = [];

	static async fromURL(url, options)
	{
		const image = new FakeImage();
		image.url     = url;
		image.options = options;
		FakeImage.loaded.push(image);
		return image;
	}

	set(values)
	{
		Object.assign(this, values);
	}
}

function fakeFabric()
{
	return replaceGlobals({fabric: {
		FabricImage: FakeImage, Textbox: FakeTextbox, Circle: FakeShape, Triangle: FakeShape, Rect: FakeShape, Polygon: FakePolygon
	}});
}

Deno.test('an image is loaded for the canvas and keeps its medium and file name', async () =>
{
	using _fabric = fakeFabric();

	const image = await new FabricShapeFactory().createImage('abc', 'https://hub/media/abc.jpg?signature=1', 'abc.jpg', 2);

	assertEquals([image.url, image.options], ['https://hub/media/abc.jpg?signature=1', {crossOrigin: 'anonymous'}]);
	assertEquals([image.mediaId, image.fileName], ['abc', 'abc.jpg']);
});

Deno.test('every further image is placed a bit lower right', async () =>
{
	using _fabric = fakeFabric();
	const factory = new FabricShapeFactory();

	const first  = await factory.createImage('a', 'u', 'a.jpg', 1);
	const third  = await factory.createImage('c', 'u', 'c.jpg', 3);

	assertEquals([first.left, first.top, third.left, third.top], [50, 50, 150, 150]);
});

Deno.test('a text is a textbox of Arial with a sample text', () =>
{
	using _fabric = fakeFabric();

	const text = new FabricShapeFactory().createText();

	assertEquals(text.text, 'Lorem ipsum');
	assertEquals(text.created, {left: 50, top: 50, fill: '#000000', width: 400, fontFamily: 'Arial', fontSize: 64});
});

Deno.test('the shapes are black at the same place with their own size', () =>
{
	using _fabric = fakeFabric();
	const factory = new FabricShapeFactory();
	const place   = {left: 50, top: 50, fill: '#000000'};

	assertEquals(factory.createCircle().created, {...place, radius: 200});
	assertEquals(factory.createTriangle().created, {...place, width: 400, height: 400});
	assertEquals(factory.createRectangle().created, {...place, width: 400, height: 300});
});

Deno.test('a polygon takes its points', () =>
{
	using _fabric = fakeFabric();
	const points  = [{x: 0, y: 0}, {x: 10, y: 0}, {x: 5, y: 8}];

	const polygon = new FabricShapeFactory().createPolygon(points);

	assertEquals(polygon.points, points);
	assertEquals(polygon.created, {left: 50, top: 50, fill: '#000000'});
});
