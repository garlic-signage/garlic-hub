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

import {assertEquals, assertThrows} from "@std/assert";
import {busOfTest}                  from "@support/MultizoneEvents.js";
import {replaceGlobals}             from "@support/globals.js";
import {TemplateFabricWrapper}      from "@js/templates/canvas-composer/Utils/TemplateFabricWrapper.js";

/**
 * The canvas of fabric.js: the content is a list of objects, toDataURL() notes the background color it painted.
 */
class FakeCanvas
{
	objects         = [];
	handlers        = {};
	backgroundColor = '';
	dimensions      = [];
	renders         = 0;
	images          = [];
	loaded          = [];
	asked           = [];

	on(name, handler)
	{
		(this.handlers[name] ??= []).push(handler);
	}

	fire(name, options = {})
	{
		for (const handler of this.handlers[name] ?? [])
			handler(options);
	}

	setDimensions(dimensions)
	{
		this.dimensions.push(dimensions);
	}

	toObject(properties)
	{
		this.asked.push(properties);
		return {objects: [...this.objects]};
	}

	async loadFromJSON(content)
	{
		this.loaded.push(content);
		this.objects = (typeof content === 'string' ? JSON.parse(content) : content).objects;
	}

	renderAll()
	{
		this.renders++;
	}

	toDataURL(options)
	{
		this.images.push({...options, backgroundColor: this.backgroundColor});
		return 'data:image/' + options.format + ';base64,AAAA';
	}

	change(object)
	{
		this.objects.push(object);
		this.fire('object:modified');
	}
}

/**
 * fabric.cache of fabric.js: load() clears the widths of the characters.
 */
function fakeFabric()
{
	const cleared = [];
	const globals = replaceGlobals({fabric: {cache: {clearFontCache: () => cleared.push(true)}}});
	return {cleared, [Symbol.dispose]: () => globals[Symbol.dispose]()};
}

function createWrapper(...recordedEvents)
{
	const bus     = busOfTest(...recordedEvents);
	const canvas  = new FakeCanvas();
	const wrapper = new TemplateFabricWrapper(canvas);
	return {bus, canvas, wrapper, events: bus.events, [Symbol.dispose]: () => bus[Symbol.dispose]()};
}

Deno.test('a new width or height is a change without the event of a change', () =>
{
	using page = createWrapper('setChanged');

	page.wrapper.setWidth(1280);
	page.wrapper.setHeight(720);

	assertEquals(page.canvas.dimensions, [{width: 1280}, {height: 720}]);
	assertEquals(page.wrapper.hasChanged, true);
	assertEquals(page.events, []);
});

Deno.test('the content of a template has the properties of the composer', () =>
{
	using page = createWrapper();
	page.canvas.objects = ['image'];

	assertEquals(page.wrapper.toTemplateJSON(), {objects: ['image']});
	assertEquals(page.canvas.asked.at(-1), TemplateFabricWrapper.PROPERTIES);
	assertEquals(TemplateFabricWrapper.PROPERTIES.includes('mediaId'), true);
	assertEquals(TemplateFabricWrapper.PROPERTIES.includes('fileName'), true);
});

Deno.test('the history keeps besides them whether an object can be selected', () =>
{
	using page = createWrapper();

	assertEquals(page.canvas.asked[0], [...TemplateFabricWrapper.PROPERTIES, 'evented', 'selectable']);
});

Deno.test('undo and redo tell the composer first, e.g. to end the crop mode', async () =>
{
	using page = createWrapper('beforeHistory');
	page.canvas.change('a');
	page.canvas.change('b');

	await page.wrapper.undo();
	assertEquals(page.canvas.objects, ['a']);
	await page.wrapper.redo();
	assertEquals(page.canvas.objects, ['a', 'b']);

	assertEquals(page.events.map(event => event[0]), ['beforeHistory', 'beforeHistory']);
});

Deno.test('a step can be saved by hand and the history can be paused', async () =>
{
	using page = createWrapper();
	page.canvas.objects = ['a'];
	page.wrapper.historySaveAction();
	page.canvas.objects = ['a', 'b'];
	page.wrapper.historySaveAction();

	page.wrapper.pauseHistory();
	page.canvas.change('c');
	page.wrapper.resumeHistory();

	await page.wrapper.undo();
	assertEquals(page.canvas.objects, ['a']);
});

Deno.test('a loaded template starts a new history and measures its fonts again', async () =>
{
	using page    = createWrapper();
	using fabric  = fakeFabric();
	page.canvas.change('a');
	page.canvas.change('b');

	await page.wrapper.load('{"objects":["x"]}');
	await page.wrapper.undo();

	assertEquals(page.canvas.loaded, ['{"objects":["x"]}']);
	assertEquals(fabric.cleared, [true]);
	assertEquals(page.canvas.objects, ['x']);
});

Deno.test('the background is white without a color and for a transparent template', () =>
{
	using page = createWrapper();

	for (const [color, shown, transparent] of [
		['', '#ffffff', false], [undefined, '#ffffff', false], [null, '#ffffff', false],
		['transparent', '#ffffff', true], ['#123456', '#123456', false]
	])
	{
		page.canvas.backgroundColor = color;
		assertEquals([page.wrapper.getBackgroundColor(), page.wrapper.isTransparentBackground()], [shown, transparent], String(color));
	}
});

Deno.test('a color is shown while it is chosen and becomes a step when it is taken', () =>
{
	using page = createWrapper('setChanged');

	page.wrapper.setBackgroundColor('#ff0000');
	assertEquals([page.canvas.backgroundColor, page.canvas.renders], ['#ff0000', 1]);
	assertEquals(page.events, []);

	page.wrapper.commitBackgroundColor();
	assertEquals(page.wrapper.hasChanged, true);
	assertEquals(page.events.map(event => event[0]), ['setChanged']);
});

Deno.test('the image has the color of the template', () =>
{
	using page = createWrapper();
	page.canvas.backgroundColor = '#123456';

	const image = page.wrapper.toBase64Image('jpeg', 0.9, '#ffffff');

	assertEquals(image, 'data:image/jpeg;base64,AAAA');
	assertEquals(page.canvas.images, [{format: 'jpeg', quality: 0.9, backgroundColor: '#123456'}]);
});

Deno.test('a template without a color gets the given one in its image and keeps none', () =>
{
	using page = createWrapper();

	page.wrapper.toBase64Image('png', 1, '#000000');

	assertEquals(page.canvas.images[0].backgroundColor, '#000000');
	assertEquals(page.canvas.backgroundColor, '');
});

Deno.test('a transparent template stays transparent in png and webp, a jpeg gets the given color', () =>
{
	using page = createWrapper();
	page.canvas.backgroundColor = 'transparent';

	for (const format of ['png', 'webp', 'jpeg', 'bmp'])
		page.wrapper.toBase64Image(format, 1, '#ffffff');

	assertEquals(page.canvas.images.map(image => [image.format, image.backgroundColor]),
		[['png', 'transparent'], ['webp', 'transparent'], ['jpeg', '#ffffff'], ['bmp', '#ffffff']]);
	assertEquals(page.canvas.backgroundColor, 'transparent');
});

Deno.test('the own color comes back even when the image fails', () =>
{
	using page = createWrapper();
	page.canvas.toDataURL = () => { throw new Error('tainted canvas'); };

	assertThrows(() => page.wrapper.toBase64Image('jpeg', 1, '#ffffff'), Error, 'tainted canvas');

	assertEquals(page.canvas.backgroundColor, '');
});
