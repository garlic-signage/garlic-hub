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


import {assertEquals}   from "@std/assert";
import {stub}           from "@std/testing/mock";
import {createDom}      from "@support/dom.js";
import {replaceGlobals} from "@support/globals.js";
import {WidgetPreview}  from "@js/mediapool/uploads/Preview/WidgetPreview.js";

const WIDGET = {name: 'clock.wgt', size: 1, type: 'application/widget'};

/**
 * JSZip with the given files of the widget, or failing with the given Error.
 */
function fakeJsZip(content)
{
	return class
	{
		static loaded = [];

		loadAsync(file)
		{
			this.constructor.loaded.push(file);
			if (content instanceof Error)
				return Promise.reject(content);

			const files = {};
			for (const name of content)
				files[name] = {async: (type) => Promise.resolve({blob: name, type: type})};

			return Promise.resolve({files});
		}
	};
}

/**
 * The widget with JSZip and URL.createObjectURL of the test; waits until the zip is read.
 */
async function preview(content)
{
	const JSZip   = fakeJsZip(content);
	const blobs   = [];
	using _globals = replaceGlobals({JSZip: JSZip, URL: {createObjectURL: (blob) => { blobs.push(blob); return 'blob:' + blob.blob; }}});

	const image = new WidgetPreview(WIDGET).createPreview();
	const src   = image.getAttribute('src');
	await new Promise(resolve => setTimeout(resolve, 0));

	return {image, src, blobs, loaded: JSZip.loaded};
}

Deno.test('the widget shows the widget icon until its own icon is read', async () =>
{
	using _dom = createDom();

	const {src, loaded} = await preview([]);

	assertEquals(src, '/images/icons/widget.svg');
	assertEquals(loaded.length, 1);
	assertEquals(loaded[0] === WIDGET, true);
});

Deno.test('the png icon of the widget is shown, else the jpg icon', async () =>
{
	using _dom = createDom();

	const png = await preview(['config.xml', 'icon.jpg', 'icon.png']);
	assertEquals(png.image.getAttribute('src'), 'blob:icon.png');
	assertEquals(png.blobs, [{blob: 'icon.png', type: 'blob'}]);

	const jpg = await preview(['config.xml', 'icon.jpg']);
	assertEquals(jpg.image.getAttribute('src'), 'blob:icon.jpg');
});

Deno.test('a widget without icon keeps the widget icon', async () =>
{
	using _dom = createDom();

	const {image, blobs} = await preview(['config.xml', 'images/icon.png', 'icon.svg']);

	assertEquals(image.getAttribute('src'), '/images/icons/widget.svg');
	assertEquals(blobs, []);
});

Deno.test('a broken zip is logged and keeps the widget icon', async () =>
{
	using _dom      = createDom();
	using errorStub = stub(console, 'error');

	const {image} = await preview(new Error('End of data reached'));

	assertEquals(image.getAttribute('src'), '/images/icons/widget.svg');
	assertEquals(errorStub.calls[0].args, ['Error in zip-process:', 'End of data reached']);
});
